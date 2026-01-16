<?php

namespace App\Enums;

enum FulfillmentType: string
{
    case Pickup = 'pickup';
    case Delivery = 'delivery';
}
