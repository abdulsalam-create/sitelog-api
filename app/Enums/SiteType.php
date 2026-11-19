<?php

namespace App\Enums;

enum SiteType: string
{
    case Greenfield = 'greenfield';
    case Rooftop = 'rooftop';
    case Indoor = 'indoor';
}
