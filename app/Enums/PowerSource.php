<?php

namespace App\Enums;

enum PowerSource: string
{
    case Grid = 'grid';
    case Generator = 'generator';
    case Hybrid = 'hybrid';
    case Solar = 'solar';
}
