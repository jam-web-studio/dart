<?php

namespace App\Http\Controllers\Api;

use App\Models\Banner;
use App\Models\Document;
use App\Models\Page;
use App\Models\Post;

/** Як записи віддаються в API (спільне для списків і карток) */
class Present
{
    public static function postCard(Post $p): array
    {
        return [
            'slug' => $p->slug,
            'url' => $p->url(),
            'title' => $p->title,
            'excerpt' => $p->excerpt,
            'cover' => Post::mediaUrl($p->cover_image),
            'date' => $p->published_at?->toAtomString(),
            'category' => $p->category ? ['slug' => $p->category->slug, 'name' => $p->category->name] : null,
        ];
    }

    public static function post(Post $p): array
    {
        return self::postCard($p) + [
            'body' => $p->body,
            'gallery' => Post::mediaUrls($p->gallery),
            'meta' => ['title' => $p->meta_title, 'description' => $p->meta_description],
            'updated_at' => $p->updated_at?->toAtomString(),
        ];
    }

    public static function pageLink(Page $p): array
    {
        return ['title' => $p->title, 'url' => $p->url(), 'path' => $p->path];
    }

    public static function page(Page $p): array
    {
        $chain = [];
        for ($a = $p->parent; $a; $a = $a->parent) {
            array_unshift($chain, self::pageLink($a));
        }
        $siblingsOf = $p->parent_id ? $p->parent : $p;

        return self::pageLink($p) + [
            'excerpt' => $p->excerpt,
            'body' => $p->body,
            'cover' => Page::mediaUrl($p->cover_image),
            'template' => $p->template,
            'breadcrumbs' => $chain,
            // бічне меню розділу: дочірні сторінки кореня розділу
            'section' => $siblingsOf->children()->published()->get()->map(fn ($c) => self::pageLink($c))->values(),
            'meta' => ['title' => $p->meta_title, 'description' => $p->meta_description],
            'updated_at' => $p->updated_at?->toAtomString(),
        ];
    }

    public static function document(Document $d): array
    {
        return [
            'id' => $d->id,
            'group' => $d->group,
            'title' => $d->title,
            'number' => $d->number,
            'date' => $d->date?->toDateString(),
            'url' => $d->link(),
            'description' => $d->description,
        ];
    }

    public static function banner(Banner $b): array
    {
        return ['title' => $b->title, 'text' => $b->text, 'image' => Banner::mediaUrl($b->image), 'url' => $b->url];
    }
}
