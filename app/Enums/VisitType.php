<?php

namespace App\Enums;

enum VisitType: string
{
    case Routine = 'routine';
    case Corrective = 'corrective';
    case Emergency = 'emergency';
}
