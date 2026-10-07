<?php

namespace App\Enum;

enum ExchangeStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Refused = 'refused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
