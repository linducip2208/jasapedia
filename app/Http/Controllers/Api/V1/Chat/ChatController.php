<?php

namespace App\Http\Controllers\Api\V1\Chat;

use App\Domain\Chat\ChatService;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ChatService $chat)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $conversations = $request->user()->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot('last_read_at', 'muted_until')
            ->with('participants:id,name')
            ->when($request->boolean('unread_only'), fn ($q) => $q)
            ->orderByDesc('conversations.last_message_at')
            ->paginate(30);

        return $this->paginated($conversations);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $conversation = $request->user()->belongsToMany(Conversation::class, 'conversation_participants')
            ->where('conversations.id', $id)
            ->first();

        if (! $conversation) {
            return $this->fail('NOT_FOUND', 'Conversation not found.', 404);
        }

        return $this->ok(['conversation' => $conversation->load('participants:id,name')]);
    }

    /** Open (or create) conversation for an order context. */
    public function forOrder(Request $request, int $orderId): JsonResponse
    {
        $order = \App\Models\Order::where(function ($q) use ($request) {
            $q->where('user_id', $request->user()->id)
                ->orWhereHas('partner', fn ($p) => $p->where('user_id', $request->user()->id));
        })->findOrFail($orderId);

        $participantIds = [$order->user_id];
        if ($order->partner) {
            $participantIds[] = $order->partner->user_id;
        }

        $conversation = $this->chat->conversationForContext(
            'order', 'order', $order->id, $participantIds, $request->user(),
        );

        return $this->ok(['conversation' => $conversation->load('participants:id,name')]);
    }

    public function direct(Request $request): JsonResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer', 'different:' . $request->user()->id]]);

        $other = User::findOrFail($data['user_id']);
        $conversation = $this->chat->directConversation($request->user(), $other);

        return $this->ok(['conversation' => $conversation->load('participants:id,name')]);
    }

    public function messages(Request $request, int $id): JsonResponse
    {
        $conversation = Conversation::findOrFail($id);
        $this->chat->assertParticipant($conversation, $request->user());

        $query = $conversation->messages()->with('sender:id,name', 'attachments');

        if ($after = $request->integer('after')) {
            $query->where('id', '>', $after);
        }

        if ($search = $request->string('q')->toString()) {
            $query->where('body', 'like', "%{$search}%");
        }

        $messages = $query->limit(50)->orderBy('id', $request->boolean('asc') ? 'asc' : 'desc')->get();

        return $this->ok(['messages' => $messages]);
    }

    public function send(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'type' => ['sometimes', 'in:text,image,video,audio,file,location,system_event,service_card,order_card,quotation_card,payment_request,milestone_card,reschedule_request,additional_charge_request,dispute_event,warranty_event'],
            'body' => ['nullable', 'string', 'max:8000'],
            'structured' => ['nullable', 'array'],
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
            'client_message_id' => ['nullable', 'string', 'max:64'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*.file_path' => ['required', 'string', 'max:512'],
            'attachments.*.kind' => ['required', 'in:image,video,audio,file'],
            'attachments.*.name' => ['nullable', 'string', 'max:190'],
            'attachments.*.mime' => ['nullable', 'string', 'max:96'],
            'attachments.*.size' => ['nullable', 'integer', 'min:0'],
        ]);

        $conversation = Conversation::findOrFail($id);
        $message = $this->chat->send($conversation, $request->user(), [
            ...$data,
            'type' => $data['type'] ?? 'text',
        ]);

        // Broadcast event (realtime delivery; DB remains source of truth)
        broadcast(new \App\Events\ChatMessageSent($message))->toOthers();

        return $this->created(['message' => $message]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['up_to_message_id' => ['required', 'integer']]);

        $conversation = Conversation::findOrFail($id);
        $count = $this->chat->markRead($conversation, $request->user(), $data['up_to_message_id']);

        return $this->ok(['marked' => $count]);
    }

    public function reportMessage(Request $request, int $messageId): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $message = Message::findOrFail($messageId);
        $conversation = $message->conversation;
        $this->chat->assertParticipant($conversation, $request->user());

        $report = $message->hasMany(\App\Models\MessageReport::class)->create([
            ...$data,
            'reported_by' => $request->user()->id,
        ]);

        return $this->created(['report' => $report]);
    }
}
