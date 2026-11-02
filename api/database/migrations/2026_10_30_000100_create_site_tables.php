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

        // сторінки сайту: розмітка Webflow як є (path = адреса без мови: «licensuvannya», «departments/finances»);
        // динамічні списки в тексті позначені <template data-collection=…> і заповнюються з бази
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5)->default('uk');
            $table->string('path');
            $table->string('title');
            $table->longText('body')->nullable();
            $table->json('scripts')->nullable();
            $table->json('styles')->nullable();
            $table->string('wf_page', 40)->nullable(); // data-wf-page: потрібен скриптам Webflow для анімацій сторінки
            $table->boolean('is_published')->default(true);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('og_image', 1000)->nullable();
            $table->string('imported_hash', 32)->nullable();
            $table->string('source_url')->nullable();
            $table->timestamps();
            $table->unique(['locale', 'path']);
        });

        // рубрики новин: новина, анонс
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
            $table->string('title', 500);
            $table->longText('body')->nullable();
            $table->string('cover_image', 1000)->nullable();
            $table->json('gallery')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->boolean('is_published')->default(true);
            $table->boolean('is_featured')->default(false); // «Актуальні новини»
            $table->boolean('is_main')->default(false); // «Остання новина»
            $table->string('meta_title', 500)->nullable();
            $table->text('meta_description')->nullable();
            $table->string('og_image', 1000)->nullable();
            $table->string('imported_hash', 32)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->timestamps();
            $table->unique(['locale', 'slug']);
        });

        // накази (/orders/{slug}, PDF) і звіти щодо запитів на інформацію (/reports/{slug})
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20)->default('order');
            $table->string('locale', 5)->default('uk');
            $table->string('slug');
            $table->string('title', 1000);
            $table->string('number')->nullable();
            $table->date('date')->nullable();
            $table->string('file')->nullable();
            $table->string('url', 2000)->nullable();
            $table->json('meta')->nullable(); // звіт: {month, year}
            $table->integer('sort')->default(0);
            $table->boolean('is_published')->default(true);
            $table->string('imported_hash', 32)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->timestamps();
            $table->unique(['kind', 'locale', 'slug']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['settings', 'documents', 'posts', 'categories', 'pages'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
