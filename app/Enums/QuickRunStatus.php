<?php

namespace App\Enums;

enum QuickRunStatus: string
{
    case Open = 'open';
    case Locked = 'locked';
    case Closed = 'closed';
}
