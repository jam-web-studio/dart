<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PostResource\Pages;
use App\Filament\Support\Editor;
use App\Filament\Support\Fields;
use App\Models\Post;
use App\Models\Site;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?string $modelLabel = 'Новина';

    protected static ?string $pluralModelLabel = 'Новини';

    protected static string|UnitEnum|null $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                ...Fields::titleWithSlug('title', 'Заголовок'),
                Fields::locale(),
                Select::make('category_id')->label('Рубрика')->relationship('category', 'name')->preload(),
                DateTimePicker::make('published_at')->label('Дата публікації')->default(now())->native(false),
                Textarea::make('excerpt')->label('Анонс')->rows(3)->columnSpanFull(),
                Editor::make('body', 'news')->label('Текст')->columnSpanFull(),
                ...Fields::image('cover_image', 'Головне фото', 'news'),
                ...Fields::gallery('gallery', 'news'),
                Toggle::make('is_published')->label('Опубліковано')->default(true),
                Toggle::make('is_featured')->label('На головній у слайдері'),
            ])->columnSpanFull(),
            Fields::seo()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('published_at', 'desc')->columns([
            ImageColumn::make('cover_image')->label('')->getStateUsing(fn (Post $record) => Post::mediaUrl($record->cover_image))->square()->imageSize(48),
            TextColumn::make('title')->label('Заголовок')->searchable()->wrap()->limit(90),
            TextColumn::make('category.name')->label('Рубрика')->badge(),
            TextColumn::make('published_at')->label('Дата')->dateTime('d.m.Y H:i')->sortable(),
            TextColumn::make('locale')->label('Мова')->badge(),
            ToggleColumn::make('is_published')->label('Опубл.'),
            ToggleColumn::make('is_featured')->label('Слайдер'),
        ])->filters([
            SelectFilter::make('category_id')->label('Рубрика')->relationship('category', 'name'),
            SelectFilter::make('locale')->label('Мова')->options(Site::LOCALES),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
