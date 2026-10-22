<?php

namespace App\Enums;

enum InterviewType: string
{
    case ONLINE = 'online';
    case ON_SITE = 'on_site';
    case EXAM = 'exam';

    /**
     * Get all allowed types as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
