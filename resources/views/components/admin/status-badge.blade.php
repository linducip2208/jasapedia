@props(['status'])
@php
$labels = trans('status');
$label = $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
$map = [
    'draft' => 'secondary', 'pending_payment' => 'warning', 'paid' => 'brand', 'searching_provider' => 'info',
    'offered' => 'warning', 'accepted' => 'brand', 'assigned' => 'brand', 'on_the_way' => 'info',
    'arrived' => 'info', 'checked_in' => 'brand', 'working' => 'brand',
    'awaiting_customer_confirmation' => 'warning', 'completed' => 'success', 'settlement_pending' => 'secondary',
    'settled' => 'secondary', 'closed' => 'secondary', 'cancelled' => 'danger', 'expired' => 'danger',
    'failed' => 'danger', 'disputed' => 'danger', 'rework_required' => 'warning', 'refund_pending' => 'warning',
    'partially_refunded' => 'warning', 'refunded' => 'danger',
    // project / proposal / rfq statuses
    'open' => 'brand', 'in_progress' => 'info', 'awarded' => 'success', 'done' => 'success',
    'submitted' => 'info', 'shortlisted' => 'brand', 'rejected' => 'danger', 'withdrawn' => 'secondary',
    'pending' => 'warning', 'approved' => 'success', 'published' => 'success', 'closed_won' => 'success',
    'funded' => 'brand', 'released' => 'success', 'active' => 'success', 'suspended' => 'danger', 'paused' => 'warning',
    // withdrawal / dispute / kyc
    'requested' => 'warning', 'under_review' => 'warning', 'processing' => 'info',
    'resolved' => 'success', 'unverified' => 'secondary', 'verified' => 'success', 'completed_refund' => 'success',
];
$tone = $map[$status] ?? 'secondary';
$class = $tone === 'brand' ? 'badge-brand' : 'text-bg-'.$tone;
@endphp
<span class="badge rounded-pill {{ $class }}">{{ $label }}</span>
