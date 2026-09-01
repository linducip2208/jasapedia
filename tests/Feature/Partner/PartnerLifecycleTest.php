<?php

namespace Tests\Feature\Partner;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function registerPartner(string $email, string $type = 'individual', array $org = []): array
    {
        $reg = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User', 'email' => $email, 'password' => 'RahasiaKuat99',
        ]);
        $token = $reg->json('data.token');

        $payload = [
            'type' => $type,
            'display_name' => "Bengkel {$type} Sejahtera",
            'city' => 'Jakarta',
            ...$org,
        ];

        $res = $this->withToken($token)->postJson('/api/v1/partner', $payload);

        return [$token, $res];
    }

    public function test_individual_partner_registration_and_lifecycle(): void
    {
        [$token, $res] = $this->registerPartner('partner1@example.test');

        $res->assertCreated()->assertJsonPath('data.partner.type', 'individual');

        // Me
        $this->withToken($token)->getJson('/api/v1/partner/me')
            ->assertOk()->assertJsonPath('data.partner.slug', 'bengkel-individual-sejahtera');

        // Duplicate registration blocked (same user)
        $this->withToken($token)->postJson('/api/v1/partner', [
            'type' => 'individual', 'display_name' => 'Another',
        ])->assertStatus(409);
    }

    public function test_partner_registration_requires_customer_or_any_user(): void
    {
        // Unauthenticated
        $this->postJson('/api/v1/partner', [
            'type' => 'individual', 'display_name' => 'X',
        ])->assertStatus(401);
    }

    public function test_vendor_company_with_organization_and_members(): void
    {
        [$ownerToken] = $this->registerPartner('owner@example.test', 'vendor_company', [
            'organization' => [
                'name' => 'CV Teknik Mantap',
                'npwp' => '012345678901234',
                'address' => 'Jl. Industri 12, Jakarta',
            ],
        ]);

        $me = $this->withToken($ownerToken)->getJson('/api/v1/partner/me');
        $me->assertOk()->assertJsonPath('data.partner.organization.name', 'CV Teknik Mantap');
        $orgId = $me->json('data.partner.organization.id');

        // Invite a worker (must exist as user first)
        $workerReg = $this->postJson('/api/v1/auth/register', [
            'name' => 'Worker', 'email' => 'worker@example.test', 'password' => 'RahasiaKuat99',
        ]);
        $workerReg->assertCreated();

        $add = $this->withToken($ownerToken)->postJson('/api/v1/partner/members', [
            'email' => 'worker@example.test', 'role' => 'worker',
        ]);
        $add->assertCreated()->assertJsonPath('data.member.role', 'worker');

        // Worker now has scoped vendor role
        $workerToken = $workerReg->json('data.token');
        $this->withToken($workerToken)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonFragment(['partner.order.complete']);

        // Owner sees member list
        $this->withToken($ownerToken)->getJson('/api/v1/partner/me')
            ->assertOk()
            ->assertJsonCount(2, 'data.partner.organization.members');

        // Remove member
        $memberId = $add->json('data.member.id');
        $this->withToken($ownerToken)->deleteJson("/api/v1/partner/members/{$memberId}")
            ->assertNoContent();

        // Worker loses vendor permissions
        $this->withToken($workerToken)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonMissing(['partner.order.complete']);
    }

    public function test_online_status_requires_verification(): void
    {
        [$token] = $this->registerPartner('offline@example.test');

        $this->withToken($token)->postJson('/api/v1/partner/online-status', ['status' => 'online'])
            ->assertStatus(403)->assertJsonPath('error.code', 'NOT_VERIFIED');
    }

    public function test_skills_documents_service_areas_payout(): void
    {
        [$token] = $this->registerPartner('full@example.test');

        $this->withToken($token)->postJson('/api/v1/partner/skills', ['name' => 'AC Split', 'level' => 5])
            ->assertCreated();

        $this->withToken($token)->postJson('/api/v1/partner/skills', ['name' => 'AC Cassette'])
            ->assertCreated();

        $this->withToken($token)->deleteJson('/api/v1/partner/skills/999')->assertNoContent();

        $this->withToken($token)->postJson('/api/v1/partner/documents', [
            'type' => 'ktp', 'file_path' => 'partners/ktp/abc.jpg', 'number' => '327101...',
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/v1/partner/service-areas', [
            'coverage_type' => 'radius', 'radius_km' => 15,
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/v1/partner/payout-destinations', [
            'type' => 'bank', 'bank_code' => 'BCA', 'account_number' => '1234567890', 'account_name' => 'PT TEST',
        ])->assertCreated();

        $me = $this->withToken($token)->getJson('/api/v1/partner/me');
        $me->assertOk()
            ->assertJsonCount(2, 'data.partner.skills')
            ->assertJsonCount(1, 'data.partner.documents');
    }

    public function test_verification_submission_flow(): void
    {
        [$token] = $this->registerPartner('verify@example.test');

        $this->withToken($token)->postJson('/api/v1/partner/submit-verification')
            ->assertOk()->assertJsonPath('data.verification_state', 'submitted');

        // Double submit blocked
        $this->withToken($token)->postJson('/api/v1/partner/submit-verification')
            ->assertStatus(409);
    }
}
