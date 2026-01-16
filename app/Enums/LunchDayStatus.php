<?php

namespace App\Enums;

enum LunchDayStatus: string
{
    case Open = 'open';
    case Locked = 'locked';
    case Closed = 'closed';
}
