<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Actions\CreateTenant;
use App\Http\Controllers\Controller;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\PlatformStatsService;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = ListQuery::paginate(Tenant::with(['plan', 'domains', 'latestUsage']), $request,
            search: ['name', 'code', 'admin_email', 'domains.domain'],
            filters: [
                'status' => 'status',
                'plan' => fn ($q, $v) => $q->whereHas('plan', fn ($p) => $p->where('name', $v)),
                'plan_id' => 'plan_id',
            ],
            sorts: ['name' => 'name', 'expires_at' => 'expires_at', 'created_at' => 'created_at', 'students_count' => 'id'],
            default: '-created_at',
        );
        $page->getCollection()->transform(fn (Tenant $t) => self::present($t));

        return ListQuery::json($page);
    }

    /** Création complète d'une école ; le mot de passe initial de l'administrateur est renvoyé une seule fois. */
    public function store(Request $request, CreateTenant $action): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:40'],
            'domain' => ['nullable', 'string', 'max:150', Rule::unique('central.domains', 'domain')],
            'plan' => ['nullable', 'string'],
            'plan_id' => ['nullable', 'integer', 'exists:central.plans,id'],
            'status' => ['nullable', Rule::in(['active', 'trial'])],
            'expires_at' => ['nullable', 'date'],
            'admin_name' => ['required', 'string', 'max:150'],
            'admin_email' => ['required', 'email', 'max:150'],
        ]);

        $data['plan_id'] ??= isset($data['plan']) ? Plan::where('name', $data['plan'])->value('id') : null;
        $result = $action->handle($data);

        return response()->json([
            'data' => self::present($result['tenant']->load('plan', 'domains')),
            'meta' => [
                'admin_email' => $data['admin_email'],
                'admin_password' => $result['admin_password'],
                'login_url' => request()->getScheme().'://'.$result['tenant']->primaryDomain().'/login',
            ],
            'message' => 'École créée.',
        ], 201);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        return response()->json(['data' => self::present($tenant->load('plan', 'domains', 'latestUsage', 'subscription'))]);
    }

    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'plan' => ['nullable', 'string'],
            'plan_id' => ['nullable', 'integer', 'exists:central.plans,id'],
            'expires_at' => ['nullable', 'date'],
            'domain' => ['nullable', 'string', 'max:150'],
            'admin_name' => ['nullable', 'string', 'max:150'],
            'admin_email' => ['nullable', 'email'],
        ]);

        $planId = $data['plan_id'] ?? (isset($data['plan']) ? Plan::where('name', $data['plan'])->value('id') : $tenant->plan_id);
        $tenant->update([
            'name' => $data['name'],
            'plan_id' => $planId,
            'expires_at' => $data['expires_at'] ?? $tenant->expires_at,
            'admin_name' => $data['admin_name'] ?? $tenant->admin_name,
            'admin_email' => $data['admin_email'] ?? $tenant->admin_email,
        ]);

        if (! empty($data['domain']) && ! $tenant->domains()->where('domain', $data['domain'])->exists()) {
            $tenant->domains()->create(['domain' => strtolower($data['domain'])]);
        }

        $tenant->subscription?->update(['plan_id' => $planId, 'expires_at' => $tenant->expires_at]);
        $this->forgetHosts($tenant);

        return response()->json(['data' => self::present($tenant->load('plan', 'domains', 'latestUsage'))]);
    }

    public function suspend(Tenant $tenant): JsonResponse
    {
        $tenant->update(['status' => 'suspended']);
        $tenant->subscription?->update(['status' => 'suspended']);
        $this->forgetHosts($tenant);

        return response()->json(['data' => self::present($tenant->load('plan', 'domains')), 'message' => 'École suspendue.']);
    }

    public function activate(Tenant $tenant): JsonResponse
    {
        $tenant->update(['status' => 'active', 'expires_at' => $tenant->expires_at?->isPast() ? now()->addYear() : $tenant->expires_at]);
        $tenant->subscription?->update(['status' => 'active', 'expires_at' => $tenant->expires_at]);
        $this->forgetHosts($tenant);

        return response()->json(['data' => self::present($tenant->load('plan', 'domains')), 'message' => 'École activée.']);
    }

    /** Statistiques en direct (connexion temporaire à la base de l'école). */
    public function stats(Tenant $tenant, PlatformStatsService $stats): JsonResponse
    {
        return response()->json(['data' => $stats->live($tenant)]);
    }

    public static function present(Tenant $t): array
    {
        $usage = $t->relationLoaded('latestUsage') ? $t->latestUsage : null;

        return [
            'id' => $t->id,
            'name' => $t->name,
            'code' => $t->code,
            'domain' => $t->relationLoaded('domains') ? ($t->domains->first()?->domain ?? $t->primaryDomain()) : $t->primaryDomain(),
            'domains' => $t->relationLoaded('domains') ? $t->domains->pluck('domain') : [],
            'plan' => $t->plan?->name,
            'plan_id' => $t->plan_id,
            'status' => $t->status,
            'admin_name' => $t->admin_name,
            'admin_email' => $t->admin_email,
            'users_count' => $usage?->users ?? 0,
            'students_count' => $usage?->students ?? 0,
            'created_at' => $t->created_at?->toDateString(),
            'expires_at' => $t->expires_at?->toDateString(),
            'trial_ends_at' => $t->trial_ends_at?->toDateString(),
        ];
    }

    protected function forgetHosts(Tenant $tenant): void
    {
        foreach ($tenant->domains()->pluck('domain') as $host) {
            Cache::forget("tenancy:host:{$host}");
        }
    }
}
