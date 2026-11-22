<?php

namespace App\Events;

use App\Models\VisitReport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportReviewed
{
    use Dispatchable, SerializesModels;

    public function __construct(public VisitReport $report) {}
}
