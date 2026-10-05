<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Homework extends TenantModel
{
    protected $table = 'homework';

    protected function casts(): array
    {
        return ['due_date' => 'date', 'published_at' => 'datetime', 'attachments' => 'array'];
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class);
    }
}
