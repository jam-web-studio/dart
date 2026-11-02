<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Document;
use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteApiTest extends TestCase
{
    use RefreshDatabase;

    private function news(): Category
    {
        return Category::create(['slug' => 'news', 'name' => 'Новини']);
    }

    public function test_page_with_news_list_and_pager(): void
    {
        $cat = $this->news();
        foreach (range(1, 3) as $i) {
            Post::create(['slug' => "n$i", 'title' => "Новина $i", 'category_id' => $cat->id, 'published_at' => now()->subDays(4 - $i)]);
        }
        Post::create(['slug' => 'future', 'title' => 'Майбутня', 'published_at' => now()->addDay()]);
        $pager = e('<div class="w-pagination-wrapper"><!--prev--><a href="{{prev}}">Назад</a><!--/prev--><span>{{page}}/{{pages}}</span><!--next--><a href="{{next}}">Далі</a><!--/next--></div>');
        Page::create(['path' => 'news-and-announcements', 'title' => 'Новини та анонси', 'wf_page' => 'abc',
            'body' => '<div class="list"><template data-collection="news" data-limit="2" data-page-param="p1" data-pager="'.$pager.'">'
                .'<a href="{{url}}">{{title}} · {{date|d.m.Y}}</a></template></div><template data-pager-slot="p1"></template>']);

        $html = $this->getJson('/api/v1/render?path=/news-and-announcements')->assertOk()
            ->assertJsonPath('wf_page', 'abc')->json('html');
        $this->assertStringContainsString('<a href="/blog/n3">Новина 3', $html);
        $this->assertStringNotContainsString('Майбутня', $html);
        $this->assertStringNotContainsString('Назад', $html);
        $this->assertStringContainsString('href="?p1=2">Далі', $html);

        $html = $this->getJson('/api/v1/render?path=news-and-announcements&p1=2')->json('html');
        $this->assertStringContainsString('Новина 1', $html);
        $this->assertStringContainsString('<span>2/2</span>', $html);
        $this->assertStringNotContainsString('Далі', $html);

        $this->getJson('/api/v1/render?path=/nope')->assertNotFound();
        $this->getJson('/api/v1/render?path=/blog/future')->assertNotFound();
    }

    public function test_record_templates(): void
    {
        Setting::create(['key' => 'template_post', 'value' => ['wf_page' => 'p', 'body' => '<h1>{{title}}</h1><div class="news-date">{{date|d.m.Y}}</div><div class="rich">{{{body}}}</div>', 'scripts' => [], 'styles' => []]]);
        Setting::create(['key' => 'template_order', 'value' => ['wf_page' => 'o', 'body' => '<div>№{{number}} {{date|Y-m-d}}</div><div id="pdf-view" data-pdf="{{pdf}}"></div>', 'scripts' => [['code' => 'x']], 'styles' => []]]);
        Post::create(['slug' => 'karpaty', 'title' => 'Карпати <b>', 'body' => '<p>Текст {{title}}</p>', 'published_at' => '2026-10-27 12:00:00']);
        Document::create(['kind' => 'order', 'slug' => 'n18', 'title' => 'Про ліцензію', 'number' => '18', 'date' => '2023-03-02', 'url' => 'https://example.com/a.pdf']);

        $this->getJson('/api/v1/render?path=/blog/karpaty')->assertOk()->assertJsonPath('type', 'blog')
            ->assertJsonPath('html', '<h1>Карпати &lt;b&gt;</h1><div class="news-date">27.10.2026</div><div class="rich"><p>Текст {{title}}</p></div>');
        $this->getJson('/api/v1/render?path=/orders/n18')->assertOk()->assertJsonPath('scripts.0.code', 'x')
            ->assertJsonPath('html', '<div>№18 2023-03-02</div><div id="pdf-view" data-pdf="https://example.com/a.pdf"></div>');
    }

    public function test_import_keeps_admin_edits(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'seed');
        file_put_contents($file, json_encode([
            'layout' => ['header' => '<header>Шапка</header>', 'footer' => '<footer></footer>', 'accessibility' => '', 'global_script' => '', 'head_styles' => []],
            'templates' => ['post' => ['body' => '{{title}}'], 'order' => ['body' => ''], 'report' => ['body' => '']],
            'pages' => [['path' => 'contact', 'title' => 'Контакти', 'body' => '<p>Адреса</p>', 'scripts' => [], 'styles' => [], 'wf_page' => 'c']],
            'posts' => [['slug' => 'one', 'title' => 'Перша', 'body' => '<p>1</p>', 'published_at' => '2026-10-01', 'category' => 'news', 'cover_image' => null, 'gallery' => [], 'is_featured' => false, 'is_main' => false]],
            'documents' => [['kind' => 'report', 'slug' => 'r1', 'title' => 'Звіт', 'meta' => ['month' => 3, 'year' => 2025], 'sort' => 202503]],
        ]));

        $this->artisan('site:import', ['file' => $file])->assertSuccessful();
        $this->getJson('/api/v1/layout')->assertOk()->assertJsonPath('header', '<header>Шапка</header>');
        Page::where('path', 'contact')->update(['title' => 'Контакти ДАРТ']);
        $this->artisan('site:import', ['file' => $file])->assertSuccessful();
        $this->assertSame('Контакти ДАРТ', Page::where('path', 'contact')->value('title'));
        $this->assertSame(3, Document::where('slug', 'r1')->first()->meta['month']);
        $this->artisan('site:import', ['file' => $file, '--force' => true])->assertSuccessful();
        $this->assertSame('Контакти', Page::where('path', 'contact')->value('title'));
        $this->getJson('/api/v1/render?path=/blog/one')->assertOk()->assertJsonPath('html', 'Перша');
    }

    public function test_search_and_sitemap(): void
    {
        Post::create(['slug' => 'karpaty', 'title' => 'Туризм у Карпатах', 'body' => '<p>Гори</p><p>і ліси</p>', 'published_at' => now()->subHour()]);
        Page::create(['path' => 'home', 'title' => 'Головна', 'body' => '<p>ДАРТ</p>']);

        $this->getJson('/api/v1/search?query=карпат')->assertOk()
            ->assertJsonPath('data.0.url', '/blog/karpaty')
            ->assertJsonPath('data.0.text', 'Гори і ліси');
        $this->getJson('/api/v1/sitemap')->assertOk()->assertJsonFragment(['url' => '/']);
    }

    public function test_admin_pages_open(): void
    {
        $admin = User::create(['name' => 'Адмін Тест', 'email' => 'a@t.test', 'password' => 'secret123', 'role' => 'admin']);
        Post::create(['slug' => 'one', 'title' => 'Новина', 'published_at' => now()]);
        Page::create(['path' => 'about', 'title' => 'Про нас', 'body' => '<p>x</p>']);
        Document::create(['kind' => 'report', 'slug' => 'r', 'title' => 'Звіт', 'meta' => ['month' => 1, 'year' => 2024]]);
        foreach (['/admin', '/admin/posts', '/admin/posts/create', '/admin/posts/1/edit', '/admin/pages', '/admin/pages/1/edit', '/admin/documents', '/admin/documents/create', '/admin/documents/1/edit', '/admin/categories', '/admin/site-settings', '/admin/users'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
