<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\Message;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private string $aToken;
    private string $bToken;
    private int $aId;
    private int $bId;
    private int $conversationId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\LocationSeeder::class);
        $this->seed(\Database\Seeders\CatalogSeeder::class);

        $a = $this->postJson('/api/v1/auth/register', ['name' => 'A', 'email' => 'a@a.test', 'password' => 'RahasiaKuat99']);
        $b = $this->postJson('/api/v1/auth/register', ['name' => 'B', 'email' => 'b@b.test', 'password' => 'RahasiaKuat99']);

        $this->aToken = $a->json('data.token');
        $this->bToken = $b->json('data.token');
        $this->aId = $a->json('data.user.id');
        $this->bId = $b->json('data.user.id');

        $this->conversationId = $this->withToken($this->aToken)
            ->postJson('/api/v1/chat/direct', ['user_id' => $this->bId])
            ->json('data.conversation.id');
    }

    public function test_send_and_poll_messages(): void
    {
        $send = $this->withToken($this->aToken)->postJson("/api/v1/chat/{$this->conversationId}/messages", [
            'body' => 'Halo, besok jam 9 bisa?',
            'client_message_id' => 'cmid-1',
        ]);
        $send->assertCreated()->assertJsonPath('data.message.type', 'text');

        // Offline retry: same client_message_id → same message, no duplicate
        $retry = $this->withToken($this->aToken)->postJson("/api/v1/chat/{$this->conversationId}/messages", [
            'body' => 'Halo, besok jam 9 bisa?',
            'client_message_id' => 'cmid-1',
        ]);
        $retry->assertCreated();
        $this->assertSame($send->json('data.message.id'), $retry->json('data.message.id'));

        // Recipient sees it
        $messages = $this->withToken($this->bToken)->getJson("/api/v1/chat/{$this->conversationId}/messages?asc=1");
        $messages->assertOk()->assertJsonCount(1, 'data.messages');
    }

    public function test_read_receipts(): void
    {
        $msg = $this->withToken($this->aToken)->postJson("/api/v1/chat/{$this->conversationId}/messages", [
            'body' => 'hello',
        ])->json('data.message');

        $this->withToken($this->bToken)->postJson("/api/v1/chat/{$this->conversationId}/read", [
            'up_to_message_id' => $msg['id'],
        ])->assertOk()->assertJsonPath('data.marked', 1);

        // Second mark → 0 new
        $this->withToken($this->bToken)->postJson("/api/v1/chat/{$this->conversationId}/read", [
            'up_to_message_id' => $msg['id'],
        ])->assertOk()->assertJsonPath('data.marked', 0);

        $this->assertDatabaseCount('message_reads', 1);
    }

    public function test_non_participant_blocked(): void
    {
        $c = $this->postJson('/api/v1/auth/register', ['name' => 'C', 'email' => 'c@c.test', 'password' => 'RahasiaKuat99']);
        $cToken = $c->json('data.token');

        $this->withToken($cToken)->postJson("/api/v1/chat/{$this->conversationId}/messages", [
            'body' => 'intruder',
        ])->assertStatus(403);
    }

    public function test_contact_sharing_flagged(): void
    {
        $this->withToken($this->aToken)->postJson("/api/v1/chat/{$this->conversationId}/messages", [
            'body' => 'Chat saya di 081234567890 ya',
        ])->assertCreated();

        $this->assertDatabaseHas('conversation_events', [
            'conversation_id' => $this->conversationId,
            'event' => 'masked_contact_warning',
        ]);
    }

    public function test_order_conversation_created_once(): void
    {
        // Build minimal order between A (customer) and B (partner)
        $this->withToken($this->bToken)->postJson('/api/v1/partner', [
            'type' => 'individual', 'display_name' => 'B Partner',
        ])->assertCreated();

        \App\Models\Partner::query()->update(['verification_state' => 'verified']);

        $serviceId = \App\Models\Service::create([
            'partner_id' => \App\Models\Partner::first()->id,
            'category_id' => \App\Models\Category::first()->id,
            'title' => 'T', 'slug' => 't-'.uniqid(),
            'fulfillment_type' => 'fixed_package', 'delivery_mode' => 'remote',
            'price_model' => 'fixed', 'base_price' => 100000, 'status' => 'active',
        ])->id;

        $orderRes = $this->withToken($this->aToken)->postJson('/api/v1/orders', [
            'service_id' => $serviceId,
        ]);
        $orderRes->assertCreated();
        $orderId = $orderRes->json('data.order.id');

        $first = $this->withToken($this->aToken)->getJson("/api/v1/chat/orders/{$orderId}/conversation");
        $first->assertOk();
        $second = $this->withToken($this->bToken)->getJson("/api/v1/chat/orders/{$orderId}/conversation");
        $second->assertOk();

        $this->assertSame($first->json('data.conversation.id'), $second->json('data.conversation.id'));
        $this->assertSame('order', $first->json('data.conversation.type'));

        // Both can chat
        $this->withToken($this->bToken)->postJson('/api/v1/chat/'.$first->json('data.conversation.id').'/messages', [
            'body' => 'Siap, saya yang handle.',
        ])->assertCreated();
    }

    public function test_search_messages(): void
    {
        $this->withToken($this->aToken)->postJson("/api/v1/chat/{$this->conversationId}/messages", ['body' => 'Harga negotiable']);
        $this->withToken($this->aToken)->postJson("/api/v1/chat/{$this->conversationId}/messages", ['body' => 'Sudah dikerjakan']);

        $found = $this->withToken($this->aToken)->getJson("/api/v1/chat/{$this->conversationId}/messages?q=negotiable");
        $found->assertOk()->assertJsonCount(1, 'data.messages');
    }
}
