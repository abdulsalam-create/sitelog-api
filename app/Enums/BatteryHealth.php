<?php

namespace App\Enums;

enum BatteryHealth: string
{
    case Good = 'good';
    case Fair = 'fair';
    case Poor = 'poor';
}
