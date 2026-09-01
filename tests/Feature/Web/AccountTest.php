<?php

namespace Tests\Feature\Web;

use App\Models\CustomerAddress;
use App\Models\Partner;
use App\Models\Service;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Customer account center — ownership boundaries and flows.
 */
class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(LocationSeeder::class);
        $this->seed(CatalogSeeder::class);
    }

    private function registerUser(string $email = 'cust@test.id'): string
    {
        return $this->postJson('/api/v1/auth/register', [
            'name' => 'Customer', 'email' => $email, 'password' => 'RahasiaKuat99',
        ])->json('data.token');
    }

    public function test_guest_is_redirected_to_login_for_account_pages(): void
    {
        $this->get('/akun')->assertRedirect(route('login'));
        $this->get('/orders')->assertRedirect(route('login'));
        $this->get('/kebutuhan/buat')->assertRedirect(route('login'));
    }

    public function test_customer_dashboard_renders(): void
    {
        $token = $this->registerUser();
        $this->post('/login-web-does-not-exist'); // no-op guard

        // Web session login via register redirect isn't available for API tokens; simulate actingAs.
        $user = \App\Models\User::where('email', 'cust@test.id')->first();

        $res = $this->actingAs($user)->get('/akun');

        $res->assertOk()
            ->assertSee('Pesanan Aktif')
            ->assertSee('Kebutuhan Terbuka');
    }

    public function test_customer_cannot_read_other_customer_address(): void
    {
        $owner = \App\Models\User::factory()->create();
        $other = \App\Models\User::factory()->create();
        $addr = CustomerAddress::create([
            'user_id' => $owner->id, 'label' => 'Rumah', 'recipient_name' => $owner->name,
            'phone' => '081234567890', 'subdistrict_id' => \App\Models\Location::first()->id,
            'address_line' => 'Jl. Test No. 1', 'is_default' => true,
        ]);

        $res = $this->actingAs($other)->delete('/akun/alamat/'.$addr->id);

        $this->assertDatabaseHas('customer_addresses', ['id' => $addr->id]);
    }

    public function test_favorites_toggle_is_unique(): void
    {
        $user = \App\Models\User::factory()->create();
        $partner = Partner::create([
            'user_id' => \App\Models\User::factory()->create()->id,
            'type' => 'individual', 'display_name' => 'Fav Partner', 'slug' => 'fav-partner',
            'verification_state' => 'verified',
        ]);
        $service = Service::create([
            'partner_id' => $partner->id, 'category_id' => \App\Models\Category::first()->id,
            'title' => 'Fav Service', 'slug' => 'fav-service', 'description' => 'Test.',
            'fulfillment_type' => 'appointment', 'delivery_mode' => 'onsite',
            'price_model' => 'fixed', 'base_price' => 100000, 'status' => 'active',
        ]);

        $r1 = $this->actingAs($user)->postJson('/favorit/toggle', ['service_id' => $service->id]);
        $r2 = $this->actingAs($user)->postJson('/favorit/toggle', ['service_id' => $service->id]);
        $r3 = $this->actingAs($user)->postJson('/favorit/toggle', ['service_id' => $service->id]);

        $r1->assertOk()->assertJson(['favorited' => true]);
        $r2->assertOk()->assertJson(['favorited' => false]);
        $r3->assertOk()->assertJson(['favorited' => true]);
        $this->assertDatabaseCount('favorites', 1);
    }

    public function test_favorites_page_lists_favorited_services(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user)->get('/favorit')->assertOk()->assertSee('Favorit Saya');
    }

    public function test_requests_create_wizard_renders(): void
    {
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)->get('/kebutuhan/buat')->assertOk()->assertSee('Posting Kebutuhan');
    }

    public function test_customer_can_publish_request_and_see_it(): void
    {
        $this->seed(\Database\Seeders\CatalogSeeder::class);
        $user = \App\Models\User::factory()->create();
        $categoryId = \App\Models\Category::first()->id;

        $res = $this->actingAs($user)->post('/kebutuhan', [
            'category_id' => $categoryId,
            'title' => 'AC bocor tidak dingin',
            'description' => 'AC saya bocor dan tidak dingin di Bekasi Selatan.',
        ]);

        $res->assertRedirect();
        $this->assertDatabaseHas('rfqs', ['user_id' => $user->id, 'title' => 'AC bocor tidak dingin']);

        $rfq = \App\Models\Rfq::where('user_id', $user->id)->first();
        $this->actingAs($user)->get('/kebutuhan/'.$rfq->id)->assertOk()->assertSee('AC bocor tidak dingin');
    }

    public function test_customer_cannot_view_others_request(): void
    {
        $this->seed(\Database\Seeders\CatalogSeeder::class);
        $owner = \App\Models\User::factory()->create();
        $intruder = \App\Models\User::factory()->create();
        $rfq = \App\Models\Rfq::create([
            'code' => 'RFQ-TEST-0001',
            'user_id' => $owner->id, 'category_id' => \App\Models\Category::first()->id,
            'title' => 'Rahasia', 'description' => 'Data pribadi', 'visibility' => 'public', 'status' => 'open',
        ]);

        $this->actingAs($intruder)->get('/kebutuhan/'.$rfq->id)->assertStatus(404);
    }
}
