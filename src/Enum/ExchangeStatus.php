<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;

enum ExchangeStatus: string implements TranslatableInterface
{
    use LabelledEnumTrait;

    case Pending = 'pending';
    case Accepted = 'accepted';
    case Refused = 'refused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Accepted => 'Accepté',
            self::Refused => 'Refusé',
            self::Completed => 'Terminé',
            self::Cancelled => 'Annulé',
        };
    }

    /** Couleur du badge (classe CSS badge--xxx). */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'ochre',
            self::Accepted => 'green',
            self::Refused, self::Cancelled => 'danger',
            self::Completed => 'slate',
        };
    }

    /**
     * Un échange est « ouvert » tant qu'il n'a été ni refusé, ni annulé, ni terminé :
     * on peut encore y envoyer des messages.
     */
    public function isOpen(): bool
    {
        return self::Pending === $this || self::Accepted === $this;
    }
}
