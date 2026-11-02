<?php

use App\Http\Controllers\Api\SiteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:240,1')->group(function () {
    Route::get('layout', [SiteController::class, 'layout']);
    Route::get('render', [SiteController::class, 'render']);
    Route::get('search', [SiteController::class, 'search']);
    Route::get('sitemap', [SiteController::class, 'sitemap']);
});
