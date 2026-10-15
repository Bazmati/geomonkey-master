<?php

namespace App\Enum;

enum ImagePosition: string
{
    case Start = 'start';
    case Middle = 'middle';
    case End = 'end';

    public function label(): string
    {
        return match ($this) {
            self::Start => 'Début d\'article',
            self::Middle => 'Milieu d\'article',
            self::End => 'Fin d\'article',
        };
    }
}