<?php

namespace App\Domain\Sales\Enums;

enum CouponType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
}
