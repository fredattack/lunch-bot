<?php

namespace App\Enums;

enum ProposalStatus: string
{
    case Open = 'open';
    case Ordering = 'ordering';
    case Placed = 'placed';
    case Received = 'received';
    case Closed = 'closed';
}
