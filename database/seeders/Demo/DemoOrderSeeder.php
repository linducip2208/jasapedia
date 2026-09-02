<?php

namespace Database\Seeders\Demo;

use App\Domain\Finance\WithdrawalService;
use App\Domain\Order\OrderService;
use App\Domain\Payment\Contracts\GatewayEvent;
use App\Domain\Payment\PaymentService;
use App\Models\Order;
use App\Models\PayoutDestination;
use App\Models\Service;
use App\Models\User;
use App\Support\Demo\DemoContext;
use App\Support\Demo\DemoOrderOrchestrator;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ~3,000 demo orders with the target status distribution.
 *
 * FINANCIAL SAFETY: only ~22% (paid + completed/settled + refunded) run through
 * the real domain pipeline (OrderService → PaymentService → SettlementService).
 * The remainder are early-state orders (draft/pending_payment/cancelled/expired)
 * that carry no ledger exposure — no fake balances are ever written.
 *
 * completed/settled 55% | in_progress 10% | scheduled 10% | paid 8%
 * pending_payment 7% | cancelled 5% | refunded 2% | other 3%
 */
class DemoOrderSeeder extends Seeder
{
    public function run(DemoContext $ctx, int $orders, array $partnerMap, array $serviceMeta, array $customerIds, array $fixedIds): array
    {
        if ($serviceMeta === [] || $customerIds === []) {
            $this->command?->warn('No demo services/customers; skipping orders.');

            return ['completedIds' => [], 'orderCount' => 0];
        }

        $orchestrator = app(DemoOrderOrchestrator::class);
        $payments = app(PaymentService::class);

        $existing = DB::table('orders')->where('is_demo', true)->count();
        $toCreate = max(0, $orders - $existing);

        $bar = $this->command?->getOutput()->createProgressBar($toCreate);
        $bar?->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $bar?->setMessage('Orders');
        $bar?->start();

        // Financial-complete subset: 55% settled + 2% refunded (+ withdrawals from settlement pool)
        $financeTarget = (int) ceil($toCreate * 0.57);
        $financeDone = 0;

        $servicesById = [];
        $customersById = [];
        $completedOrderIds = [];
        $reviewableIds = [];
        $seq = (int) (DB::table('orders')->max('id') ?? 0);
        $ranking = $this->popularityRanking($serviceMeta);
        // Address must belong to the ordering customer (OrderService findOrFail validates ownership)
        $addressIdsByCustomer = DB::table('customer_addresses')
            ->select('user_id', 'id')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($group) => $group->pluck('id')->all())
            ->all();

        // Preload service models in chunks lazily (memory-safe)
        $metaIndex = 0;

        for ($i = 0; $i < $toCreate; $i++) {
            $seq++;
            $meta = $serviceMeta[$this->pickServiceIndex($serviceMeta, $seq, $ranking)];
            $customerId = $customerIds[$seq % count($customerIds)];

            $customer = $customersById[$customerId] ??= User::find($customerId);
            if (! $customer) {
                continue;
            }

            $service = $servicesById[$meta['id']] ??= Service::with('partner')->find($meta['id']);
            if (! $service || ! $service->partner) {
                continue;
            }

            $bar?->setMessage('Orders');
            $bar?->advance();

            $roll = $i % 100;

            // ---- Finance-complete paths (~57%) ----
            if ($roll < 55 && $financeDone < $financeTarget) {
                $order = $this->createSettled($orchestrator, $customer, $service, $meta, $addressIdsByCustomer[$customerId] ?? [], $seq);
                if ($order) {
                    $financeDone++;
                    $completedOrderIds[] = $order->id;
                    $reviewableIds[] = $order->id;
                }

                continue;
            }

            if ($roll < 57 && $financeDone < $financeTarget) {
                $order = $this->createSettled($orchestrator, $customer, $service, $meta, $addressIdsByCustomer[$customerId] ?? [], $seq);
                if ($order) {
                    $financeDone++;
                    try {
                        $orchestrator->refundFully($order->fresh(), $customer, 'Demo: layanan tidak sesuai ekspektasi');
                    } catch (\Throwable) {
                        // refund eligibility edge — order stays settled
                    }
                }

                continue;
            }

            // ---- Non-financial / early states ----
            $this->createEarlyStateOrder($payments, $customer, $service, $meta, $addressIdsByCustomer[$customerId] ?? [], $seq, $roll);
        }

        $bar?->finish();
        $this->command?->getOutput()->writeln('');

        $this->simulateWithdrawals((int) (DB::table('users')->where('email', 'admin@jasapedia.test')->value('id') ?? 0));

        return ['completedIds' => $completedOrderIds, 'reviewableIds' => $reviewableIds, 'orderCount' => $toCreate];
    }

    /**
     * Realistic marketplace skew: a small head of providers takes most jobs.
     * Completed orders must land on the same partners repeatedly so the
     * existing level logic (completed_jobs ≥ 20/80/200 + rating thresholds)
     * computes Preferred/Top/Pro honestly from data.
     *
     * @return array<string> partner ids in "popularity" order (head = busiest)
     */
    private function popularityRanking(array $serviceMeta): array
    {
        $order = [];
        foreach ($serviceMeta as $meta) {
            $order[$meta['partner_id']] = true;
        }

        return array_keys($order);
    }

    /** Weighted pick toward the head of the popularity ranking. */
    private function pickServiceIndex(array $serviceMeta, int $seq, array $ranking): int
    {
        $count = count($serviceMeta);
        $head = max(1, (int) ceil($count * 0.15));
        $mid = max($head + 1, (int) ceil($count * 0.5));

        $r = mt_rand(1, 100);

        // 62% of demand goes to the top 15% providers (head), 23% mid, 15% tail
        if ($r <= 62) {
            $idx = ($seq * 7) % $head;
        } elseif ($r <= 85) {
            $idx = $head + (($seq * 5) % max(1, $mid - $head));
        } else {
            $idx = $mid + (($seq * 3) % max(1, $count - $mid));
        }

        // Convert ranking position → service meta owned by that partner
        $partnerId = $ranking[$idx % count($ranking)] ?? $ranking[0];

        // First service owned by that partner (linear scan, cached map outside)
        static $byPartner = null;
        $byPartner ??= $this->groupByPartner($serviceMeta);
        $list = $byPartner[$partnerId] ?? array_keys($serviceMeta);

        return $list[$seq % count($list)];
    }

    private function groupByPartner(array $serviceMeta): array
    {
        $map = [];
        foreach ($serviceMeta as $idx => $meta) {
            $map[$meta['partner_id']][] = $idx;
        }

        return $map;
    }

    private function createSettled(DemoOrderOrchestrator $orchestrator, User $customer, Service $service, array $meta, array $addressIds, int $seq): ?Order
    {
        $data = $this->orderData($service, $meta, $addressIds, $seq);

        try {
            return $orchestrator->createPaidAndSettled($customer, $service, $data, $seq);
        } catch (\Throwable $e) {
            $firstError = $e->getMessage();

            $dbStatus = DB::table('orders')->where('partner_id', $service->partner_id)->where('is_demo', true)->orderByDesc('id')->value('status');
            $txStatus = DB::table('payment_transactions')->orderByDesc('id')->value('status');

            \Log::warning("demo order DIAG service {$service->id}", [
                'err' => $firstError,
                'last_order_status' => $dbStatus,
                'last_tx_status' => $txStatus,
                'fulfillment' => $service->fulfillment_type,
                'data' => $data,
            ]);

            // Retry without schedule only works for services that don't require one.
            unset($data['scheduled_at']);

            if (in_array($service->fulfillment_type, ['appointment', 'per_unit', 'instant_booking'], true)) {
                \Log::warning("demo order skipped: service {$service->id} needs schedule", ['first' => $firstError]);

                return null;
            }

            try {
                return $orchestrator->createPaidAndSettled($customer, $service, $data, $seq);
            } catch (\Throwable $e2) {
                \Log::warning('demo order skipped: '.$e2->getMessage(), ['first' => $firstError, 'service' => $service->id]);

                return null;
            }
        }
    }

    private function createEarlyStateOrder($payments, User $customer, Service $service, array $meta, array $addressIds, int $seq, int $roll): void
    {
        // States: in_progress(10) scheduled(10) paid(8) pending_payment(7) cancelled(5) other(3)
        try {
            $order = app(OrderService::class)->createServiceOrder($customer, $service, $this->orderData($service, $meta, $addressIds, $seq));
            $order->forceFill(['is_demo' => true])->save();
        } catch (\Throwable) {
            return;
        }

        try {
            if ($roll < 68) {
                // in_progress 10%: paid then walk partway
                $this->payDemo($payments, $order, $seq);
                $states = ['offered', 'accepted', 'assigned'];
                $target = min(count($states), mt_rand(3, 8));
                foreach (array_slice(array_merge($states, ['on_the_way', 'arrived', 'checked_in', 'working']), 0, $target) as $state) {
                    try {
                        $order->transition($state, null, 'Demo progression');
                    } catch (\Throwable) {
                        break;
                    }
                }
            } elseif ($roll < 78) {
                // scheduled: draft + slot reserved, still pending_payment
            } elseif ($roll < 86) {
                // paid, searching provider
                $this->payDemo($payments, $order, $seq);
            } elseif ($roll < 93) {
                // pending_payment stays
            } elseif ($roll < 98) {
                // cancelled
                app(OrderService::class)->cancel($order, $customer, 'Demo: berubah pikiran');
            } else {
                // expired (other 3%)
                $order->transition('expired', null, 'Demo: window pembayaran habis');
            }
        } catch (\Throwable) {
            // leave in whatever valid state reached
        }
    }

    private function payDemo(PaymentService $payments, Order $order, int $seq): void
    {
        try {
            $tx = $payments->initialize($order);
            $event = new GatewayEvent(
                eventId: 'EVT-DEMO-'.$order->code.'-'.$seq,
                type: 'payment.paid',
                orderCode: $order->code,
                gatewayRef: $tx->gateway_ref,
                status: 'paid',
                amountIdr: (int) $order->total,
                raw: ['order_code' => $order->code, 'gateway_ref' => $tx->gateway_ref, 'amount' => (int) $order->total, 'status' => 'paid', 'method' => 'sandbox_qris', 'demo' => true],
            );
            $payments->handleWebhook('sandbox', $event);
        } catch (\Throwable) {
            // payment race — acceptable in early-state demo orders
        }
    }

    private function orderData(Service $service, array $meta, array $addressIds, int $seq): array
    {
        $data = [
            'customer_note' => $this->note($seq),
        ];

        if ($service->delivery_mode !== 'remote' && $addressIds !== []) {
            $data['address_id'] = $addressIds[$seq % count($addressIds)];
        }

        if (in_array($service->fulfillment_type, ['appointment', 'per_unit', 'instant_booking'], true)) {
            $scheduled = $this->scheduleWithinPartnerWindow($service, $seq);
            if ($scheduled === null) {
                return ['customer_note' => $this->note($seq)]; // no schedule → non-scheduled fulfillment only
            }
            $data['scheduled_at'] = $scheduled;
        }

        if ($meta['price_model'] === 'per_unit') {
            $data['quantity'] = mt_rand(1, min(4, $service->max_quantity ?? 4));
        }

        if ($service->price_model === 'package') {
            $pkg = DB::table('service_packages')->where('service_id', $service->id)->orderBy('sort')->first();
            if ($pkg) {
                $data['package_id'] = $pkg->id;
            }
        }

        return $data;
    }

    /**
     * A future weekday slot that provably fits THIS partner's schedule
     * (demo: Mon–Sat 08:00–15:00/17:00, Sunday only for some), respecting
     * the service duration. Returns null when the job cannot fit any window.
     */
    private function scheduleWithinPartnerWindow(Service $service, int $seq): ?CarbonInterface
    {
        $duration = (int) ($service->duration_minutes ?? 60);

        $days = DB::table('partner_schedules')
            ->where('partner_id', $service->partner_id)
            ->orderBy('day_of_week')
            ->get(['day_of_week', 'start_time', 'end_time']);

        if ($days->isEmpty()) {
            return null;
        }

        // Longest usable window across working days
        $latestEnd = $days->max(fn ($d) => strtotime((string) $d->end_time));
        $availableMinutes = max(0, (int) (($latestEnd - strtotime('08:00:00')) / 60));

        if ($duration > $availableMinutes) {
            return null; // job cannot fit — caller degrades to non-scheduled path
        }

        $workingDays = $days->pluck('day_of_week')->all();

        $date = now()->addDays(1 + ($seq % 17));
        for ($i = 0; $i < 10 && ! in_array($date->dayOfWeek, $workingDays, true); $i++) {
            $date = $date->addDay();
        }

        if (! in_array($date->dayOfWeek, $workingDays, true)) {
            return null;
        }

        $slack = max(0, $availableMinutes - $duration);
        $offsetMinutes = mt_rand(0, min($slack, 240));

        return $date->copy()->setTime(8, 0)->addMinutes($offsetMinutes);
    }

    private function note(int $seq): string
    {
        $notes = [
            'Mohon konfirmasi jadwal sebelum datang.',
            'Titip kunci ke security bila saya tidak ada.',
            'Ada hewan peliharaan di rumah, mohon maklum.',
            'Akses parkir dari belakang.',
            'Mohon kerjakan dengan rapi.',
            'Bisa dihubungi WhatsApp bila tiba di lokasi.',
            'Unit di lantai 2, lift tersedia.',
            '',
        ];

        return $notes[$seq % count($notes)];
    }

    /** Partner withdrawals from real settled balances — capped, race-safe. */
    private function simulateWithdrawals(int $adminId): void
    {
        if ($adminId <= 0) {
            $this->command?->warn('Admin user not found; skipping withdrawal simulation.');

            return;
        }

        $admin = User::find($adminId);
        if (! $admin) {
            return;
        }

        $svc = app(WithdrawalService::class);

        $destinations = PayoutDestination::whereNotNull('verified_at')
            ->with('partner')
            ->inRandomOrder()
            ->limit(120)
            ->get();

        $created = 0;
        foreach ($destinations as $destination) {
            if ($created >= 60) {
                break;
            }

            try {
                $balance = $svc->availableBalance($destination->partner);
                if ($balance >= 500000) {
                    $svc->request($destination->partner, $destination, 500000, $admin);
                    $created++;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        $this->command?->getOutput()->writeln("   Withdrawals requested: {$created}");
    }
}
