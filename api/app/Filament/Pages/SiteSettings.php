<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class SiteSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $title = 'Налаштування сайту';

    protected static string|UnitEnum|null $navigationGroup = 'Система';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(Setting::publicAll());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Відомство')->columns(2)->schema([
                TextInput::make('site_name')->label('Назва (укр.)'),
                TextInput::make('site_name_en')->label('Назва (англ.)'),
                TextInput::make('phone')->label('Телефон'),
                TextInput::make('email')->label('Email')->email(),
                TextInput::make('address')->label('Адреса (укр.)'),
                TextInput::make('address_en')->label('Адреса (англ.)'),
                Textarea::make('footer_text')->label('Текст у підвалі')->rows(2)->columnSpanFull(),
            ]),
            Section::make('Головна')->schema([
                TextInput::make('hero_title')->label('Заголовок'),
                Textarea::make('hero_text')->label('Підзаголовок')->rows(2),
                TextInput::make('hero_image')->label('Фонове фото (URL)'),
            ]),
            Section::make('Соцмережі')->columns(2)->schema([
                TextInput::make('facebook')->label('Facebook')->url(),
                TextInput::make('instagram')->label('Instagram')->url(),
                TextInput::make('youtube')->label('YouTube')->url(),
                TextInput::make('telegram')->label('Telegram')->url(),
                TextInput::make('twitter')->label('X / Twitter')->url(),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])->id('form')->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label('Зберегти')->submit('save')])]),
        ]);
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Notification::make()->title('Збережено')->success()->send();
    }
}
