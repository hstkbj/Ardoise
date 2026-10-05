<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\SupportMessage;
use App\Models\Central\SupportTicket;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Support côté école. Les tickets vivent en base centrale, filtrés sur l'école courante. */
class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $page = ListQuery::paginate($this->scope(), $request,
            search: ['subject', 'category'],
            filters: ['status' => 'status', 'priority' => 'priority'],
            sorts: ['updated_at' => 'updated_at'],
            default: '-updated_at',
        );
        $page->getCollection()->transform(fn ($t) => self::present($t));

        return ListQuery::json($page);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high'])],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $user = $request->user();
        $ticket = SupportTicket::create([
            'tenant_id' => tenant()->id,
            'requester_user_id' => $user->id,
            'requester_name' => $user->name,
            'requester_email' => $user->email,
            'subject' => $data['subject'],
            'category' => $data['category'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'status' => 'open',
        ]);
        $ticket->messages()->create(['author_type' => 'client', 'author_name' => $user->name, 'body' => $data['message']]);

        return response()->json(['data' => self::present($ticket)], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => self::present($this->scope()->findOrFail($id))]);
    }

    public function messages(int $id): JsonResponse
    {
        $ticket = $this->scope()->findOrFail($id);

        return response()->json(['data' => $ticket->messages->map(fn (SupportMessage $m) => self::presentMessage($m))]);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $ticket = $this->scope()->findOrFail($id);
        $body = $request->validate(['body' => ['required', 'string', 'max:5000']])['body'];

        $message = $ticket->messages()->create(['author_type' => 'client', 'author_name' => $request->user()->name, 'body' => $body]);
        $ticket->update(['status' => $ticket->status === 'resolved' ? 'open' : $ticket->status]);
        $ticket->touch();

        return response()->json(['data' => self::presentMessage($message)], 201);
    }

    protected function scope()
    {
        return SupportTicket::with('tenant')->where('tenant_id', tenant()->id);
    }

    public static function present(SupportTicket $t): array
    {
        return [
            'id' => $t->id,
            'subject' => $t->subject,
            'category' => $t->category,
            'priority' => $t->priority,
            'status' => $t->status,
            'requester' => $t->requester_name,
            'tenant_name' => $t->tenant?->name,
            'created_at' => $t->created_at,
            'updated_at' => $t->updated_at,
        ];
    }

    public static function presentMessage(SupportMessage $m): array
    {
        return ['id' => $m->id, 'author' => $m->author_name, 'side' => $m->author_type, 'at' => $m->created_at, 'body' => $m->body];
    }
}
