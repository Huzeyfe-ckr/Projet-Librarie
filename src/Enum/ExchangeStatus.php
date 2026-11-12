<?php

namespace App\Enum;

enum ExchangeStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Refused = 'refused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Un échange est « ouvert » tant qu'il n'a été ni refusé, ni annulé, ni terminé :
     * on peut encore y envoyer des messages.
     */
    public function isOpen(): bool
    {
        return self::Pending === $this || self::Accepted === $this;
    }
}
