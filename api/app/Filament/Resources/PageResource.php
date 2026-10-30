<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Filament\Support\Editor;
use App\Filament\Support\Fields;
use App\Models\Page;
use App\Models\Site;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                ...Fields::titleWithSlug('title', 'Заголовок', uniqueInLocale: false),
                Fields::locale()->live(),
                Select::make('parent_id')->label('Розділ (батьківська сторінка)')
                    ->options(fn (Get $get, ?Page $record) => Page::where('locale', $get('locale') ?: 'uk')
                        ->when($record, fn ($q) => $q->where('id', '!=', $record->id))->orderBy('path')->pluck('path', 'id'))
                    ->searchable(),
                Textarea::make('excerpt')->label('Короткий опис')->rows(2)->columnSpanFull(),
                Editor::make('body', 'pages')->label('Текст')->columnSpanFull(),
                ...Fields::image('cover_image', 'Обкладинка', 'pages'),
                TextInput::make('sort')->label('Порядок')->numeric()->default(0),
                Toggle::make('is_published')->label('Опубліковано')->default(true),
            ])->columnSpanFull(),
            Fields::seo()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('path')->columns([
            TextColumn::make('title')->label('Заголовок')->searchable()->wrap(),
            TextColumn::make('path')->label('Адреса')->formatStateUsing(fn (Page $record) => $record->url())->searchable()->sortable(),
            TextColumn::make('locale')->label('Мова')->badge(),
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
