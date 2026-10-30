<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Document;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_and_home(): void
    {
        $top = MenuItem::create(['label' => 'Про агентство', 'url' => '/about']);
        MenuItem::create(['label' => 'Структура', 'url' => '/about/structure', 'parent_id' => $top->id]);
        $cat = Category::create(['slug' => 'news', 'name' => 'Новини']);
        Post::create(['slug' => 'one', 'title' => 'Перша новина', 'category_id' => $cat->id, 'published_at' => now()->subDay(), 'is_featured' => true]);
        Post::create(['slug' => 'future', 'title' => 'Майбутня', 'published_at' => now()->addDay()]);
        Post::create(['slug' => 'en-one', 'title' => 'English news', 'locale' => 'en', 'published_at' => now()->subDay()]);

        $this->getJson('/api/v1/layout')->assertOk()
            ->assertJsonPath('menus.header.0.label', 'Про агентство')
            ->assertJsonPath('menus.header.0.children.0.url', '/about/structure');

        $this->getJson('/api/v1/home')->assertOk()
            ->assertJsonCount(1, 'news')
            ->assertJsonPath('featured.0.url', '/news/one');

        $this->getJson('/api/v1/news?locale=en')->assertOk()->assertJsonPath('data.0.url', '/en/news/en-one');
        $this->getJson('/api/v1/news/future')->assertNotFound();
        $this->getJson('/api/v1/news/one')->assertOk()->assertJsonPath('data.category.name', 'Новини');
    }

    public function test_nested_pages_by_path(): void
    {
        $about = Page::create(['slug' => 'about', 'title' => 'Про агентство']);
        $structure = Page::create(['slug' => 'structure', 'title' => 'Структура', 'parent_id' => $about->id]);
        $this->assertSame('about/structure', $structure->path);

        $this->getJson('/api/v1/pages?path=about/structure')->assertOk()
            ->assertJsonPath('data.breadcrumbs.0.url', '/about')
            ->assertJsonPath('data.section.0.title', 'Структура');

        // перейменування розділу оновлює адреси дочірніх сторінок
        $about->update(['slug' => 'agency']);
        $this->assertSame('agency/structure', $structure->fresh()->path);
        $this->getJson('/api/v1/pages?path=about/structure')->assertNotFound();
    }

    public function test_documents_and_search(): void
    {
        Document::create(['title' => 'Положення про агентство', 'group' => 'Нормативна база', 'url' => 'https://zakon.rada.gov.ua/x']);
        Post::create(['slug' => 'karpaty', 'title' => 'Туризм у Карпатах', 'published_at' => now()->subHour()]);

        $this->getJson('/api/v1/documents')->assertOk()->assertJsonPath('groups.0', 'Нормативна база');
        $this->getJson('/api/v1/search?q=карпат')->assertOk()->assertJsonPath('posts.0.slug', 'karpaty');
    }

    public function test_admin_pages_open(): void
    {
        $admin = User::create(['name' => 'Адмін Тест', 'email' => 'a@t.test', 'password' => 'secret123', 'role' => 'admin']);
        Post::create(['slug' => 'one', 'title' => 'Новина', 'published_at' => now()]);
        Page::create(['slug' => 'about', 'title' => 'Про нас']);
        foreach (['/admin', '/admin/posts', '/admin/posts/create', '/admin/posts/1/edit', '/admin/pages', '/admin/pages/1/edit', '/admin/documents/create', '/admin/menu-items/create', '/admin/banners', '/admin/categories', '/admin/site-settings', '/admin/users'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
