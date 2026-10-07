<?php

namespace App\Enum;

enum AnnouncementStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
