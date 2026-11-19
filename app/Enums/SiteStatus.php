<?php

namespace App\Enums;

enum SiteStatus: string
{
    case Active = 'active';
    case UnderMaintenance = 'under_maintenance';
    case Decommissioned = 'decommissioned';
}
