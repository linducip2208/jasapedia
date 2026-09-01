<?php

namespace App\Domain\Ai;

use App\Domain\Auth\DomainException;

/**
 * AI abstraction (ADR-010, doc 89): optional provider, never business-critical.
 *
 * RESTRICTIONS (doc 91) — AI output is advisory ONLY. It must never:
 * release funds, approve refunds/withdrawals, approve KYC, suspend accounts,
 * decide disputes, modify the ledger, or delete records.
 * Those require deterministic rules and/or authorized humans.
 */
class AiManager
{
    public function __construct(private readonly ?AiProviderInterface $provider = null)
    {
    }

    public function available(): bool
    {
        return $this->provider !== null;
    }

    /** Graceful degradation: every feature falls back when AI is unavailable (doc 122). */
    public function advise(string $task, array $context): ?string
    {
        if (! $this->available()) {
            return null;
        }

        $prompt = $this->buildPrompt($task, $context);

        try {
            return $this->provider->complete($prompt);
        } catch (\Throwable $e) {
            report($e);

            return null; // never break the marketplace
        }
    }

    private function buildPrompt(string $task, array $context): array
    {
        $system = <<<'TXT'
        You are Jasapedia's assistant. You provide ADVISORY output only.
        You cannot and must not: release funds, approve refunds or withdrawals,
        approve KYC, suspend accounts, decide disputes, or modify financial records.
        Respond in Indonesian (id-ID) unless asked otherwise.
        TXT;

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => "Task: {$task}\nContext: ".json_encode($context)],
        ];
    }
}
