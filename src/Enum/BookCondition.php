<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;

enum BookCondition: string implements TranslatableInterface
{
    use LabelledEnumTrait;

    case New = 'new';
    case VeryGood = 'very_good';
    case Good = 'good';
    case Acceptable = 'acceptable';
    case Poor = 'poor';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Neuf',
            self::VeryGood => 'Très bon état',
            self::Good => 'Bon état',
            self::Acceptable => 'État correct',
            self::Poor => 'Abîmé',
        };
    }

    /** Note de 1 (abîmé) à 5 (neuf), utilisée pour la jauge d'état. */
    public function level(): int
    {
        return match ($this) {
            self::New => 5,
            self::VeryGood => 4,
            self::Good => 3,
            self::Acceptable => 2,
            self::Poor => 1,
        };
    }
}
