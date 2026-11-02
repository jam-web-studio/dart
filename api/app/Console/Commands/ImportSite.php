<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Document;
use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Переносить контент tourism.gov.ua зі знімка database/seed-data/tourism-gov.json (робить tools/convert_webflow.py).
 * Повторний запуск оновлює записи, яких не змінювали в адмінці після попереднього імпорту
 * (imported_hash збігається з поточним вмістом); --force перезаписує все.
 */
class ImportSite extends Command
{
    protected $signature = 'site:import {file? : JSON знімка} {--force : перезаписати й відредаговані записи}';

    protected $description = 'Імпорт сторінок, новин, наказів і звітів зі знімка старого сайту';

    private int $skipped = 0;

    public function handle(): int
    {
        $file = $this->argument('file') ?: database_path('seed-data/tourism-gov.json');
        $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($data) {
            foreach ($data['layout'] as $key => $value) {
                $this->setting('layout_'.$key, $value);
            }
            foreach ($data['templates'] as $key => $value) {
                $this->setting('template_'.$key, $value);
            }

            foreach ($data['pages'] as $p) {
                $this->upsert(Page::class, ['locale' => 'uk', 'path' => $p['path']], [
                    'title' => mb_substr($p['title'], 0, 255),
                    'body' => $p['body'],
                    'scripts' => $p['scripts'] ?? [],
                    'styles' => $p['styles'] ?? [],
                    'wf_page' => $p['wf_page'] ?? null,
                    'meta_title' => $p['meta_title'] ?? null,
                    'meta_description' => $p['meta_description'] ?? null,
                    'og_image' => $p['og_image'] ?? null,
                ]);
            }

            $cats = collect(['news' => 'Новини', 'announcement' => 'Анонси'])
                ->map(fn ($name, $slug) => Category::firstOrCreate(['locale' => 'uk', 'slug' => $slug], ['name' => $name])->id);
            foreach ($data['posts'] as $p) {
                $this->upsert(Post::class, ['locale' => 'uk', 'slug' => $p['slug']], [
                    'title' => $p['title'],
                    'body' => $p['body'],
                    'cover_image' => $p['cover_image'] ?? null,
                    'gallery' => $p['gallery'] ?? [],
                    'published_at' => ! empty($p['published_at']) ? $p['published_at'].' 12:00:00' : null,
                    'category_id' => $cats[$p['category'] ?? 'news'] ?? $cats['news'],
                    'is_featured' => (bool) ($p['is_featured'] ?? false),
                    'is_main' => (bool) ($p['is_main'] ?? false),
                    'meta_title' => $p['meta_title'] ?? null,
                    'meta_description' => $p['meta_description'] ?? null,
                    'og_image' => $p['og_image'] ?? null,
                    'source_url' => $p['source_url'] ?? null,
                ]);
            }

            foreach ($data['documents'] as $d) {
                $this->upsert(Document::class, ['kind' => $d['kind'], 'locale' => 'uk', 'slug' => $d['slug']], [
                    'title' => $d['title'],
                    'number' => $d['number'] ?? null,
                    'date' => $d['date'] ?? null,
                    'url' => $d['url'] ?? null,
                    'meta' => $d['meta'] ?? null,
                    'sort' => $d['sort'] ?? 0,
                    'source_url' => $d['source_url'] ?? null,
                ]);
            }
        });

        $this->info(sprintf('Сторінок: %d, новин: %d, наказів: %d, звітів: %d; пропущено відредагованих: %d',
            Page::count(), Post::count(), Document::where('kind', 'order')->count(), Document::where('kind', 'report')->count(), $this->skipped));

        return self::SUCCESS;
    }

    /** Шапку й шаблони, змінені в адмінці, теж не перезаписуємо (відбитки імпорту в налаштуванні import_hashes) */
    private function setting(string $key, $value): void
    {
        $hashes = (array) (Setting::find('import_hashes')?->value ?? []);
        $current = Setting::find($key)?->value;
        if ($current !== null && ! $this->option('force') && isset($hashes[$key]) && $hashes[$key] !== md5(json_encode($current))) {
            $this->skipped++;

            return;
        }
        $saved = Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        $hashes[$key] = md5(json_encode($saved->fresh()->value));
        Setting::updateOrCreate(['key' => 'import_hashes'], ['value' => $hashes]);
    }

    /** @param class-string<Model> $class */
    private function upsert(string $class, array $keys, array $values): void
    {
        $model = $class::where($keys)->first();
        if ($model && ! $this->option('force') && $model->imported_hash && $model->imported_hash !== $this->hash($model, array_keys($values))) {
            $this->skipped++;

            return;
        }
        $model ??= new $class($keys);
        $model->fill($values);
        $model->imported_hash = $this->hash($model, array_keys($values));
        $model->save();
    }

    /** Відбиток імпортованих полів у тому вигляді, як їх повертає модель */
    private function hash(Model $model, array $fields): string
    {
        return md5(json_encode(collect($fields)->mapWithKeys(fn ($f) => [$f => $this->norm($model->getAttribute($f))])->all(), JSON_UNESCAPED_UNICODE));
    }

    private function norm($v)
    {
        return $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : $v;
    }
}
