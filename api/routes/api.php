<?php

use App\Http\Controllers\Api\SiteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:240,1')->group(function () {
    Route::get('layout', [SiteController::class, 'layout']);
    Route::get('home', [SiteController::class, 'home']);
    Route::get('news', [SiteController::class, 'news']);
    Route::get('news/{slug}', [SiteController::class, 'post']);
    Route::get('pages', [SiteController::class, 'page']);
    Route::get('documents', [SiteController::class, 'documents']);
    Route::get('search', [SiteController::class, 'search']);
    Route::get('sitemap', [SiteController::class, 'sitemap']);
});
