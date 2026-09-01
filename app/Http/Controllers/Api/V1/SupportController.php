<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\CmsBlock;
use App\Models\CmsPage;
use App\Models\SeoMetadata;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    use ApiResponse;

    public function createTicket(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', 'in:general,order,payment,project,withdrawal,kyc,dispute,warranty,technical'],
            'subject' => ['required', 'string', 'max:190'],
            'body' => ['required', 'string', 'max:10000'],
            'ref_type' => ['nullable', 'string', 'max:32'],
            'ref_id' => ['nullable', 'integer'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
        ]);

        $ticket = SupportTicket::create([
            'code' => 'TKT-'.now()->format('ymd').'-'.strtoupper(\Illuminate\Support\Str::random(5)),
            'user_id' => $request->user()->id,
            'category' => $data['category'],
            'ref_type' => $data['ref_type'] ?? null,
            'ref_id' => $data['ref_id'] ?? null,
            'subject' => $data['subject'],
            'priority' => $data['priority'] ?? 'normal',
            'status' => 'open',
        ]);

        SupportMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'author_type' => 'customer',
            'body' => $data['body'],
        ]);

        return $this->created(['ticket' => $ticket], 'Ticket created.');
    }

    public function myTickets(Request $request): JsonResponse
    {
        $tickets = SupportTicket::where('user_id', $request->user()->id)->latest()->paginate(20);

        return $this->paginated($tickets);
    }

    public function ticket(Request $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::where('user_id', $request->user()->id)->with('messages')->findOrFail($id);

        return $this->ok(['ticket' => $ticket]);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:10000']]);

        $ticket = SupportTicket::where('user_id', $request->user()->id)->findOrFail($id);
        $message = SupportMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'author_type' => 'customer',
            'body' => $data['body'],
        ]);

        if ($ticket->status === 'pending_customer') {
            $ticket->update(['status' => 'open']);
        }

        return $this->created(['message' => $message]);
    }
}
