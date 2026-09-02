<?php

namespace Database\Seeders\Demo;

use App\Models\Order;
use App\Models\Review;
use App\Support\Demo\DemoContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ~7,000 reviews ONLY on domain-eligible orders (completed/settled/closed),
 * one per order (DB unique), rating distribution 70/22/6/1.5/0.5,
 * dimension ratings = Category.config.review_dimensions (1..5).
 * Partner rating recompute mirrors ReviewService::recomputePartnerRating
 * exactly (published only, COUNT+AVG) â€” bulk version for performance.
 */
class DemoReviewSeeder extends Seeder
{
    private const COMMENTS_POSITIVE = [
        'Teknisi datang sesuai jadwal dan pengerjaan rapi.',
        'Komunikasi cepat, hasil sesuai penawaran.',
        'Sangat profesional, alat lengkap, hasil memuaskan.',
        'Recommended! Pengerjaan cepat dan hasil bersih.',
        'Admin fast respon, teknisi ramah, harga sesuai.',
        'Kerja bagus, area kerja dibersihkan setelah selesai.',
        'Sudah langganan ke-3 kali, tetap konsisten bagus.',
        'Hasil melebihi ekspektasi, terima kasih.',
        'Datang tepat waktu, penjelasan detail sebelum kerja.',
        'Pengerjaan aman dan hati-hati, tidak ada barang rusak.',
    ];

    private const COMMENTS_NEUTRAL = [
        'Pengerjaan bagus, tetapi datang sedikit terlambat.',
        'Hasil cukup baik, komunikasi bisa lebih responsif.',
        'Overall oke, beberapa detail perlu diperbaiki ulang.',
        'Sesuai harga, tidak spesial tapi tidak mengecewakan.',
        'Pengerjaan selesai, hanya ada penyesuaian jadwal.',
    ];

    private const COMMENTS_NEGATIVE = [
        'Datang sangat terlambat tanpa konfirmasi.',
        'Hasil kurang rapi, perlu perbaikan ulang.',
        'Komunikasi sulit, progress tidak jelas.',
        'Pengerjaan tergesa-gesa, ada bagian terlewat.',
    ];

    public function run(DemoContext $ctx, int $reviews, array $customerIds): int
    {
        $existing = (int) DB::table('reviews')->where('is_demo', true)->count();
        $toCreate = max(0, $reviews - $existing);
        if ($toCreate === 0) {
            return 0;
        }

        $bar = $this->command?->getOutput()->createProgressBar($toCreate);
        $bar?->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $bar?->setMessage('Reviews');
        $bar?->start();

        // Eligible orders = completed/settled/closed, without a review yet
        $eligibleIds = DB::table('orders')
            ->whereIn('status', ['completed', 'settled', 'closed'])
            ->whereNotIn('id', DB::table('reviews')->select('order_id'))
            ->inRandomOrder()
            ->limit($toCreate)
            ->pluck('id')
            ->all();

        // Reviews are 1-per-order (unique FK). When the requested count exceeds
        // eligible orders, create HISTORICAL completed demo orders (paid via
        // payment_transactions only â€” no ledger rows, matching the real
        // transient "completed, not yet settled" state) to back the reviews.
        $backfillCreated = 0;
        if (count($eligibleIds) < $toCreate) {
            $backfillCreated = $this->createBackfillOrders($ctx, $toCreate - count($eligibleIds));

            $eligibleIds = DB::table('orders')
                ->whereIn('status', ['completed', 'settled', 'closed'])
                ->whereNotIn('id', DB::table('reviews')->select('order_id'))
                ->inRandomOrder()
                ->limit($toCreate)
                ->pluck('id')
                ->all();
        }

        $orderInfo = DB::table('orders')
            ->whereIn('orders.id', $eligibleIds)
            ->join('services', 'services.id', '=', 'orders.service_id')
            ->get(['orders.id', 'orders.user_id', 'orders.partner_id', 'services.category_id']);

        $catDims = [];
        foreach ($ctx->categories as $category) {
            $catDims[$category['id']] = $category['config']['review_dimensions'] ?? ['quality', 'communication', 'value'];
        }

        $rows = [];
        $now = now();

        foreach ($orderInfo as $order) {
            if (count($rows) >= $toCreate) {
                break;
            }

            $overall = $this->weightedRating();
            $dims = $catDims[$order->category_id] ?? ['quality', 'communication', 'value'];
            $dimensionRatings = [];
            foreach ($dims as $dim) {
                // dimension ratings correlate with overall
                $drift = [-1, 0, 0, 0, 1][mt_rand(0, 4)];
                $dimensionRatings[$dim] = min(5, max(1, $overall + $drift));
            }

            $comment = $overall >= 4
                ? self::COMMENTS_POSITIVE[mt_rand(0, count(self::COMMENTS_POSITIVE) - 1)]
                : ($overall === 3
                    ? self::COMMENTS_NEUTRAL[mt_rand(0, count(self::COMMENTS_NEUTRAL) - 1)]
                    : self::COMMENTS_NEGATIVE[mt_rand(0, count(self::COMMENTS_NEGATIVE) - 1)]);

            $rows[] = [
                'order_id' => $order->id,
                'author_id' => $order->user_id,
                'partner_id' => $order->partner_id,
                'overall' => $overall,
                'dimension_ratings' => json_encode($dimensionRatings),
                'comment' => $comment,
                'partner_response' => mt_rand(1, 100) <= 45 ? $this->partnerResponse($overall) : null,
                'responded_at' => mt_rand(1, 100) <= 45 ? $now->copy()->subDays(mt_rand(0, 10)) : null,
                'status' => 'published',
                'is_demo' => true,
                'created_at' => $now->copy()->subDays(mt_rand(0, 120)),
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('reviews')->insertOrIgnore($chunk);
            $bar?->advance(count($chunk));
        }

        $bar?->finish();
        $this->command?->getOutput()->writeln('');

        $inserted = DB::table('reviews')->where('is_demo', true)->count() - $existing;

        $this->recomputePartnerRatingsBulk();

        return (int) max($inserted, 0);
    }

    private function weightedRating(): int
    {
        $roll = mt_rand(1, 10000);

        return match (true) {
            $roll <= 7000 => 5,
            $roll <= 9200 => 4,
            $roll <= 9800 => 3,
            $roll <= 9950 => 2,
            default => 1,
        };
    }

    /**
     * Historical completed orders backing the review FK (1 review = 1 order).
     * Money: payment_transactions (paid, sandbox) ONLY â€” no settlements, no
     * ledger postings, no balances. Same shape as a real order waiting for
     * settlement processing. Bulk inserted for speed.
     */
    private function createBackfillOrders(DemoContext $ctx, int $count): int
    {
        $services = DB::table('services')
            ->where('is_demo', true)->where('status', 'active')
            ->whereIn('price_model', ['fixed', 'per_unit', 'hourly', 'daily', 'starting_from', 'package'])
            ->inRandomOrder()
            ->limit(3000)
            ->get(['id', 'partner_id', 'title', 'fulfillment_type', 'delivery_mode', 'price_model', 'base_price', 'duration_minutes']);

        if ($services->isEmpty()) {
            return 0;
        }

        $customerIds = DB::table('users')->where('is_demo', true)
            ->where('email', 'like', 'customer%')->orderBy('id')->pluck('id')->all();
        if ($customerIds === []) {
            return 0;
        }

        // Marketplace skew: head providers (same ranking as DemoOrderSeeder â€”
        // ascending partner id â‰ˆ first-seen order) take the majority of jobs.
        $partnerIds = DB::table('partners')->where('is_demo', true)->orderBy('id')->pluck('id')->all();
        $weights = [];
        $head = max(1, (int) ceil(count($partnerIds) * 0.15));
        $mid = max($head + 1, (int) ceil(count($partnerIds) * 0.5));
        foreach ($partnerIds as $idx => $pid) {
            $weights[$pid] = $idx < $head ? 4.1 : ($idx < $mid ? 0.66 : 0.3);
        }
        $cumulative = [];
        $sum = 0;
        foreach ($weights as $pid => $w) {
            $sum += $w;
            $cumulative[$pid] = $sum;
        }
        $totalWeight = $sum;

        $byPartner = [];
        foreach ($services as $svc) {
            $byPartner[$svc->partner_id][] = $svc;
        }

        $orderRows = [];
        $itemRows = [];
        $txRows = [];
        $now = now();
        $startSeq = (int) (DB::table('orders')->max('id') ?? 0);

        for ($i = 0; $i < $count; $i++) {
            // Weighted partner pick
            $roll = mt_rand(1, (int) ($totalWeight * 100)) / 100;
            $partnerId = $partnerIds[0];
            foreach ($cumulative as $pid => $cum) {
                if ($roll <= $cum && isset($byPartner[$pid])) {
                    $partnerId = $pid;
                    break;
                }
            }

            $pool = $byPartner[$partnerId] ?? $services;
            $svc = $pool[$i % count($pool)];

            $customerId = $customerIds[$i % count($customerIds)];

            $qty = $svc->price_model === 'per_unit' ? mt_rand(1, 3) : 1;
            $total = (int) ($svc->base_price * $qty);
            $when = $now->copy()->subDays(mt_rand(20, 330))->subHours(mt_rand(0, 10));

            $code = 'JP-'.$when->format('ymd').'-D'.str_pad((string) ($startSeq + $i), 6, '0', STR_PAD_LEFT);

            $orderRows[] = [
                'code' => $code,
                'user_id' => $customerId,
                'partner_id' => $svc->partner_id,
                'type' => 'service',
                'status' => 'completed',
                'service_id' => $svc->id,
                'fulfillment_type' => $svc->fulfillment_type,
                'delivery_mode' => $svc->delivery_mode,
                'scheduled_at' => $when,
                'duration_minutes' => $svc->duration_minutes,
                'customer_note' => null,
                'pricing_snapshot' => json_encode([
                    'service_id' => $svc->id,
                    'price_model' => $svc->price_model,
                    'lines' => [['type' => 'base', 'name' => $svc->title, 'qty' => $qty, 'unit_price' => $svc->base_price, 'amount' => $total]],
                    'subtotal' => ['amount' => $total],
                    'emergency_surcharge' => ['amount' => 0],
                    'total' => ['amount' => $total],
                    'currency' => 'IDR',
                    'demo_backfill' => true,
                ]),
                'subtotal' => $total,
                'emergency_surcharge' => 0,
                'total' => $total,
                'is_emergency' => false,
                'paid_at' => $when,
                'completed_at' => $when->copy()->addHours(2),
                'is_demo' => true,
                'created_at' => $when,
                'updated_at' => $when,
            ];

            $itemRows[] = [
                'type' => 'base',
                'name' => $svc->title,
                'qty' => $qty,
                'unit_price' => $svc->base_price,
                'amount' => $total,
                'ref_id' => $svc->id,
                'unit_label' => null,
                'created_at' => $when,
                'updated_at' => $when,
            ];

            $txRows[] = [
                'gateway' => 'sandbox',
                'gateway_ref' => 'SBX-DEMO-'.str_pad((string) ($startSeq + $i), 10, '0', STR_PAD_LEFT),
                'amount' => $total,
                'status' => 'paid',
                'payment_method' => 'sandbox_qris',
                'paid_at' => $when,
                'created_at' => $when,
                'updated_at' => $when,
            ];
        }

        // Insert orders, then map items+tx to order ids positionally
        foreach (array_chunk($orderRows, 500) as $chunk) {
            DB::table('orders')->insert($chunk);
        }

        $orderIds = DB::table('orders')->where('is_demo', true)->orderByDesc('id')->limit($count)->pluck('id')->all();
        $orderIds = array_reverse($orderIds);

        foreach ($itemRows as $i => $row) {
            $itemRows[$i]['order_id'] = $orderIds[$i];
        }
        foreach ($txRows as $i => $row) {
            $txRows[$i]['order_id'] = $orderIds[$i];
        }

        foreach (array_chunk($itemRows, 500) as $chunk) {
            DB::table('order_items')->insert($chunk);
        }
        foreach (array_chunk($txRows, 500) as $chunk) {
            DB::table('payment_transactions')->insert($chunk);
        }

        // Condensed, machine-consistent status history (bulk)
        $history = [];
        foreach ($orderIds as $orderId) {
            $when = $now->copy()->subDays(mt_rand(20, 330));
            foreach (['draft', 'pending_payment', 'paid', 'searching_provider', 'accepted', 'working', 'awaiting_customer_confirmation', 'completed'] as $status) {
                $history[] = [
                    'order_id' => $orderId,
                    'from_status' => null,
                    'to_status' => $status,
                    'actor_id' => null,
                    'actor_type' => 'system',
                    'reason' => 'Demo historical progression',
                    'metadata' => null,
                    'created_at' => $when,
                ];
            }
        }
        foreach (array_chunk($history, 500) as $chunk) {
            DB::table('order_status_history')->insert($chunk);
        }

        return $count;
    }

    private function partnerResponse(int $rating): string
    {
        return $rating >= 4
            ? ['Terima kasih atas kepercayaannya!', 'Senang bisa membantu, ditunggu orderan berikutnya.', 'Terima kasih, akan selalu jaga kualitas.'][mt_rand(0, 2)]
            : 'Mohon maaf atas ketidaknyamanannya, tim kami akan menindaklanjuti perbaikan.';
    }

    /**
     * Bulk rating recompute â€” identical formula to ReviewService:
     * rating_count = COUNT(published), rating_avg = AVG(overall).
     */
    private function recomputePartnerRatingsBulk(): void
    {
        DB::statement('
            UPDATE partners p
            LEFT JOIN (
                SELECT partner_id, COUNT(*) AS c, ROUND(AVG(overall), 2) AS avg_rating
                FROM reviews
                WHERE status = "published"
                GROUP BY partner_id
            ) r ON r.partner_id = p.id
            SET p.rating_count = COALESCE(r.c, 0),
                p.rating_avg = COALESCE(r.avg_rating, 0)
            WHERE p.is_demo = 1
        ');

        // completed_jobs = settled orders count
        DB::statement('
            UPDATE partners p
            LEFT JOIN (
                SELECT partner_id, COUNT(*) AS c
                FROM orders
                WHERE status IN ("completed", "settled", "closed")
                GROUP BY partner_id
            ) o ON o.partner_id = p.id
            SET p.completed_jobs = COALESCE(o.c, 0)
            WHERE p.is_demo = 1
        ');
    }
}
