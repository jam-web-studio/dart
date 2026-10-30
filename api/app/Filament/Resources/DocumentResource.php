<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentResource\Pages;
use App\Filament\Support\Fields;
use App\Models\Document;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
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

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowDown;

    protected static ?string $modelLabel = 'Документ';

    protected static ?string $pluralModelLabel = 'Документи';

    protected static string|UnitEnum|null $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                Textarea::make('title')->label('Назва')->required()->rows(2)->columnSpanFull(),
                TextInput::make('group')->label('Розділ')->datalist(fn () => Document::query()->distinct()->pluck('group')->filter()->all()),
                Fields::locale(),
                TextInput::make('number')->label('Номер'),
                DatePicker::make('date')->label('Дата')->native(false),
                FileUpload::make('file')->label('Файл')->disk('public')->directory('documents')->preserveFilenames()->maxSize(51200),
                TextInput::make('url')->label('Або посилання')->url(),
                Textarea::make('description')->label('Опис')->rows(2)->columnSpanFull(),
                TextInput::make('sort')->label('Порядок')->numeric()->default(0),
                Toggle::make('is_published')->label('Опубліковано')->default(true),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('date', 'desc')->columns([
            TextColumn::make('title')->label('Назва')->searchable()->wrap()->limit(120),
            TextColumn::make('group')->label('Розділ')->badge()->sortable(),
            TextColumn::make('number')->label('№'),
            TextColumn::make('date')->label('Дата')->date('d.m.Y')->sortable(),
            ToggleColumn::make('is_published')->label('Опубл.'),
        ])->filters([
            SelectFilter::make('group')->label('Розділ')->options(fn () => Document::query()->distinct()->pluck('group', 'group')->filter()->all()),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocuments::route('/'),
            'create' => Pages\CreateDocument::route('/create'),
            'edit' => Pages\EditDocument::route('/{record}/edit'),
        ];
    }
}
