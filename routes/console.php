<?php

use Illuminate\Support\Facades\Schedule;

// Order expiry: pending payments past window
Schedule::call(function () {
    app(\App\Domain\Order\OrderService::class)->expireStalePendingPayments(60);
})->everyTenMinutes()->name('orders-expire-pending-payments');

// Recurring services: generate + materialize occurrences
Schedule::call(function () {
    app(\App\Domain\Growth\RecurringService::class)->generateOccurrences(30);
    app(\App\Domain\Growth\RecurringService::class)->materializeDueOccurrences();
})->daily()->name('recurring-generate-and-materialize');

// Offers TTL cleanup: expire stale dispatch offers
Schedule::call(function () {
    \App\Models\Assignment::where('status', 'offered')
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->update(['status' => 'expired']);
})->everyFifteenMinutes()->name('dispatch-expire-offers');

// Additional charge requests: expire unanswered
Schedule::call(function () {
    \App\Models\AdditionalChargeRequest::where('status', 'pending')
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->update(['status' => 'expired']);
})->hourly()->name('acr-expire');

// Quotations: expire past validity
Schedule::call(function () {
    \App\Models\Quotation::where('status', 'sent')
        ->whereNotNull('valid_until')
        ->where('valid_until', '<', now())
        ->update(['status' => 'expired']);
})->daily()->name('quotations-expire');

Artisan::command('inspire', function () {
    $this->comment(\Illuminate\Foundation\Inspiring::quote());
})->purpose('Display an inspiring quote');
