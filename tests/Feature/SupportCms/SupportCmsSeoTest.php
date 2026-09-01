<?php

namespace Tests\Feature\SupportCms;

use App\Models\BlogPost;
use App\Models\CmsBlock;
use App\Models\CmsPage;
use App\Models\SeoMetadata;
use App\Models\SupportTicket;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportCmsSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(CatalogSeeder::class);
    }

    public function test_support_ticket_lifecycle(): void
    {
        $reg = $this->postJson('/api/v1/auth/register', ['name' => 'U', 'email' => 's@test.id', 'password' => 'RahasiaKuat99']);
        $token = $reg->json('data.token');

        $ticket = $this->withToken($token)->postJson('/api/v1/support/tickets', [
            'category' => 'order',
            'subject' => 'Pesanan belum diproses',
            'body' => 'Sudah bayar tapi status masih searching.',
        ]);
        $ticket->assertCreated()->assertJsonPath('data.ticket.status', 'open');
        $ticketId = $ticket->json('data.ticket.id');

        // reply
        $this->withToken($token)->postJson("/api/v1/support/tickets/{$ticketId}/reply", [
            'body' => 'Update: sudah masuk',
        ])->assertCreated();

        $show = $this->withToken($token)->getJson("/api/v1/support/tickets/{$ticketId}");
        $show->assertOk()->assertJsonCount(2, 'data.ticket.messages');

        // isolation
        $other = $this->postJson('/api/v1/auth/register', ['name' => 'O', 'email' => 'o@test.id', 'password' => 'RahasiaKuat99']);
        $this->withToken($other->json('data.token'))->getJson("/api/v1/support/tickets/{$ticketId}")
            ->assertStatus(404);
    }

    public function test_cms_page_blocks_and_blog(): void
    {
        CmsPage::create(['slug' => 'refund-policy', 'title' => 'Kebijakan Refund', 'content' => '<p>Full refund dalam 24 jam.</p>', 'status' => 'published']);
        CmsBlock::create(['key' => 'home.hero', 'type' => 'hero', 'data' => ['title' => 'Semua Jasa, Satu Platform'], 'sort' => 0]);
        BlogPost::create(['slug' => 'tips-cuci-ac', 'title' => 'Tips Cuci AC', 'content' => 'Cuci AC 3 bulan sekali.', 'status' => 'published', 'published_at' => now()]);

        $this->getJson('/api/v1/cms/pages/refund-policy')->assertOk()->assertJsonPath('data.page.title', 'Kebijakan Refund');
        $this->getJson('/api/v1/cms/pages/nonexistent')->assertStatus(404);
        $this->getJson('/api/v1/cms/blocks')->assertOk()->assertJsonCount(1, 'data.blocks');
        $this->getJson('/api/v1/blog/tips-cuci-ac')->assertOk();
    }

    public function test_seo_landing_metadata(): void
    {
        $catId = \App\Models\Category::where('slug', 'cuci-ac')->value('id')
            ?? \App\Models\Category::where('slug', 'ac-electronics')->value('id');

        SeoMetadata::create([
            'page_type' => 'category_city',
            'category_id' => $catId,
            'city' => 'jakarta-selatan',
            'meta_title' => 'Jasa Cuci AC Jakarta Selatan — Cepat & Bergaransi',
            'meta_description' => 'Cuci AC di Jakarta Selatan mulai Rp90.000, teknisi terverifikasi.',
            'h1' => 'Cuci AC di Jakarta Selatan',
            'noindex' => false,
        ]);

        $res = $this->getJson('/api/v1/jasa/ac-electronics/jakarta-selatan');
        $res->assertOk()->assertJsonPath('data.seo.h1', 'Cuci AC di Jakarta Selatan');

        // generic fallback
        $this->getJson('/api/v1/jasa/ac-electronics')->assertOk();
    }
}
