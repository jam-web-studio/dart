<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentResource\Pages;
use App\Filament\Support\Fields;
use App\Models\Document;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
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

/** Накази (/orders) і звіти щодо запитів на інформацію (/reports) */
class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowDown;

    protected static ?string $modelLabel = 'Документ';

    protected static ?string $pluralModelLabel = 'Накази і звіти';

    protected static string|UnitEnum|null $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                Select::make('kind')->label('Тип')->options(Document::KINDS)->default('order')->required()->native(false)->live(),
                Fields::locale(),
                Textarea::make('title')->label('Назва')->required()->rows(2)->columnSpanFull(),
                TextInput::make('slug')->label('Адреса (slug)')->required()->maxLength(255)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, Get $get) => $rule->where('kind', $get('kind') ?: 'order')->where('locale', $get('locale') ?: 'uk')),
                TextInput::make('number')->label('Номер наказу')->visible(fn (Get $get) => $get('kind') !== 'report'),
                DatePicker::make('date')->label('Дата')->native(false)->displayFormat('d.m.Y'),
                TextInput::make('meta.month')->label('Місяць звіту (1–12)')->numeric()->minValue(1)->maxValue(12)->visible(fn (Get $get) => $get('kind') === 'report'),
                TextInput::make('meta.year')->label('Рік звіту')->numeric()->visible(fn (Get $get) => $get('kind') === 'report'),
                FileUpload::make('file')->label('PDF')->disk('public')->directory('documents')->preserveFilenames()->acceptedFileTypes(['application/pdf'])->maxSize(51200)
                    ->visible(fn (Get $get) => $get('kind') !== 'report'),
                TextInput::make('url')->label('Або посилання на PDF')->url()->maxLength(2000)->visible(fn (Get $get) => $get('kind') !== 'report'),
                TextInput::make('sort')->label('Порядок')->numeric()->default(0),
                Toggle::make('is_published')->label('Опубліковано')->default(true),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('date', 'desc')->columns([
            TextColumn::make('title')->label('Назва')->searchable()->wrap()->limit(120),
            TextColumn::make('kind')->label('Тип')->badge()->formatStateUsing(fn ($state) => Document::KINDS[$state] ?? $state),
            TextColumn::make('number')->label('№')->searchable(),
            TextColumn::make('date')->label('Дата')->date('d.m.Y')->sortable(),
            ToggleColumn::make('is_published')->label('Опубл.'),
        ])->filters([
            SelectFilter::make('kind')->label('Тип')->options(Document::KINDS),
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
