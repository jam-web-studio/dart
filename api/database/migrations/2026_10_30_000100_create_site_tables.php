<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('editor')->after('email');
        });

        // статичні сторінки: «Про агентство», «Структура», «Контакти»…; path = повна адреса без мови (about/structure)
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('locale', 5)->default('uk');
            $table->string('slug');
            $table->string('path');
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('template', 30)->default('default');
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->string('source_url')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
            $table->unique(['locale', 'path']);
        });

        // рубрики стрічки: новини, анонси, оголошення…
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5)->default('uk');
            $table->string('slug');
            $table->string('name');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['locale', 'slug']);
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('locale', 5)->default('uk');
            $table->string('slug');
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('cover_image')->nullable();
            $table->json('gallery')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->boolean('is_published')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->string('source_url')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
            $table->unique(['locale', 'slug']);
        });

        // нормативні документи, звіти, плани
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5)->default('uk');
            $table->string('group')->nullable();
            $table->string('title', 1000);
            $table->string('number')->nullable();
            $table->date('date')->nullable();
            $table->string('file')->nullable();
            $table->string('url', 2000)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // меню шапки й підвалу (дворівневе)
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('menu', 20)->default('header');
            $table->string('locale', 5)->default('uk');
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('label');
            $table->string('url', 1000);
            $table->boolean('new_tab')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // банери/посилання на головній (партнерські ресурси, проєкти)
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5)->default('uk');
            $table->string('place', 30)->default('home');
            $table->string('title');
            $table->text('text')->nullable();
            $table->string('image')->nullable();
            $table->string('url', 1000)->nullable();
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['settings', 'banners', 'menu_items', 'documents', 'posts', 'categories', 'pages'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
