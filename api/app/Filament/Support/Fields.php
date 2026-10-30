<?php

namespace App\Filament\Support;

use App\Models\Site;
use App\Support\Translit;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/** Спільні поля форм адмінки */
class Fields
{
    private static function isUrl($v): bool
    {
        return is_string($v) && preg_match('~^https?://~i', $v);
    }

    /**
     * Одне зображення. Імпортовані зі старого сайту картинки зберігаються як зовнішні URL —
     * тоді показуємо поле URL; якщо його очистити й зберегти, з'явиться завантаження файлу.
     * Набір полів вибираємо за збереженим записом (а не станом форми): два поля
     * з одним шляхом стану конфліктують, бо FileUpload перетворює рядок на масив.
     */
    public static function image(string $name, string $label, string $dir): array
    {
        return [Group::make(fn (?Model $record) => self::isUrl($record?->getAttribute($name))
            ? [
                TextInput::make($name)
                    ->label($label.' (зовнішнє посилання)')
                    ->url()
                    ->live(onBlur: true)
                    ->helperText('Зображення зі старого сайту. Щоб завантажити власний файл, очистіть поле та збережіть.'),
                // прев’ю поруч з адресою, щоб одразу бачити, що це за картинка
                Placeholder::make($name.'_preview')->hiddenLabel()
                    ->content(fn (Get $get) => self::isUrl($get($name))
                        ? new HtmlString('<a href="'.e($get($name)).'" target="_blank" rel="noopener"><img src="'.e($get($name)).'" alt="" loading="lazy" style="display:block;width:100%;max-height:260px;object-fit:cover;border-radius:12px;border:1px solid rgba(0,0,0,.08)"></a>')
                        : ''),
            ]
            : [
                FileUpload::make($name)
                    ->label($label)
                    ->image()
                    ->imageEditor()
                    ->disk('public')
                    ->directory($dir)
                    ->maxSize(8192),
            ])->columnSpanFull()];
    }

    /** Галерея: або список зовнішніх URL, або завантажені файли */
    public static function gallery(string $name, string $dir): array
    {
        $thumbs = fn (Get $get) => new HtmlString('<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(88px,1fr));gap:8px">'
            .collect((array) $get($name))->filter(fn ($v) => self::isUrl($v))->take(30)
                ->map(fn ($v) => '<a href="'.e($v).'" target="_blank" rel="noopener"><img src="'.e($v).'" alt="" loading="lazy" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:10px"></a>')->implode('')
            .'</div>');
        $hasUrls = fn (?Model $record) => collect((array) $record?->getAttribute($name))->contains(fn ($v) => self::isUrl($v));

        return [Group::make(fn (?Model $record) => $hasUrls($record)
            ? [
                TagsInput::make($name)
                    ->label('Галерея (зовнішні посилання)')
                    ->live()
                    ->helperText('Посилання на фото зі старого сайту. Видаліть усі й збережіть, щоб завантажувати файли.'),
                Placeholder::make($name.'_preview')->hiddenLabel()->content($thumbs),
            ]
            : [
                FileUpload::make($name)
                    ->label('Галерея')
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->panelLayout('grid')
                    ->disk('public')
                    ->directory($dir)
                    ->maxSize(8192)
                    ->maxFiles(30),
            ])->columnSpanFull()];
    }

    /** Поле slug, що автоматично заповнюється з назви при створенні */
    public static function titleWithSlug(string $title = 'name', string $label = 'Назва', bool $uniqueInLocale = true): array
    {
        $slug = TextInput::make('slug')
            ->label('Адреса (slug)')
            ->helperText('Частина адреси сторінки. Латиниця, цифри та дефіс.')
            ->required()
            ->maxLength(255)
            ->alphaDash();

        return [
            TextInput::make($title)
                ->label($label)
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (string $operation, $state, Set $set) {
                    if ($operation === 'create') {
                        $set('slug', Translit::slug((string) $state));
                    }
                }),
            $uniqueInLocale ? self::uniqueInLocale($slug) : $slug,
        ];
    }

    public static function locale(): Select
    {
        return Select::make('locale')->label('Мова')->options(Site::LOCALES)->default('uk')->required()->native(false);
    }

    /** Унікальність slug у межах мови */
    public static function uniqueInLocale(TextInput $field): TextInput
    {
        return $field->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, Get $get) => $rule->where('locale', $get('locale') ?: 'uk'));
    }

    public static function seo(): Section
    {
        return Section::make('SEO')
            ->description('Якщо не заповнено, використовується назва та короткий опис.')
            ->collapsed()
            ->schema([
                TextInput::make('meta_title')->label('Meta title')->maxLength(255),
                Textarea::make('meta_description')->label('Meta description')->rows(2)->maxLength(500),
            ]);
    }
}
