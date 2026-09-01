<?php

namespace Tests\Feature\Ai;

use App\Domain\Ai\AiManager;
use App\Domain\Ai\AiProviderInterface;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\LocationSeeder::class);
        $this->seed(\Database\Seeders\CatalogSeeder::class);
    }

    public function test_find_service_degrades_to_rule_based(): void
    {
        $token = $this->postJson('/api/v1/auth/register', [
            'name' => 'U', 'email' => 'ai@test.id', 'password' => 'RahasiaKuat99',
        ])->json('data.token');

        $res = $this->withToken($token)->postJson('/api/v1/ai/find-service', ['query' => 'cuci']);
        $res->assertOk()->assertJsonPath('data.mode', 'rule_based');
        $this->assertNull($res->json('data.ai_summary'));
    }

    public function test_brief_builder_template_fallback(): void
    {
        $token = $this->postJson('/api/v1/auth/register', [
            'name' => 'U', 'email' => 'ai2@test.id', 'password' => 'RahasiaKuat99',
        ])->json('data.token');

        $res = $this->withToken($token)->postJson('/api/v1/ai/build-brief', [
            'type' => 'project',
            'raw_requirements' => 'Butuh website toko online, budget 10 juta, 1 bulan jadi',
        ]);

        $res->assertOk()->assertJsonPath('data.mode', 'template')
            ->assertJsonStructure(['data' => ['suggestion' => ['structured_requirements', 'missing_information']]]);
    }

    public function test_ai_provider_is_used_when_registered(): void
    {
        config(['services.ai.driver' => FakeAiProvider::class]);
        app()->forgetInstance(\App\Domain\Ai\AiManager::class);

        $token = $this->postJson('/api/v1/auth/register', [
            'name' => 'U', 'email' => 'ai3@test.id', 'password' => 'RahasiaKuat99',
        ])->json('data.token');

        $res = $this->withToken($token)->postJson('/api/v1/ai/build-brief', [
            'type' => 'rfq', 'raw_requirements' => 'AC kantor 20 unit maintenance tahunan',
        ]);

        $res->assertOk()->assertJsonPath('data.mode', 'ai');
        $this->assertStringContainsString('ADVISORY', $res->json('data.suggestion'));

        app()->forgetInstance(\App\Domain\Ai\AiManager::class);
        config(['services.ai.driver' => null]);
    }

    public function test_summary_requires_participation(): void
    {
        $a = $this->postJson('/api/v1/auth/register', ['name' => 'A', 'email' => 'aa@test.id', 'password' => 'RahasiaKuat99']);
        $b = $this->postJson('/api/v1/auth/register', ['name' => 'B', 'email' => 'bb@test.id', 'password' => 'RahasiaKuat99']);

        $conversation = $this->withToken($a->json('data.token'))
            ->postJson('/api/v1/chat/direct', ['user_id' => $b->json('data.user.id')])
            ->json('data.conversation.id');

        $c = $this->postJson('/api/v1/auth/register', ['name' => 'C', 'email' => 'cc@test.id', 'password' => 'RahasiaKuat99']);

        $this->withToken($c->json('data.token'))
            ->postJson("/api/v1/ai/conversations/{$conversation}/summary")
            ->assertStatus(403);
    }
}

class FakeAiProvider implements AiProviderInterface
{
    public function complete(array $messages, array $options = []): string
    {
        return 'ADVISORY ONLY — saran struktur brief dari AI mock.';
    }
}
