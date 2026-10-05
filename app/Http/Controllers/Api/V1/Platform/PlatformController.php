<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Models\Central\PlatformAnnouncement;
use App\Models\Central\PlatformSetting;
use App\Models\Central\Subscription;
use App\Models\Central\SubscriptionPayment;
use App\Models\Central\Tenant;
use App\Models\Central\UsageSnapshot;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Tableau de bord, statistiques, utilisation, écoles et utilisateurs (agrégés), annonces, paramètres. */
class PlatformController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $latest = $this->latestSnapshots();
        $months = collect(range(5, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));

        return response()->json(['data' => [
            'kpis' => [
                'tenants' => Tenant::count(),
                'active' => Tenant::where('status', 'active')->count(),
                'trial' => Tenant::where('status', 'trial')->count(),
                'suspended' => Tenant::where('status', 'suspended')->count(),
                'users' => (int) $latest->sum('users'),
                'students' => (int) $latest->sum('students'),
                'teachers' => (int) $latest->sum('teachers'),
                'revenue_month' => (int) SubscriptionPayment::where('status', 'paid')->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
                'new_tenants_30d' => Tenant::where('created_at', '>=', now()->subDays(30))->count(),
            ],
            'new_tenants_by_month' => $months->map(fn (Carbon $m) => [
                'label' => $m->locale('fr')->isoFormat('MMM'),
                'value' => Tenant::whereBetween('created_at', [$m, $m->copy()->endOfMonth()])->count(),
            ]),
            'students_by_month' => $months->map(fn (Carbon $m) => [
                'label' => $m->locale('fr')->isoFormat('MMM'),
                'value' => (int) $this->snapshotsAt($m->copy()->endOfMonth())->sum('students'),
            ]),
            'revenue_by_month' => $months->map(fn (Carbon $m) => [
                'label' => $m->locale('fr')->isoFormat('MMM'),
                'value' => (int) SubscriptionPayment::where('status', 'paid')->whereBetween('paid_at', [$m, $m->copy()->endOfMonth()])->sum('amount'),
            ]),
            'subscriptions_by_status' => collect(['active' => 'Actifs', 'trial' => 'Essai', 'suspended' => 'Suspendus', 'expired' => 'Expirés', 'cancelled' => 'Annulés'])
                ->map(fn ($label, $status) => ['label' => $label, 'value' => Subscription::where('status', $status)->count()])->values(),
            'active_users_7d' => collect(range(6, 0))->map(fn ($i) => [
                'label' => now()->subDays($i)->locale('fr')->isoFormat('ddd'),
                'value' => (int) UsageSnapshot::whereDate('date', now()->subDays($i)->toDateString())->sum('active_users'),
            ]),
        ]]);
    }

    public function usage(Request $request): JsonResponse
    {
        $rows = $this->latestSnapshots()->load('tenant')->map(fn (UsageSnapshot $u) => [
            'id' => $u->id,
            'tenant_id' => $u->tenant_id,
            'tenant_name' => $u->tenant?->name,
            'students' => $u->students,
            'teachers' => $u->teachers,
            'users' => $u->users,
            'storage_mb' => $u->storage_mb,
            'sms_sent' => $u->sms_sent,
            'last_activity' => $u->last_activity,
            'date' => $u->date->toDateString(),
        ]);

        return $this->paginateCollection($rows, $request, ['tenant_name']);
    }

    /** Établissements de toutes les écoles (instantanés quotidiens). */
    public function schools(Request $request): JsonResponse
    {
        $rows = $this->latestSnapshots()->load('tenant')->flatMap(fn (UsageSnapshot $u) => collect($u->campuses ?? [])->map(fn ($c) => [
            'id' => $u->tenant_id.'-'.$c['id'],
            'name' => $c['name'],
            'tenant_name' => $u->tenant?->name,
            'city' => $c['city'] ?? null,
            'students_count' => $c['students_count'] ?? 0,
            'status' => $c['status'] ?? 'active',
        ]));

        return $this->paginateCollection($rows, $request, ['name', 'tenant_name', 'city']);
    }

    /** Administrateurs et directeurs de chaque école (instantanés quotidiens). */
    public function users(Request $request): JsonResponse
    {
        $rows = $this->latestSnapshots()->load('tenant')->flatMap(fn (UsageSnapshot $u) => collect($u->admins ?? [])->map(fn ($a) => $a + [
            'tenant_name' => $u->tenant?->name,
        ])->map(fn ($a) => ['id' => $u->tenant_id.'-'.$a['id']] + $a));

        return $this->paginateCollection($rows, $request, ['full_name', 'email', 'tenant_name']);
    }

    public function announcements(Request $request): JsonResponse
    {
        $page = ListQuery::paginate(PlatformAnnouncement::query(), $request, search: ['title'], filters: ['status' => 'status'], default: '-id');
        $page->getCollection()->transform(fn ($a) => $this->presentAnnouncement($a));

        return ListQuery::json($page);
    }

    public function showAnnouncement(PlatformAnnouncement $announcement): JsonResponse
    {
        return response()->json(['data' => $this->presentAnnouncement($announcement)]);
    }

    public function storeAnnouncement(Request $request): JsonResponse
    {
        $announcement = PlatformAnnouncement::create($this->announcementData($request));

        return response()->json(['data' => $this->presentAnnouncement($announcement)], 201);
    }

    public function updateAnnouncement(Request $request, PlatformAnnouncement $announcement): JsonResponse
    {
        $announcement->update($this->announcementData($request, $announcement));

        return response()->json(['data' => $this->presentAnnouncement($announcement)]);
    }

    public function destroyAnnouncement(PlatformAnnouncement $announcement): JsonResponse
    {
        $announcement->delete();

        return response()->json(['message' => 'Annonce supprimée.']);
    }

    public function settings(string $section): JsonResponse
    {
        return response()->json(['data' => PlatformSetting::where('section', $section)->first()?->values ?? []]);
    }

    public function updateSettings(Request $request, string $section): JsonResponse
    {
        abort_unless(in_array($section, ['identity', 'saas', 'email', 'notifications', 'plans', 'payments', 'security'], true), 404);
        $values = collect($request->except(['logo']))->map(fn ($v) => is_string($v) ? mb_substr($v, 0, 2000) : $v)->all();
        $row = PlatformSetting::updateOrCreate(['section' => $section], ['values' => $values]);

        return response()->json(['data' => $row->values, 'message' => 'Paramètres enregistrés.']);
    }

    protected function presentAnnouncement(PlatformAnnouncement $a): array
    {
        return ['id' => $a->id, 'title' => $a->title, 'body' => $a->body, 'audience' => $a->audience, 'status' => $a->status, 'published_at' => $a->published_at?->toDateString()];
    }

    protected function announcementData(Request $request, ?PlatformAnnouncement $current = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:5000'],
            'audience' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
        ]);

        $data['status'] ??= 'draft';
        $data['published_at'] = $data['status'] === 'published' ? ($current?->published_at ?? now()) : null;

        return $data;
    }

    /** Dernier instantané de chaque école. */
    protected function latestSnapshots()
    {
        return $this->snapshotsAt(now());
    }

    protected function snapshotsAt(Carbon $date)
    {
        $ids = UsageSnapshot::whereDate('date', '<=', $date->toDateString())
            ->selectRaw('max(id) as id')->groupBy('tenant_id')->pluck('id');

        return UsageSnapshot::whereIn('id', $ids)->get();
    }

    protected function paginateCollection($rows, Request $request, array $searchable): JsonResponse
    {
        $term = mb_strtolower(trim((string) $request->input('search')));
        $rows = collect($rows)->values();

        if ($term !== '') {
            $rows = $rows->filter(fn ($r) => collect($searchable)->contains(fn ($k) => str_contains(mb_strtolower((string) ($r[$k] ?? '')), $term)))->values();
        }

        foreach ((array) $request->input('filter', []) as $key => $value) {
            if ($value !== '' && $value !== null) {
                $rows = $rows->filter(fn ($r) => (string) ($r[$key] ?? '') === (string) $value)->values();
            }
        }

        if ($sort = $request->input('sort')) {
            $key = ltrim($sort, '-');
            $rows = $rows->sortBy($key, SORT_NATURAL, str_starts_with($sort, '-'))->values();
        }

        $perPage = min(200, max(1, (int) $request->input('per_page', 15)));
        $page = max(1, (int) $request->input('page', 1));

        return response()->json([
            'data' => $rows->forPage($page, $perPage)->values(),
            'meta' => ['current_page' => $page, 'per_page' => $perPage, 'total' => $rows->count(), 'last_page' => max(1, (int) ceil($rows->count() / $perPage))],
        ]);
    }
}
