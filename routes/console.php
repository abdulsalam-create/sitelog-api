<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('sla:notify-breaches')->hourly();
