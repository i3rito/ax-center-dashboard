<?php

namespace App\Support;

final class PlantConnection
{
    public const A = 'planta_a';
    public const B = 'planta_b';

    public const ALL = [self::A, self::B];

    public static function label(string $connection): string
    {
        return [
            self::A => 'Planta A',
            self::B => 'Planta B',
        ][$connection] ?? $connection;
    }
}
