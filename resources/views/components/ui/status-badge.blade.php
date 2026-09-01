@props(['status', 'type' => 'order'])
@php
$labels = trans('status');
$label = $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
$map = [
    'draft' => 'slate', 'pending_payment' => 'amber', 'paid' => 'teal', 'searching_provider' => 'indigo',
    'offered' => 'amber', 'accepted' => 'teal', 'assigned' => 'teal', 'on_the_way' => 'indigo',
    'arrived' => 'indigo', 'checked_in' => 'teal', 'working' => 'teal',
    'awaiting_customer_confirmation' => 'amber', 'completed' => 'green', 'settlement_pending' => 'slate',
    'settled' => 'slate', 'closed' => 'slate', 'cancelled' => 'rose', 'expired' => 'rose',
    'failed' => 'rose', 'disputed' => 'rose', 'rework_required' => 'amber', 'refund_pending' => 'amber',
    'partially_refunded' => 'amber', 'refunded' => 'rose',
    // project / proposal / rfq statuses
    'open' => 'teal', 'in_progress' => 'indigo', 'awarded' => 'green', 'done' => 'green',
    'submitted' => 'indigo', 'shortlisted' => 'teal', 'rejected' => 'rose', 'withdrawn' => 'slate',
    'pending' => 'amber', 'approved' => 'green', 'published' => 'green', 'closed_won' => 'green',
    'funded' => 'teal', 'released' => 'green', 'active' => 'green', 'suspended' => 'rose', 'paused' => 'amber',
];
$tone = $map[$status] ?? 'slate';
@endphp
<x-ui.badge :tone="$tone">{{ $label }}</x-ui.badge>
