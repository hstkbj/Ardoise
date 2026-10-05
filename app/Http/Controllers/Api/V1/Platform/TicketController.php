<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Api\V1\Tenant\SupportTicketController;
use App\Http\Controllers\Controller;
use App\Models\Central\SupportTicket;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Support côté plateforme : tous les tickets, toutes écoles. */
class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = ListQuery::paginate(SupportTicket::with('tenant'), $request,
            search: ['subject', 'requester_name', 'tenant.name'],
            filters: ['status' => 'status', 'priority' => 'priority', 'tenant_id' => 'tenant_id'],
            sorts: ['updated_at' => 'updated_at'],
            default: '-updated_at',
        );
        $page->getCollection()->transform(fn ($t) => SupportTicketController::present($t));

        return ListQuery::json($page);
    }

    public function show(SupportTicket $ticket): JsonResponse
    {
        return response()->json(['data' => SupportTicketController::present($ticket->load('tenant'))]);
    }

    public function update(Request $request, SupportTicket $ticket): JsonResponse
    {
        $ticket->update($request->validate(['status' => ['required', Rule::in(['open', 'in_progress', 'resolved'])], 'priority' => ['nullable', Rule::in(['low', 'normal', 'high'])]]));

        return response()->json(['data' => SupportTicketController::present($ticket->load('tenant'))]);
    }

    public function messages(SupportTicket $ticket): JsonResponse
    {
        return response()->json(['data' => $ticket->messages->map(fn ($m) => SupportTicketController::presentMessage($m))]);
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $body = $request->validate(['body' => ['required', 'string', 'max:5000']])['body'];
        $message = $ticket->messages()->create(['author_type' => 'support', 'author_name' => 'Support '.config('app.name'), 'body' => $body]);
        $ticket->status === 'open' && $ticket->update(['status' => 'in_progress']);
        $ticket->touch();

        return response()->json(['data' => SupportTicketController::presentMessage($message)], 201);
    }
}
