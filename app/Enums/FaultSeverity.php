<?php

namespace App\Enums;

enum FaultSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function slaHours(): int
    {
        return match ($this) {
            self::Critical => 4,
            self::High => 24,
            self::Medium => 72,
            self::Low => 168,
        };
    }
}
