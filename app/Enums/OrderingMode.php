<?php

namespace App\Enums;

enum OrderingMode: string
{
    case Individual = 'individual';
    case Shared = 'shared';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Commandes individuelles',
            self::Shared => 'Commande groupée',
        };
    }
}
