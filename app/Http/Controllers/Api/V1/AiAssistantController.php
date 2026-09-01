<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Ai\AiManager;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Service;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 44/45 — AI assistance endpoints (advisory only).
 * All degrade gracefully to rule-based results when no provider is configured.
 */
class AiAssistantController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AiManager $ai)
    {
    }

    /** AI Service Finder: natural language → ranked services (rule-based fallback). */
    public function findService(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:64'],
        ]);

        // Rule-based search (deterministic backbone)
        $services = Service::query()->active()
            ->with('category:id,name,slug', 'partner:id,display_name,slug,rating_avg')
            ->when($data['city'] ?? null, fn ($q, $c) => $q->whereHas('partner', fn ($p) => $p->where('city', 'like', "%{$c}%")))
            ->where(fn ($w) => $w
                ->where('title', 'like', "%{$data['query']}%")
                ->orWhere('description', 'like', "%{$data['query']}%")
                ->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$data['query']}%")))
            ->limit(8)->get();

        $aiSummary = $this->ai->advise('service_finder', [
            'query' => $data['query'],
            'candidates' => $services->map(fn ($s) => ['id' => $s->id, 'title' => $s->title])->all(),
        ]);

        return $this->ok([
            'services' => $services,
            'ai_summary' => $aiSummary, // null when AI disabled — feature still works
            'mode' => $this->ai->available() ? 'hybrid' : 'rule_based',
        ]);
    }

    /** AI RFQ/Project brief builder: helps customers structure requirements. */
    public function buildBrief(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:project,rfq'],
            'raw_requirements' => ['required', 'string', 'max:5000'],
            'category_id' => ['nullable', 'integer'],
        ]);

        $category = isset($data['category_id']) ? Category::find($data['category_id'])?->only(['id', 'name']) : null;

        $suggestion = $this->ai->advise("{$data['type']}_brief_builder", [
            'raw_requirements' => $data['raw_requirements'],
            'category' => $category,
        ]);

        // Deterministic fallback structure
        return $this->ok([
            'suggestion' => $suggestion ?? [
                'title' => null,
                'structured_requirements' => [
                    'scope' => $data['raw_requirements'],
                    'deliverables' => [],
                    'constraints' => [],
                    'acceptance_criteria' => [],
                ],
                'missing_information' => ['budget', 'timeline', 'lokasi/remote', 'dokumen referensi'],
            ],
            'mode' => $this->ai->available() ? 'ai' : 'template',
        ]);
    }

    /** Conversation summary (advisory, read-only). */
    public function summarizeConversation(Request $request, int $conversationId): JsonResponse
    {
        $conversation = \App\Models\Conversation::findOrFail($conversationId);
        app(\App\Domain\Chat\ChatService::class)->assertParticipant($conversation, $request->user());

        $messages = $conversation->messages()->latest()->limit(50)->get()->reverse()
            ->map(fn ($m) => ['sender' => $m->sender_id, 'type' => $m->type, 'body' => $m->body])
            ->values();

        $summary = $this->ai->advise('conversation_summary', ['messages' => $messages]);

        return $this->ok([
            'summary' => $summary,
            'mode' => $this->ai->available() ? 'ai' : 'unavailable',
        ]);
    }
}
