<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Адреси деяких наказів зі старого сайту довші за 255 символів
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', fn (Blueprint $t) => $t->string('slug', 500)->change());
        Schema::table('posts', fn (Blueprint $t) => $t->string('slug', 500)->change());
        Schema::table('pages', fn (Blueprint $t) => $t->string('source_url', 500)->nullable()->change());
    }

    public function down(): void {}
};
