<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Notifications de l'utilisateur connecté (tous profils, et superadmins sur /platform/notifications). */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->notifications()->latest();

        if ($type = $request->input('filter.type')) {
            // « subscription. » : toutes les alertes d'abonnement (préfixe)
            $query->where('data', 'like', '%"type":"'.addslashes($type).(str_ends_with($type, '.') ? '%' : '"%'));
        }

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        return NotificationResource::collection($query->paginate(min(100, $request->integer('per_page', 20) ?: 20)))
            ->additional(['meta' => ['unread' => $request->user()->unreadNotifications()->count()]]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->whereKey($id)->update(['read_at' => now()]);

        return response()->json(['message' => 'Notification lue.']);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'Toutes les notifications sont lues.']);
    }
}
