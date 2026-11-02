<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Site;
use App\Support\Webflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SiteController extends Controller
{
    private function locale(Request $request): string
    {
        $l = (string) $request->query('locale', 'uk');

        return array_key_exists($l, Site::LOCALES) ? $l : 'uk';
    }

    /** Шапка, підвал, віджет доступності й загальний скрипт (однакові на всіх сторінках) */
    public function layout()
    {
        return [
            'settings' => Setting::publicAll(),
            'header' => (string) Setting::get('layout_header', ''),
            'footer' => (string) Setting::get('layout_footer', ''),
            'accessibility' => (string) Setting::get('layout_accessibility', ''),
            'global_script' => (string) Setting::get('layout_global_script', ''),
            'head_styles' => (array) Setting::get('layout_head_styles', []),
        ];
    }

    /**
     * Готовий HTML сторінки за адресою старого сайту: «/», «licensuvannya», «blog/{slug}», «orders/{slug}», «reports/{slug}».
     * Решта параметрів запиту — номери сторінок списків (?6f78dda3_page=2).
     */
    public function render(Request $request)
    {
        $l = $this->locale($request);
        $path = trim((string) $request->query('path'), '/') ?: 'home';
        $query = collect($request->query())->except(['path', 'locale'])->map(fn ($v) => (string) $v)->all();

        if (preg_match('~^(blog|orders|reports)/([^/]+)$~', $path, $m)) {
            return $this->record($m[1], $m[2], $l);
        }

        $page = Page::published()->where('locale', $l)->where('path', $path)->firstOrFail();

        return [
            'type' => 'page',
            'title' => $page->title,
            'meta' => ['title' => $page->meta_title ?: $page->title, 'description' => $page->meta_description, 'image' => $page->og_image],
            'wf_page' => $page->wf_page,
            'html' => Webflow::render((string) $page->body, $l, $query),
            'scripts' => $page->scripts ?? [],
            'styles' => $page->styles ?? [],
            'updated_at' => $page->updated_at?->toAtomString(),
        ];
    }

    private function record(string $section, string $slug, string $l): array
    {
        if ($section === 'blog') {
            $post = Post::published()->where('locale', $l)->where('slug', $slug)->firstOrFail();
            $vars = Webflow::postVars($post);
            $meta = ['title' => $post->meta_title ?: $post->title, 'description' => $post->meta_description, 'image' => Post::mediaUrl($post->og_image ?: $post->cover_image)];
            $tpl = Webflow::template('post');
            $title = $post->title;
            $updated = $post->updated_at;
        } else {
            $kind = $section === 'reports' ? 'report' : 'order';
            $doc = Document::published()->where('kind', $kind)->where('locale', $l)->where('slug', $slug)->firstOrFail();
            $vars = Webflow::documentVars($doc);
            $meta = ['title' => $doc->title, 'description' => null, 'image' => null];
            $tpl = Webflow::template($kind);
            $title = $doc->title;
            $updated = $doc->updated_at;
        }

        return [
            'type' => $section,
            'title' => $title,
            'meta' => $meta,
            'wf_page' => $tpl['wf_page'] ?? null,
            'html' => Webflow::fill((string) ($tpl['body'] ?? ''), $vars),
            'scripts' => $tpl['scripts'] ?? [],
            'styles' => $tpl['styles'] ?? [],
            'updated_at' => $updated?->toAtomString(),
        ];
    }

    public function search(Request $request)
    {
        $l = $this->locale($request);
        $term = trim((string) $request->query('query', $request->query('q', '')));
        if (mb_strlen($term) < 2) {
            return ['data' => []];
        }
        $posts = $this->match(Post::published()->where('locale', $l), ['title', 'body'], $term)
            ->orderByDesc('published_at')->limit(30)->get()
            ->map(fn (Post $p) => ['title' => $p->title, 'url' => $p->url(), 'date' => $p->published_at?->format('d.m.Y'), 'text' => $this->snippet($p->body, $term)]);
        $pages = $this->match(Page::published()->where('locale', $l), ['title', 'body'], $term)->limit(20)->get()
            ->map(fn (Page $p) => ['title' => $p->title, 'url' => $p->url(), 'date' => null, 'text' => $this->snippet($p->body, $term)]);
        $docs = $this->match(Document::published()->where('locale', $l), ['title', 'number'], $term)->limit(20)->get()
            ->map(fn (Document $d) => ['title' => $d->title, 'url' => $d->urlPath(), 'date' => $d->date?->format('d.m.Y'), 'text' => null]);

        return ['data' => $pages->concat($posts)->concat($docs)->values()];
    }

    public function sitemap()
    {
        $item = fn ($url, $at) => ['url' => $url, 'updated_at' => $at?->toAtomString()];

        return ['data' => Page::published()->get()->map(fn ($p) => $item($p->url(), $p->updated_at))
            ->concat(Post::published()->get()->map(fn ($p) => $item($p->url(), $p->updated_at)))
            ->concat(Document::published()->get()->map(fn ($d) => $item($d->urlPath(), $d->updated_at)))
            ->values()];
    }

    private function snippet(?string $html, string $term): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace(['~<(script|style)\b.*?</\1>~is', '~<[^>]+>~'], ['', '$0 '], (string) $html)))));
        $pos = mb_stripos($text, $term);
        if ($text === '' || $pos === false) {
            return $text !== '' ? mb_substr($text, 0, 200) : null;
        }

        return ($pos > 80 ? '…' : '').mb_substr($text, max(0, $pos - 80), 220).'…';
    }

    // ilike у PostgreSQL; у SQLite (тести, локально) like не знає регістру кирилиці, тож шукаємо і з великої літери
    private function match($q, array $cols, string $term)
    {
        $op = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $terms = array_unique([$term, mb_strtolower($term), mb_convert_case($term, MB_CASE_TITLE)]);

        return $q->where(fn ($w) => collect($cols)->crossJoin($terms)->each(fn ($ct) => $w->orWhere($ct[0], $op, '%'.$ct[1].'%')));
    }
}
