<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;

enum AnnouncementType: string implements TranslatableInterface
{
    use LabelledEnumTrait;

    case Donation = 'donation';
    case Sale = 'sale';

    public function label(): string
    {
        return match ($this) {
            self::Donation => 'Don',
            self::Sale => 'Vente',
        };
    }

    /** Couleur du badge (classe CSS badge--xxx). */
    public function color(): string
    {
        return match ($this) {
            self::Donation => 'green',
            self::Sale => 'ochre',
        };
    }
}
