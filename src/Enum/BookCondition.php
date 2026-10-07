<?php

namespace App\Enum;

enum BookCondition: string
{
    case New = 'new';
    case VeryGood = 'very_good';
    case Good = 'good';
    case Acceptable = 'acceptable';
    case Poor = 'poor';
}
