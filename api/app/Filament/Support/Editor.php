<?php

namespace App\Filament\Support;

use Filament\Forms\Components\RichEditor;

/**
 * Повноцінний текстовий редактор для всіх розділів адмінки:
 * заголовки H2–H4, списки, цитати, вирівнювання, посилання, таблиці й картинки (завантажуються в storage).
 */
class Editor
{
    public static function make(string $name, string $directory = 'content'): RichEditor
    {
        return RichEditor::make($name)
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'link', 'highlight'],
                ['paragraph', 'h2', 'h3', 'h4', 'lead', 'small'],
                ['alignStart', 'alignCenter', 'alignEnd'],
                ['bulletList', 'orderedList', 'blockquote', 'horizontalRule'],
                ['attachFiles', 'table', 'grid'],
                ['clearFormatting', 'undo', 'redo'],
            ])
            ->fileAttachmentsDisk('public')
            ->fileAttachmentsDirectory($directory)
            ->fileAttachmentsVisibility('public')
            ->fileAttachmentsAcceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
            ->fileAttachmentsMaxSize(8192);
    }
}
