<?php

namespace Tests\Feature\Deal;

use App\Models\Category;
use App\Models\Partner;
use App\Models\Quotation;
use App\Models\Rfq;
use App\Models\User;
use App\Support\Demo\DemoMediaPool;
use Database\Factories\PartnerFactory;
use Database\Factories\ServiceFactory;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quotation → service order conversion (survey→quotation→order path):
 * only approved quotations convert, exactly once, into a pending_payment
 * order carrying the quotation totals in the pricing snapshot.
 */
class QuotationToOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $partnerUser;

    private Partner $partner;

    private Rfq $rfq;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(CatalogSeeder::class);

        $this->buyer = User::factory()->create();
        $this->partnerUser = User::factory()->create();
        $this->partner = Partner::factory()->for($this->partnerUser, 'user')->create();

        $this->rfq = Rfq::create([
            'code' => 'RFQ-'.now()->format('ymd').'-TEST'.uniqid(),
            'user_id' => $this->buyer->id,
            'category_id' => Category::query()->first()->id,
            'title' => 'Butuh perbaikan keran',
            'description' => 'Keran bocor di dapur.',
            'requirements' => null,
            'attachments' => null,
            'deadline' => now()->addDays(7),
            'invited_partner_ids' => null,
            'visibility' => 'public',
            'status' => 'open',
            'is_demo' => false,
        ]);
    }

    private function submitQuotation(): Quotation
    {
        return app(\App\Domain\Deal\RfqService::class)->submitQuotation($this->partner, [
            'rfq_id' => $this->rfq->id,
            'line_items' => [
                ['name' => 'Perbaikan keran', 'qty' => 1, 'unit_price' => 250000],
                ['name' => 'Material sparepart', 'qty' => 2, 'unit_price' => 50000],
            ],
            'discount' => 25000,
            'terms' => 'Garansi 30 hari.',
        ]);
    }

    public function test_approved_quotation_converts_to_pending_payment_order(): void
    {
        $quotation = $this->submitQuotation();
        app(\App\Domain\Deal\RfqService::class)->approveQuotation($quotation, $this->buyer);

        $order = app(\App\Domain\Deal\RfqService::class)->convertQuotationToOrder($quotation->fresh(), $this->buyer);

        $this->assertSame(\App\Models\Order::TYPE_SERVICE, $order->type);
        $this->assertSame('pending_payment', $order->status);
        $this->assertSame(325000, (int) $order->total);
        $this->assertSame(250000 + (2 * 50000), (int) $order->subtotal);
        $this->assertSame('quotation', $order->pricing_snapshot['source']);
        $this->assertSame($quotation->id, $order->pricing_snapshot['quotation_id']);
        $this->assertCount(2, $order->items);
        $this->assertSame($order->id, $quotation->fresh()->order_id);
    }

    public function test_conversion_is_idempotent(): void
    {
        $quotation = $this->submitQuotation();
        app(\App\Domain\Deal\RfqService::class)->approveQuotation($quotation, $this->buyer);

        $first = app(\App\Domain\Deal\RfqService::class)->convertQuotationToOrder($quotation->fresh(), $this->buyer);
        $second = app(\App\Domain\Deal\RfqService::class)->convertQuotationToOrder($quotation->fresh(), $this->buyer);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, \App\Models\Order::where('meta->quotation_id', $quotation->id)->count());
    }

    public function test_sent_quotation_cannot_convert(): void
    {
        $quotation = $this->submitQuotation();

        $this->expectException(\App\Domain\Auth\DomainException::class);
        app(\App\Domain\Deal\RfqService::class)->convertQuotationToOrder($quotation, $this->buyer);
    }

    public function test_expired_validity_cannot_convert(): void
    {
        $quotation = $this->submitQuotation();
        app(\App\Domain\Deal\RfqService::class)->approveQuotation($quotation, $this->buyer);
        $quotation->update(['valid_until' => now()->subDay()]);

        $this->expectException(\App\Domain\Auth\DomainException::class);
        app(\App\Domain\Deal\RfqService::class)->convertQuotationToOrder($quotation->fresh(), $this->buyer);
    }

    public function test_web_endpoint_orders_and_redirects(): void
    {
        $quotation = $this->submitQuotation();
        app(\App\Domain\Deal\RfqService::class)->approveQuotation($quotation, $this->buyer);

        $res = $this->actingAs($this->buyer)->post(
            route('web.requests.quotations.order', [$this->rfq->id, $quotation->id]),
        );

        $res->assertRedirect();
        $this->assertSame(1, \App\Models\Order::where('user_id', $this->buyer->id)->count());
    }
}
