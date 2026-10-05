<?php

namespace App\Models\Tenant;

class HomeworkSubmission extends TenantModel
{
    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }
}
