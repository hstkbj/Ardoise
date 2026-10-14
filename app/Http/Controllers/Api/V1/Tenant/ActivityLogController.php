<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ActivityLog;
use App\Support\ActivityLabels;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Journal d'activité de l'école : qui a fait quoi, quand, depuis quelle adresse.
 *   GET /activity-log?search=&filter[group]=student&filter[user_id]=3&filter[from]=2026-10-01&filter[to]=2026-10-31
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('activity_log.view');

        $page = ListQuery::paginate(ActivityLog::query()->with(['user', 'subject']), $request,
            search: ['action', 'ip_address', 'user.name', 'user.email'],
            filters: [
                'group' => fn ($q, $v) => $q->where(fn ($w) => $w->where('action', $v)->orWhere('action', 'like', $v.'.%')),
                'user_id' => 'user_id',
                'from' => fn ($q, $v) => $q->where('created_at', '>=', $v.' 00:00:00'),
                'to' => fn ($q, $v) => $q->where('created_at', '<=', $v.' 23:59:59'),
            ],
            sorts: ['created_at' => 'created_at'],
            default: '-created_at',
        );

        $page->getCollection()->transform(fn (ActivityLog $log) => [
            'id' => $log->id,
            'created_at' => $log->created_at?->toIso8601String(),
            'user_name' => $log->user?->name ?? ($log->user_id ? 'Compte supprimé' : 'Système'),
            'user_email' => $log->user?->email,
            'action' => $log->action,
            'action_label' => ActivityLabels::action($log->action),
            'subject_type' => ActivityLabels::subjectType($log->subject_type),
            'subject_label' => $this->subjectLabel($log),
            'details' => $this->details($log->properties ?? []),
            'ip_address' => $log->ip_address,
        ]);

        return ListQuery::json($page);
    }

    protected function subjectLabel(ActivityLog $log): ?string
    {
        if (! $log->subject_type) {
            return null;
        }

        $type = ActivityLabels::subjectType($log->subject_type);
        $subject = $log->subject;

        if (! $subject instanceof Model) {
            return $type.' n° '.$log->subject_id;
        }

        $name = $subject->getAttribute('full_name') ?? $subject->getAttribute('name') ?? $subject->getAttribute('title') ?? $subject->getAttribute('reference');

        return $name ? $type.' : '.$name : $type.' n° '.$log->subject_id;
    }

    /** Propriétés lisibles : « montant : 50 000 · frais : Scolarité ». */
    protected function details(array $properties): ?string
    {
        $parts = collect($properties)
            ->map(fn ($value, $key) => ActivityLabels::property((string) $key).' : '.(is_array($value) ? implode(', ', array_map('strval', array_filter($value, 'is_scalar'))) : (is_bool($value) ? ($value ? 'oui' : 'non') : (string) $value)))
            ->filter(fn ($line) => mb_strlen($line) <= 300)
            ->values();

        return $parts->isEmpty() ? null : $parts->implode(' · ');
    }
}
