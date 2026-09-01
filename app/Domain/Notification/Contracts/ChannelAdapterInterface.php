<?php

namespace App\Domain\Notification\Contracts;

/**
 * Outbound channel adapter architecture (§80).
 * Implementations: EmailChannel (Mailable), PushChannel (FCM), SmsChannel, WhatsAppChannel.
 * Failures must never break transactions (§122) — catch inside send().
 */
interface ChannelAdapterInterface
{
    public function send(User $user, string $event, string $title, ?string $body, array $data = []): bool;
}
