<?php

namespace App\Enums;

enum FaultCategory: string
{
    case Power = 'power';
    case Transmission = 'transmission';
    case Equipment = 'equipment';
    case Security = 'security';
    case Civil = 'civil';
}
