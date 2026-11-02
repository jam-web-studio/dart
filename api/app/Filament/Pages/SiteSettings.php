<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
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

    private const HTML = [
        'layout_header' => 'Шапка (меню)',
        'layout_footer' => 'Підвал',
        'layout_accessibility' => 'Віджет доступності',
        'template_post' => 'Шаблон новини (/blog/…)',
        'template_order' => 'Шаблон наказу (/orders/…)',
        'template_report' => 'Шаблон звіту (/reports/…)',
    ];

    public function mount(): void
    {
        $keys = [...array_keys(Setting::DEFAULTS), ...array_keys(self::HTML)];
        $this->form->fill(collect($keys)->mapWithKeys(fn ($k) => [$k => Setting::get($k)])->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Відомство')->columns(2)->schema([
                TextInput::make('site_name')->label('Назва (укр.)'),
                TextInput::make('site_name_en')->label('Назва (англ.)'),
                Textarea::make('meta_description')->label('Опис сайту для пошукових систем')->rows(2)->columnSpanFull(),
                TextInput::make('og_image')->label('Картинка для соцмереж (URL)')->url()->columnSpanFull(),
            ]),
            Section::make('Шапка, підвал і шаблони')
                ->description('HTML старого сайту (Webflow). У шаблонах записів: {{title}}, {{date|d.m.Y}}, {{cover}}, {{{body}}}, {{{gallery}}}, {{number}}, {{pdf}}.')
                ->collapsed()
                ->schema(collect(self::HTML)->map(fn ($label, $key) => CodeEditor::make($key)->label($label)->language(Language::Html))->values()->all()),
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
