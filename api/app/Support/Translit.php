<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Українська латиниця за паспортною транслітерацією (постанова КМУ № 55, 2010) */
class Translit
{
    private const MAP = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'h', 'ґ' => 'g', 'д' => 'd', 'е' => 'e', 'є' => 'ie', 'ж' => 'zh', 'з' => 'z',
        'и' => 'y', 'і' => 'i', 'ї' => 'i', 'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p',
        'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch',
        'ю' => 'iu', 'я' => 'ia', 'ь' => '', '’' => '', "'" => '', 'ʼ' => '', 'ы' => 'y', 'э' => 'e', 'ё' => 'io', 'ъ' => '',
    ];

    // на початку слова
    private const START = ['є' => 'ye', 'ї' => 'yi', 'й' => 'y', 'ю' => 'yu', 'я' => 'ya'];

    /** «Київ» → «kyiv» */
    public static function slug(string $text): string
    {
        $text = mb_strtolower($text);
        $text = str_replace('зг', 'zgh', $text);
        $text = preg_replace_callback('/(^|[^\p{L}’\'ʼ])([єїйюя])/u', fn ($m) => $m[1].self::START[$m[2]], $text);
        $text = strtr($text, self::MAP);

        return Str::slug($text);
    }

    /** «Згурівка» → «Zghurivka» (назви без перекладу на англійській версії) */
    public static function latin(string $s): string
    {
        $s = str_replace(['Зг', 'зг'], ['Zgh', 'zgh'], $s);

        return preg_replace_callback('/[\p{Cyrillic}’ʼ\']+/u', function ($m) {
            $out = '';
            $chars = mb_str_split($m[0]);
            foreach ($chars as $i => $ch) {
                $low = mb_strtolower($ch);
                $lat = ($i === 0 ? self::START[$low] ?? null : null) ?? self::MAP[$low] ?? $ch;
                $out .= $ch !== $low ? ucfirst($lat) : $lat;
            }

            return $out;
        }, $s);
    }
}
