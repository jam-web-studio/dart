<?php

namespace App\Models;

/** Мови сайту: українська без префікса, англійська з /en */
class Site
{
    public const LOCALES = ['uk' => 'Українська', 'en' => 'English'];

    public static function prefix(?string $locale): string
    {
        return $locale && $locale !== 'uk' ? '/'.$locale : '';
    }
}
