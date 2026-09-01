<?php

namespace App\Domain\Ai;

interface AiProviderInterface
{
    /** @param array<string, mixed> $messages [{role, content}] */
    public function complete(array $messages, array $options = []): string;
}
