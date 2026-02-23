<?php

use Illuminate\Support\Facades\Schedule;

// Every day at 8:00 AM — check expiring leases
Schedule::command('rentflow:check-expiring-leases')->dailyAt('08:00');

// Every day at 9:00 AM — check overdue payments
Schedule::command('rentflow:check-overdue-payments')->dailyAt('09:00');
