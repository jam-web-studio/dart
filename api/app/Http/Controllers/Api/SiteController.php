<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Document;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SiteController extends Controller
{
    private function locale(Request $request): string
    {
        $l = (string) $request->query('locale', 'uk');

        return array_key_exists($l, Site::LOCALES) ? $l : 'uk';
    }

    private function posts(string $locale)
    {
        return Post::published()->where('locale', $locale)->with('category')
            ->orderByDesc('published_at')->orderByDesc('id');
    }

    /** Шапка й підвал: меню, контакти, соцмережі (на кожній сторінці) */
    public function layout(Request $request)
    {
        $l = $this->locale($request);

        return [
            'settings' => Setting::publicAll(),
            'menus' => collect(MenuItem::MENUS)->keys()->mapWithKeys(fn ($m) => [$m => MenuItem::tree($m, $l)]),
            'locales' => Site::LOCALES,
        ];
    }

    public function home(Request $request)
    {
        $l = $this->locale($request);

        return [
            'featured' => $this->posts($l)->where('is_featured', true)->limit(5)->get()->map(fn ($p) => Present::postCard($p)),
            'news' => $this->posts($l)->limit(9)->get()->map(fn ($p) => Present::postCard($p)),
            'banners' => Banner::where('locale', $l)->where('is_published', true)->orderBy('sort')->get()
                ->groupBy('place')->map(fn ($g) => $g->map(fn ($b) => Present::banner($b))->values()),
        ];
    }

    public function news(Request $request)
    {
        $l = $this->locale($request);
        $q = $this->posts($l)
            ->when($request->query('category'), fn ($q, $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->when($request->query('q'), fn ($q, $term) => $this->match($q, ['title', 'excerpt'], $term))
            ->paginate((int) min(48, max(1, (int) $request->query('per_page', 12))));

        return [
            'data' => collect($q->items())->map(fn ($p) => Present::postCard($p)),
            'meta' => ['page' => $q->currentPage(), 'pages' => $q->lastPage(), 'total' => $q->total()],
            'categories' => Category::where('locale', $l)->orderBy('sort')->get(['slug', 'name']),
        ];
    }

    public function post(Request $request, string $slug)
    {
        $l = $this->locale($request);
        $post = $this->posts($l)->where('slug', $slug)->firstOrFail();

        return [
            'data' => Present::post($post),
            'related' => $this->posts($l)->where('id', '!=', $post->id)->limit(3)->get()->map(fn ($p) => Present::postCard($p)),
        ];
    }

    /** Сторінка за повною адресою: /api/v1/pages?path=about/structure */
    public function page(Request $request)
    {
        $page = Page::published()->where('locale', $this->locale($request))
            ->where('path', trim((string) $request->query('path'), '/'))->firstOrFail();

        return ['data' => Present::page($page)];
    }

    public function documents(Request $request)
    {
        $docs = Document::where('is_published', true)->where('locale', $this->locale($request))
            ->when($request->query('group'), fn ($q, $g) => $q->where('group', $g))
            ->orderBy('group')->orderBy('sort')->orderByDesc('date')->get();

        return ['data' => $docs->map(fn ($d) => Present::document($d))->values(), 'groups' => $docs->pluck('group')->filter()->unique()->values()];
    }

    public function search(Request $request)
    {
        $l = $this->locale($request);
        $term = trim((string) $request->query('q'));
        if (mb_strlen($term) < 2) {
            return ['posts' => [], 'pages' => []];
        }

        return [
            'posts' => $this->match($this->posts($l), ['title', 'excerpt', 'body'], $term)->limit(20)->get()->map(fn ($p) => Present::postCard($p)),
            'pages' => $this->match(Page::published()->where('locale', $l), ['title', 'body'], $term)->limit(20)->get()->map(fn ($p) => Present::pageLink($p)),
        ];
    }

    public function sitemap()
    {
        return [
            'pages' => Page::published()->get()->map(fn ($p) => ['url' => $p->url(), 'updated_at' => $p->updated_at?->toAtomString()]),
            'posts' => Post::published()->get()->map(fn ($p) => ['url' => $p->url(), 'updated_at' => $p->updated_at?->toAtomString()]),
        ];
    }

    // ilike у PostgreSQL; у SQLite (тести, локально) like не знає регістру кирилиці, тож шукаємо і з великої літери
    private function match($q, array $cols, string $term)
    {
        $op = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $terms = array_unique([$term, mb_strtolower($term), mb_convert_case($term, MB_CASE_TITLE)]);

        return $q->where(fn ($w) => collect($cols)->crossJoin($terms)->each(fn ($ct) => $w->orWhere($ct[0], $op, '%'.$ct[1].'%')));
    }
}
