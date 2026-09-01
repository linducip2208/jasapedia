<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Domain\Chat\ChatService;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatWebController extends Controller
{
    public function index(Request $request)
    {
        $conversations = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('users.id', Auth::id()))
            ->with(['participants:id,name', 'messages' => fn ($m) => $m->latest('id')->limit(1)])
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return view('web.chat.index', ['conversations' => $conversations]);
    }

    public function show(Request $request, int $id)
    {
        $conversation = Conversation::with(['participants:id,name,avatar_path', 'messages.attachments'])
            ->findOrFail($id);

        app(ChatService::class)->assertParticipant($conversation, $request->user());

        $lastMessage = $conversation->messages()->latest('id')->first();
        if ($lastMessage) {
            app(ChatService::class)->markRead($conversation, $request->user(), $lastMessage->id);
        }

        $other = $conversation->participants->firstWhere('id', '!=', $request->user()->id);

        return view('web.chat.show', [
            'conversation' => $conversation,
            'messages' => $conversation->messages()->with('attachments')->latest('id')->limit(100)->get()->reverse()->values(),
            'other' => $other,
        ]);
    }

    public function send(Request $request, int $id)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'client_message_id' => ['nullable', 'string', 'max:64'],
        ]);

        $conversation = Conversation::findOrFail($id);
        $message = app(ChatService::class)->send($conversation, $request->user(), [
            'type' => 'text',
            'body' => $data['body'],
            'client_message_id' => $data['client_message_id'] ?? uniqid('cm_', true),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message->only(['id', 'body', 'created_at'])]);
        }

        return redirect()->route('web.chat.show', $id);
    }

    /** Poll fallback for environments without Reverb. */
    public function poll(Request $request, int $id)
    {
        $conversation = Conversation::findOrFail($id);
        app(ChatService::class)->assertParticipant($conversation, $request->user());

        $after = $request->integer('after_id');
        $messages = Message::where('conversation_id', $id)
            ->where('id', '>', $after)
            ->with('attachments')
            ->limit(50)
            ->get(['id', 'conversation_id', 'sender_id', 'type', 'body', 'created_at']);

        return response()->json(['messages' => $messages]);
    }
}
