<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;

enum AnnouncementStatus: string implements TranslatableInterface
{
    use LabelledEnumTrait;

    case Available = 'available';
    case Reserved = 'reserved';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::Reserved => 'Réservé',
            self::Completed => 'Terminé',
            self::Cancelled => 'Annulé',
        };
    }

    /** Couleur du badge (classe CSS badge--xxx). */
    public function color(): string
    {
        return match ($this) {
            self::Available => 'green',
            self::Reserved => 'ochre',
            self::Completed => 'slate',
            self::Cancelled => 'danger',
        };
    }
}
