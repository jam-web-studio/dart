<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Filament\Support\Fields;
use App\Models\Page;
use App\Models\Site;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $modelLabel = 'Сторінка';

    protected static ?string $pluralModelLabel = 'Сторінки';

    protected static string|UnitEnum|null $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('title')->label('Назва')->required()->maxLength(255),
                TextInput::make('path')->label('Адреса')->required()->maxLength(255)
                    ->prefix('/')->helperText('Як на старому сайті: licensuvannya, departments/finances. Головна: home.')
                    ->regex('~^[a-z0-9\-_/]+$~'),
                Fields::locale(),
                Toggle::make('is_published')->label('Опубліковано')->default(true),
                CodeEditor::make('body')->label('Розмітка сторінки (HTML)')->language(Language::Html)->columnSpanFull()
                    ->helperText('Розмітка старого сайту з класами Webflow. Списки новин, наказів і звітів позначені <template data-collection=…> і заповнюються автоматично.'),
            ])->columnSpanFull(),
            Section::make('Для розробника')->collapsed()->columns(2)->schema([
                TextInput::make('wf_page')->label('data-wf-page')->helperText('Ідентифікатор сторінки Webflow для анімацій.'),
                Textarea::make('styles')->label('Свої стилі сторінки (JSON)')->rows(3)
                    ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '')
                    ->dehydrateStateUsing(fn ($state) => $state ? json_decode($state, true) : null)->columnSpanFull(),
                Textarea::make('scripts')->label('Свої скрипти сторінки (JSON)')->rows(3)
                    ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '')
                    ->dehydrateStateUsing(fn ($state) => $state ? json_decode($state, true) : null)->columnSpanFull(),
            ])->columnSpanFull(),
            Section::make('SEO')->collapsed()->schema([
                TextInput::make('meta_title')->label('Meta title')->maxLength(255),
                Textarea::make('meta_description')->label('Meta description')->rows(2),
                TextInput::make('og_image')->label('Зображення для соцмереж (URL)'),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('path')->columns([
            TextColumn::make('title')->label('Назва')->searchable()->wrap(),
            TextColumn::make('path')->label('Адреса')->formatStateUsing(fn (Page $record) => $record->url())->searchable()->sortable(),
            TextColumn::make('locale')->label('Мова')->badge(),
            TextColumn::make('updated_at')->label('Змінено')->dateTime('d.m.Y H:i')->sortable(),
            ToggleColumn::make('is_published')->label('Опубл.'),
        ])->filters([
            SelectFilter::make('locale')->label('Мова')->options(Site::LOCALES),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
