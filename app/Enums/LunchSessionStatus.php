<?php

namespace App\Enums;

enum LunchSessionStatus: string
{
    case Open = 'open';
    case Locked = 'locked';
    case Closed = 'closed';
}
