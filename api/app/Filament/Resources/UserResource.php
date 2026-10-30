<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'Користувач';

    protected static ?string $pluralModelLabel = 'Користувачі';

    protected static string|UnitEnum|null $navigationGroup = 'Система';

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->label('Ім’я')->required(),
                TextInput::make('email')->label('Email')->email()->required()->unique(ignoreRecord: true),
                Select::make('role')->label('Роль')->options(User::ROLES)->required()->default('editor'),
                TextInput::make('password')->label('Пароль')->password()->revealable()
                    ->required(fn ($operation) => $operation === 'create')
                    ->dehydrated(fn ($state) => filled($state))->minLength(8),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Ім’я')->searchable(),
            TextColumn::make('email')->label('Email')->searchable(),
            TextColumn::make('role')->label('Роль')->badge()->formatStateUsing(fn ($state) => User::ROLES[$state] ?? $state),
            TextColumn::make('created_at')->label('Створено')->date('d.m.Y'),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
