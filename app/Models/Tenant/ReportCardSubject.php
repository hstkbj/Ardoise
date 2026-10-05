<?php

namespace App\Models\Tenant;

class ReportCardSubject extends TenantModel
{
    protected function casts(): array
    {
        return ['coefficient' => 'float', 'average' => 'float', 'class_average' => 'float'];
    }
}
