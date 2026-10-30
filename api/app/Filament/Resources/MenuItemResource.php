<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuItemResource\Pages;
use App\Filament\Support\Fields;
use App\Models\MenuItem;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class MenuItemResource extends Resource
{
    protected static ?string $model = MenuItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static ?string $modelLabel = 'Пункт меню';

    protected static ?string $pluralModelLabel = 'Меню';

    protected static string|UnitEnum|null $navigationGroup = 'Довідники';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('label')->label('Назва')->required(),
                TextInput::make('url')->label('Адреса')->required()->helperText('Внутрішня адреса (/news) або повне посилання.'),
                Select::make('menu')->label('Меню')->options(MenuItem::MENUS)->default('header')->required()->live(),
                Fields::locale()->live(),
                Select::make('parent_id')->label('Підпункт для')
                    ->options(fn (Get $get) => MenuItem::whereNull('parent_id')->where('menu', $get('menu'))->where('locale', $get('locale') ?: 'uk')->pluck('label', 'id')),
                TextInput::make('sort')->label('Порядок')->numeric()->default(0),
                Toggle::make('new_tab')->label('Відкривати в новій вкладці'),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort')->reorderable('sort')->columns([
            TextColumn::make('label')->label('Назва')->searchable()
                ->formatStateUsing(fn (MenuItem $record) => ($record->parent_id ? '— ' : '').$record->label),
            TextColumn::make('url')->label('Адреса')->limit(50),
            TextColumn::make('parent.label')->label('Підпункт для'),
            TextColumn::make('menu')->label('Меню')->formatStateUsing(fn ($state) => MenuItem::MENUS[$state] ?? $state)->badge(),
            TextColumn::make('locale')->label('Мова')->badge(),
        ])->filters([
            SelectFilter::make('menu')->label('Меню')->options(MenuItem::MENUS),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenuItems::route('/'),
            'create' => Pages\CreateMenuItem::route('/create'),
            'edit' => Pages\EditMenuItem::route('/{record}/edit'),
        ];
    }
}
