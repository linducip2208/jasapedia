<?php

namespace App\Support\Demo;

use App\Domain\Ledger\LedgerService;
use Illuminate\Support\Facades\DB;

/**
 * Surgical demo-data cleanup for --fresh-demo.
 * Deletes ONLY rows tagged is_demo (plus dependent rows via FK cascade),
 * reverses demo ledger transactions through the domain (append-only ledger,
 * corrections via reversing entries — never raw deletes of money rows),
 * then re-derives partner aggregates so real data stays honest.
 */
final class DemoDataWiper
{
    public static function wipe(): void
    {
        self::reverseDemoLedgerTransactions();

        foreach (self::statements() as $sql) {
            try {
                DB::statement($sql);
            } catch (\Throwable) {
                // Table may not exist in older installs — skip defensively
            }
        }

        // Note: public/demo media pool intentionally survives — it is a
        // deterministic shared pool (ensurePool is idempotent) and deleting it
        // would 404 every remaining demo cover until the next seed.

        // Re-derive aggregates for any surviving partners (post-demo hygiene)
        DB::statement('
            UPDATE partners p
            LEFT JOIN (
                SELECT partner_id, COUNT(*) AS c, ROUND(AVG(overall), 2) AS avg_rating
                FROM reviews WHERE status = "published" GROUP BY partner_id
            ) r ON r.partner_id = p.id
            SET p.rating_count = COALESCE(r.c, 0), p.rating_avg = COALESCE(r.avg_rating, 0)
        ');
        DB::statement('
            UPDATE partners p
            LEFT JOIN (
                SELECT partner_id, COUNT(*) AS c
                FROM orders WHERE status IN ("completed", "settled", "closed") GROUP BY partner_id
            ) o ON o.partner_id = p.id
            SET p.completed_jobs = COALESCE(o.c, 0)
        ');
    }

    /**
     * Demo money never gets deleted rows — every demo ledger transaction is
     * reversed with a full balancing mirror, keeping Σdebit == Σcredit.
     */
    private static function reverseDemoLedgerTransactions(): void
    {
        $ledger = app(LedgerService::class);

        $demoOrderIds = DB::table('orders')->where('is_demo', true)->pluck('id');
        $demoPartnerIds = DB::table('partners')->where('is_demo', true)->pluck('id');

        $ids = collect();

        if ($demoOrderIds->isNotEmpty()) {
            $ids = $ids->merge(
                DB::table('ledger_transactions')->where('reference_type', 'order')->whereIn('reference_id', $demoOrderIds)->pluck('id'),
            );

            $refundIds = DB::table('refunds')->whereIn('order_id', $demoOrderIds)->pluck('id');
            if ($refundIds->isNotEmpty()) {
                $ids = $ids->merge(
                    DB::table('ledger_transactions')->where('reference_type', 'refund')->whereIn('reference_id', $refundIds)->pluck('id'),
                );
            }
        }

        if ($demoPartnerIds->isNotEmpty()) {
            $withdrawalIds = DB::table('withdrawals')->whereIn('partner_id', $demoPartnerIds)->pluck('id');
            if ($withdrawalIds->isNotEmpty()) {
                $ids = $ids->merge(
                    DB::table('ledger_transactions')->where('reference_type', 'withdrawal')->whereIn('reference_id', $withdrawalIds)->pluck('id'),
                );
            }
        }

        foreach ($ids->unique() as $txId) {
            try {
                $ledger->reverse((int) $txId, 'Demo data cleanup (--fresh-demo)');
            } catch (\Throwable) {
                // already reversed — reversal is idempotent-guarded by domain
            }
        }
    }

    private static function statements(): array
    {
        return [
            // Order-adjacent children (non-FK references cleaned explicitly; FK cascades cover the rest)
            'DELETE FROM order_status_history WHERE order_id IN (SELECT id FROM (SELECT id FROM orders WHERE is_demo = 1) d)',
            'DELETE FROM commissions WHERE order_id IN (SELECT id FROM (SELECT id FROM orders WHERE is_demo = 1) d)',
            'DELETE FROM settlements WHERE order_id IN (SELECT id FROM (SELECT id FROM orders WHERE is_demo = 1) d)',
            'DELETE FROM refunds WHERE order_id IN (SELECT id FROM (SELECT id FROM orders WHERE is_demo = 1) d)',
            'DELETE FROM payment_transactions WHERE order_id IN (SELECT id FROM (SELECT id FROM orders WHERE is_demo = 1) d)',
            'DELETE FROM payment_webhook_events WHERE event_id LIKE "EVT-DEMO-%"',
            'DELETE FROM disputes WHERE order_id IN (SELECT id FROM (SELECT id FROM orders WHERE is_demo = 1) d)',
            'DELETE FROM warranty_claims WHERE order_id IN (SELECT id FROM (SELECT id FROM orders WHERE is_demo = 1) d)',
            'DELETE FROM booking_slots WHERE order_id IN (SELECT id FROM (SELECT id FROM orders WHERE is_demo = 1) d)',
            'DELETE FROM assignments WHERE order_id IN (SELECT id FROM (SELECT id FROM orders WHERE is_demo = 1) d)',
            'DELETE FROM work_logs WHERE order_id IN (SELECT id FROM (SELECT id FROM orders WHERE is_demo = 1) d)',
            'DELETE FROM order_items WHERE order_id IN (SELECT id FROM (SELECT id FROM orders WHERE is_demo = 1) d)',
            'DELETE FROM reviews WHERE is_demo = 1',
            'DELETE FROM orders WHERE is_demo = 1',

            // Deal flow
            'DELETE FROM milestone_deliverables WHERE milestone_id IN (SELECT id FROM (SELECT id FROM milestones WHERE contract_id IN (SELECT id FROM (SELECT id FROM contracts WHERE project_id IN (SELECT id FROM (SELECT id FROM projects WHERE is_demo = 1) p) pr) c) m) md)',
            'DELETE FROM milestones WHERE contract_id IN (SELECT id FROM (SELECT id FROM contracts WHERE project_id IN (SELECT id FROM (SELECT id FROM projects WHERE is_demo = 1) p) pr) c)',
            'DELETE FROM contracts WHERE project_id IN (SELECT id FROM (SELECT id FROM projects WHERE is_demo = 1) p)',
            'DELETE FROM proposals WHERE project_id IN (SELECT id FROM (SELECT id FROM projects WHERE is_demo = 1) p)',
            'DELETE FROM quotations WHERE rfq_id IN (SELECT id FROM (SELECT id FROM rfqs WHERE is_demo = 1) r)',
            'DELETE FROM rfqs WHERE is_demo = 1',
            'DELETE FROM projects WHERE is_demo = 1',

            // Corporate
            'DELETE FROM corporate_service_requests WHERE organization_id IN (SELECT id FROM (SELECT id FROM corporate_organizations WHERE is_demo = 1) o)',
            'DELETE FROM corporate_budgets WHERE organization_id IN (SELECT id FROM (SELECT id FROM corporate_organizations WHERE is_demo = 1) o)',
            'DELETE FROM corporate_approval_policies WHERE organization_id IN (SELECT id FROM (SELECT id FROM corporate_organizations WHERE is_demo = 1) o)',
            'DELETE FROM corporate_employees WHERE organization_id IN (SELECT id FROM (SELECT id FROM corporate_organizations WHERE is_demo = 1) o)',
            'DELETE FROM corporate_departments WHERE organization_id IN (SELECT id FROM (SELECT id FROM corporate_organizations WHERE is_demo = 1) o)',
            'DELETE FROM corporate_cost_centers WHERE organization_id IN (SELECT id FROM (SELECT id FROM corporate_organizations WHERE is_demo = 1) o)',
            'DELETE FROM corporate_branches WHERE organization_id IN (SELECT id FROM (SELECT id FROM corporate_organizations WHERE is_demo = 1) o)',
            'DELETE FROM corporate_organizations WHERE is_demo = 1',

            // Partner tree
            'DELETE FROM partner_skills WHERE partner_id IN (SELECT id FROM (SELECT id FROM partners WHERE is_demo = 1) p)',
            'DELETE FROM partner_service_areas WHERE partner_id IN (SELECT id FROM (SELECT id FROM partners WHERE is_demo = 1) p)',
            'DELETE FROM partner_schedules WHERE partner_id IN (SELECT id FROM (SELECT id FROM partners WHERE is_demo = 1) p)',
            'DELETE FROM partner_blocks WHERE partner_id IN (SELECT id FROM (SELECT id FROM partners WHERE is_demo = 1) p)',
            'DELETE FROM partner_documents WHERE partner_id IN (SELECT id FROM (SELECT id FROM partners WHERE is_demo = 1) p)',
            'DELETE FROM withdrawals WHERE partner_id IN (SELECT id FROM (SELECT id FROM partners WHERE is_demo = 1) p)',
            'DELETE FROM payout_destinations WHERE partner_id IN (SELECT id FROM (SELECT id FROM partners WHERE is_demo = 1) p)',
            'DELETE FROM kyc_submissions WHERE partner_id IN (SELECT id FROM (SELECT id FROM partners WHERE is_demo = 1) p)',
            'DELETE FROM partner_members WHERE organization_id IN (SELECT id FROM (SELECT id FROM partner_organizations WHERE partner_id IN (SELECT id FROM (SELECT id FROM partners WHERE is_demo = 1) p) po) pm)',
            'DELETE FROM partner_organizations WHERE partner_id IN (SELECT id FROM (SELECT id FROM partners WHERE is_demo = 1) p)',
            'DELETE FROM favorites WHERE service_id IN (SELECT id FROM (SELECT id FROM services WHERE is_demo = 1) s)',
            'DELETE FROM favorites WHERE partner_id IN (SELECT id FROM (SELECT id FROM partners WHERE is_demo = 1) p)',
            'DELETE FROM service_addons WHERE service_id IN (SELECT id FROM (SELECT id FROM services WHERE is_demo = 1) s)',
            'DELETE FROM service_packages WHERE service_id IN (SELECT id FROM (SELECT id FROM services WHERE is_demo = 1) s)',
            'DELETE FROM services WHERE is_demo = 1',
            'DELETE FROM partners WHERE is_demo = 1',

            // Content + users
            'DELETE FROM blog_posts WHERE is_demo = 1',
            'DELETE FROM cms_blocks WHERE is_demo = 1',
            'DELETE FROM customer_addresses WHERE user_id IN (SELECT id FROM (SELECT id FROM users WHERE is_demo = 1) u)',
            'DELETE FROM user_profiles WHERE user_id IN (SELECT id FROM (SELECT id FROM users WHERE is_demo = 1) u)',
            'DELETE FROM personal_access_tokens WHERE tokenable_id IN (SELECT id FROM (SELECT id FROM users WHERE is_demo = 1) u)',
            'DELETE FROM users WHERE is_demo = 1',
        ];
    }
}
