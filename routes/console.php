<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('rentflow:generate-payments')->monthlyOn(1, '01:00');

Schedule::command('rentflow:check-overdue-payments')->dailyAt('02:00');

Schedule::command('rentflow:check-expiring-leases')->dailyAt('03:00');
