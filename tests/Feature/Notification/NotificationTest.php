<?php

namespace Tests\Feature\Notification;

use App\Domain\Notification\NotificationService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function tokenFor(string $email): array
    {
        $res = $this->postJson('/api/v1/auth/register', [
            'name' => 'U', 'email' => $email, 'password' => 'RahasiaKuat99',
        ]);

        return [$res->json('data.token'), $res->json('data.user.id')];
    }

    public function test_in_app_notification_lifecycle(): void
    {
        [$token, $userId] = $this->tokenFor('n1@test.id');

        app(NotificationService::class)->notify($userId, 'order.paid', 'Pembayaran diterima', 'Pesanan JP-123 sedang diproses', ['order_id' => 1]);

        $list = $this->withToken($token)->getJson('/api/v1/notifications');
        $list->assertOk()->assertJsonCount(1, 'data');

        $count = $this->withToken($token)->getJson('/api/v1/notifications/unread-count');
        $count->assertOk()->assertJsonPath('data.unread', 1);

        $id = $list->json('data.0.id');
        $this->withToken($token)->postJson('/api/v1/notifications/read', ['ids' => [$id]])->assertOk();

        $count2 = $this->withToken($token)->getJson('/api/v1/notifications/unread-count');
        $count2->assertOk()->assertJsonPath('data.unread', 0);
    }

    public function test_opt_out_respects_non_critical(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => ['marketing.promo' => ['email' => true]],
        ]);

        // opt-out honored for marketing email
        $svc = app(NotificationService::class);
        $svc->notify($user, 'marketing.promo', 'Promo!', null, [], ['in_app', 'email']);

        $rows = DB::table('notifications')->where('user_id', $user->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame('in_app', $rows[0]->channel);
    }

    public function test_preferences_roundtrip(): void
    {
        [$token] = $this->tokenFor('n2@test.id');

        $this->withToken($token)->putJson('/api/v1/notifications/preferences', [
            'preferences' => [
                ['event' => 'marketing.promo', 'channel' => 'email', 'opted_out' => true],
            ],
        ])->assertOk();

        $this->withToken($token)->getJson('/api/v1/notifications/preferences')
            ->assertOk()
            ->assertJsonFragment(['marketing.promo' => ['email' => true]]);
    }

    public function test_isolation_between_users(): void
    {
        [$t1, $u1] = $this->tokenFor('a1@test.id');
        [$t2, $u2] = $this->tokenFor('a2@test.id');

        app(NotificationService::class)->notify($u1, 'test.event', 'Only you');

        $mine = $this->withToken($t1)->getJson('/api/v1/notifications');
        $theirs = $this->withToken($t2)->getJson('/api/v1/notifications');

        $this->assertCount(1, $mine->json('data'));
        $this->assertCount(0, $theirs->json('data'));
    }
}
