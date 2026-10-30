<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use App\Models\Page;
use App\Models\Post;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContentStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Новини', Post::count())->description(Post::where('is_published', false)->count().' чернеток')->icon('heroicon-o-newspaper'),
            Stat::make('Сторінки', Page::count())->icon('heroicon-o-document-text'),
            Stat::make('Документи', Document::count())->icon('heroicon-o-document-arrow-down'),
        ];
    }
}
