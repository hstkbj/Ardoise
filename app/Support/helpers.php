<?php

use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;

if (! function_exists('tenant')) {
    /** École courante (ou null sur le domaine central). */
    function tenant(): ?Tenant
    {
        return app(TenantManager::class)->current();
    }
}
