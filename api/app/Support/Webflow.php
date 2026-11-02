<?php

namespace App\Support;

use App\Models\Document;
use App\Models\Post;
use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Збирає HTML сторінок у розмітці старого сайту (Webflow): заповнює динамічні списки
 * (<template data-collection="news|orders|reports">) записами з бази, пагінацію з тими самими
 * параметрами (?6f78dda3_page=2) і шаблони сторінок новини, наказу, звіту.
 */
class Webflow
{
    /** @param  array<string, string>  $query  параметри адреси (номери сторінок списків) */
    public static function render(string $html, string $locale = 'uk', array $query = []): string
    {
        $pagers = [];
        $html = preg_replace_callback('~<template\s+data-collection="(\w+)"([^>]*)>(.*?)</template>~s', function ($m) use ($locale, $query, &$pagers) {
            $attrs = self::attrs($m[2]);
            $limit = max(1, (int) ($attrs['data-limit'] ?? 10));
            $param = $attrs['data-page-param'] ?? null;
            $page = $param ? max(1, (int) ($query[$param] ?? 1)) : 1;
            [$items, $total] = self::items($m[1], $attrs['data-filter'] ?? 'all', $locale, $limit, ($page - 1) * $limit);
            if ($param) {
                $pagers[$param] = self::pager(html_entity_decode($attrs['data-pager'] ?? '', ENT_QUOTES | ENT_HTML5), $param, $page, (int) ceil($total / $limit));
            }

            return implode('', array_map(fn ($vars) => self::fill($m[3], $vars), $items));
        }, $html);

        return preg_replace_callback('~<template\s+data-pager-slot="(\w+)"\s*></template>~', fn ($m) => $pagers[$m[1]] ?? '', $html);
    }

    /** Записи списку як змінні шаблону */
    private static function items(string $collection, string $filter, string $locale, int $limit, int $offset): array
    {
        if ($collection === 'news') {
            $q = Post::published()->where('locale', $locale)->with('category')
                ->when($filter === 'featured', fn ($q) => $q->where('is_featured', true))
                ->when($filter === 'main', fn ($q) => $q->where('is_main', true))
                ->when($filter === 'announcement', fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', 'announcement')))
                ->orderByDesc('published_at')->orderByDesc('id');
            $total = (clone $q)->count();

            return [$q->skip($offset)->take($limit)->get()->map(fn (Post $p) => self::postVars($p))->all(), $total];
        }
        $kind = $collection === 'reports' ? 'report' : 'order';
        $q = Document::published()->where('kind', $kind)->where('locale', $locale)->orderedFor($kind);
        $total = (clone $q)->count();

        return [$q->skip($offset)->take($limit)->get()->map(fn (Document $d) => self::documentVars($d))->all(), $total];
    }

    public static function postVars(Post $p): array
    {
        return [
            'url' => $p->url(),
            'title' => $p->title,
            'cover' => Post::mediaUrl($p->cover_image) ?? '',
            'date' => $p->published_at,
            'body' => $p->body ?? '',
            'gallery' => self::gallery(Post::mediaUrls($p->gallery)),
        ];
    }

    public static function documentVars(Document $d): array
    {
        return [
            'url' => $d->urlPath(),
            'title' => $d->title,
            'number' => $d->number ?? '',
            'date' => $d->date,
            'pdf' => $d->link() ?? '',
            'month' => $d->meta['month'] ?? '',
            'year' => $d->meta['year'] ?? '',
        ];
    }

    /** Галерея новини у класах Webflow */
    private static function gallery(array $urls): string
    {
        if (! $urls) {
            return '';
        }

        return '<div class="s-gallery-dynamic w-dyn-list"><div role="list" class="s-gallery-dynamic__list w-dyn-items">'
            .implode('', array_map(fn ($u) => '<div role="listitem" class="s-gallery-dynamic__item w-dyn-item"><a href="'.e($u).'" target="_blank" rel="noopener" class="s-gallery-dynamic__lightbox w-inline-block"><img src="'.e($u).'" loading="lazy" alt="" class="s-gallery-dynamic__img"></a></div>', $urls))
            .'</div></div>';
    }

    /** {{{raw}}}, {{name}} (екранується), {{date|d.m.Y}} */
    public static function fill(string $tpl, array $vars): string
    {
        // спершу {{…}}, потім {{{…}}}: інакше фігурні дужки в тексті новини теж замінювалися б
        $tpl = preg_replace_callback('~(?<!\{)\{\{(\w+)(?:\|([^}]+))?\}\}(?!\})~', function ($m) use ($vars) {
            $v = $vars[$m[1]] ?? '';
            if ($v instanceof \DateTimeInterface) {
                return Carbon::instance($v)->timezone('Europe/Kyiv')->format($m[2] ?? 'd.m.Y');
            }

            return e((string) $v);
        }, $tpl);

        return preg_replace_callback('~\{\{\{(\w+)\}\}\}~', fn ($m) => (string) ($vars[$m[1]] ?? ''), $tpl);
    }

    private static function pager(string $tpl, string $param, int $page, int $pages): string
    {
        if ($pages <= 1 || ! $tpl) {
            return '';
        }
        if ($page <= 1) {
            $tpl = preg_replace('~<!--prev-->.*?<!--/prev-->~s', '', $tpl);
        }
        if ($page >= $pages) {
            $tpl = preg_replace('~<!--next-->.*?<!--/next-->~s', '', $tpl);
        }

        return self::fill($tpl, ['prev' => "?$param=".($page - 1), 'next' => "?$param=".($page + 1), 'page' => $page, 'pages' => $pages]);
    }

    private static function attrs(string $s): array
    {
        preg_match_all('~([\w-]+)=(?:"([^"]*)"|\'([^\']*)\')~s', $s, $m, PREG_SET_ORDER);

        return collect($m)->mapWithKeys(fn ($a) => [$a[1] => $a[2] !== '' ? $a[2] : ($a[3] ?? '')])->all();
    }

    /** Шаблон сторінки запису з налаштувань: {wf_page, body, scripts, styles} */
    public static function template(string $name): array
    {
        return (array) Setting::get('template_'.$name, []);
    }
}
