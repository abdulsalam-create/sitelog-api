<?php

namespace App\Enums;

enum MaintenanceAction: string
{
    case Refilled = 'refilled';
    case Replaced = 'replaced';
    case Repaired = 'repaired';
    case Inspected = 'inspected';
}
