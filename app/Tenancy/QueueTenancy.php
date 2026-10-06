<?php

namespace App\Tenancy;

use App\Models\Central\Tenant;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

/**
 * File d'attente consciente de l'école.
 *
 * Chaque tâche mise en file pendant qu'une école est connectée emporte son
 * identifiant ; le worker reconnecte la base de cette école avant d'exécuter
 * la tâche (notifications, e-mails…), puis la déconnecte.
 * La file elle-même (table jobs) reste dans la base centrale.
 */
class QueueTenancy
{
    public const PAYLOAD_KEY = 'tenant_id';

    public static function register(): void
    {
        Queue::createPayloadUsing(function (): array {
            $tenant = app(TenantManager::class)->current();

            return $tenant ? [self::PAYLOAD_KEY => $tenant->id] : [];
        });

        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            // Exécution immédiate (sync) : le contexte de la requête est déjà le bon
            if ($event->connectionName === 'sync') {
                return;
            }

            $manager = app(TenantManager::class);
            $tenantId = $event->job->payload()[self::PAYLOAD_KEY] ?? null;
            $tenant = $tenantId ? Tenant::find($tenantId) : null;

            $tenant ? $manager->connect($tenant) : $manager->disconnect();
        });

        Event::listen([JobProcessed::class, JobFailed::class, JobExceptionOccurred::class], function ($event): void {
            if ($event->connectionName !== 'sync') {
                app(TenantManager::class)->disconnect();
            }
        });
    }
}
