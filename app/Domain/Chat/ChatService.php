<?php

namespace App\Domain\Chat;

use App\Domain\Auth\DomainException;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChatService
{
    public const MESSAGE_TYPES = [
        'text', 'image', 'video', 'audio', 'file', 'location', 'system_event',
        'service_card', 'order_card', 'quotation_card', 'payment_request',
        'milestone_card', 'reschedule_request', 'additional_charge_request',
        'dispute_event', 'warranty_event',
    ];

    public function conversationForContext(string $type, string $contextType, int $contextId, array $participants, ?User $creator = null): Conversation
    {
        return DB::transaction(function () use ($type, $contextType, $contextId, $participants, $creator) {
            $conversation = Conversation::query()
                ->where('context_type', $contextType)
                ->where('context_id', $contextId)
                ->first();

            if (! $conversation) {
                $conversation = Conversation::create([
                    'type' => $type,
                    'context_type' => $contextType,
                    'context_id' => $contextId,
                    'created_by' => $creator?->id,
                ]);
            }

            foreach ($participants as $userId) {
                $conversation->participants()->syncWithoutDetaching([
                    $userId => ['role' => 'member'],
                ]);
            }

            return $conversation;
        });
    }

    public function directConversation(User $a, User $b): Conversation
    {
        return DB::transaction(function () use ($a, $b) {
            $existing = Conversation::where('type', 'direct')
                ->whereHas('participants', fn ($q) => $q->where('user_id', $a->id))
                ->whereHas('participants', fn ($q) => $q->where('user_id', $b->id))
                ->first();

            if ($existing) {
                return $existing;
            }

            $conversation = Conversation::create([
                'type' => 'direct',
                'created_by' => $a->id,
            ]);

            $conversation->participants()->syncWithoutDetaching([
                $a->id => ['role' => 'member'],
                $b->id => ['role' => 'member'],
            ]);

            return $conversation;
        });
    }

    public function send(Conversation $conversation, User $sender, array $data): Message
    {
        $this->assertParticipant($conversation, $sender);

        if (! in_array($data['type'], self::MESSAGE_TYPES, true)) {
            throw new DomainException('Unknown message type.', 'INVALID_TYPE', 422);
        }

        // Idempotency: same client_message_id returns the original message
        if (! empty($data['client_message_id'])) {
            $existing = Message::where('conversation_id', $conversation->id)
                ->where('sender_id', $sender->id)
                ->where('client_message_id', $data['client_message_id'])
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($conversation, $sender, $data) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'type' => $data['type'],
                'body' => $data['body'] ?? null,
                'structured' => $data['structured'] ?? null,
                'reply_to_id' => $data['reply_to_id'] ?? null,
                'client_message_id' => $data['client_message_id'] ?? null,
            ]);

            foreach ($data['attachments'] ?? [] as $attachment) {
                $message->attachments()->create($attachment);
            }

            $conversation->update(['last_message_at' => now()]);

            // Commercial safety: flag contact sharing (doc 44) — policy-driven, non-invasive
            if ($data['type'] === 'text' && $this->containsContact((string) ($data['body'] ?? ''))) {
                DB::table('conversation_events')->insert([
                    'conversation_id' => $conversation->id,
                    'event' => 'masked_contact_warning',
                    'actor_id' => $sender->id,
                    'payload' => json_encode(['detected' => true]),
                    'created_at' => now(),
                ]);
            }

            return $message->load('attachments');
        });
    }

    public function markRead(Conversation $conversation, User $user, int $upToMessageId): int
    {
        $this->assertParticipant($conversation, $user);

        $messages = DB::table('messages')
            ->where('conversation_id', $conversation->id)
            ->where('id', '<=', $upToMessageId)
            ->where('sender_id', '!=', $user->id)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('message_reads')
                ->whereColumn('message_reads.message_id', 'messages.id')
                ->where('message_reads.user_id', $user->id))
            ->pluck('id');

        foreach ($messages as $messageId) {
            DB::table('message_reads')->insertOrIgnore([
                'message_id' => $messageId,
                'user_id' => $user->id,
                'read_at' => now(),
            ]);
        }

        $conversation->participants()
            ->updateExistingPivot($user->id, ['last_read_at' => now()]);

        return $messages->count();
    }

    public function assertParticipant(Conversation $conversation, User $user): void
    {
        $isMember = $conversation->participants()->where('users.id', $user->id)->exists();

        if (! $isMember && ! $user->can('support.ticket.handle')) {
            throw new DomainException('Not a conversation participant.', 'FORBIDDEN', 403);
        }
    }

    /** Lightweight pattern detection for off-platform contact (doc 44). */
    private function containsContact(string $body): bool
    {
        return (bool) preg_match('/(\+62|08[0-9]{8,12})|([a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,})/i', $body);
    }
}
