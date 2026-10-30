<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BannerResource\Pages;
use App\Filament\Support\Fields;
use App\Models\Banner;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $modelLabel = 'Банер';

    protected static ?string $pluralModelLabel = 'Банери й посилання';

    protected static string|UnitEnum|null $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('title')->label('Назва')->required(),
                TextInput::make('url')->label('Посилання'),
                TextInput::make('place')->label('Місце')->default('home')->helperText('home — головна, partners — партнери в підвалі'),
                Fields::locale(),
                Textarea::make('text')->label('Текст')->rows(2)->columnSpanFull(),
                ...Fields::image('image', 'Зображення', 'banners'),
                TextInput::make('sort')->label('Порядок')->numeric()->default(0),
                Toggle::make('is_published')->label('Опубліковано')->default(true),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort')->reorderable('sort')->columns([
            ImageColumn::make('image')->label('')->getStateUsing(fn (Banner $record) => Banner::mediaUrl($record->image))->imageHeight(36),
            TextColumn::make('title')->label('Назва')->searchable(),
            TextColumn::make('place')->label('Місце')->badge(),
            TextColumn::make('locale')->label('Мова')->badge(),
            ToggleColumn::make('is_published')->label('Опубл.'),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
            'edit' => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}
