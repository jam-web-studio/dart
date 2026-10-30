<?php

namespace App\Filament\Support;

use App\Models\Page;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/** Верхні кнопки сторінки редагування: «Подивитися на сайті», «Зберегти», «Видалити» */
trait RecordActions
{
    protected function getHeaderActions(): array
    {
        $url = self::publicUrl($this->getRecord());

        return array_values(array_filter([
            $url ? Action::make('view_on_site')->label('Подивитися на сайті')->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')->url($url)->openUrlInNewTab() : null,
            $this->getSaveFormAction()->formId('form'),
            DeleteAction::make(),
        ]));
    }

    public static function publicUrl(?Model $r): ?string
    {
        $path = match (true) {
            $r instanceof Post, $r instanceof Page => $r->url(),
            default => null,
        };

        return $path ? rtrim((string) config('app.url'), '/').$path : null;
    }
}
