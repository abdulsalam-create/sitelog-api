<?php

namespace App\Events;

use App\Models\Fault;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FaultRaised
{
    use Dispatchable, SerializesModels;

    public function __construct(public Fault $fault) {}
}
