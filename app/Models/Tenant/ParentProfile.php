<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParentProfile extends TenantModel
{
    use SoftDeletes;

    protected $hidden = ['access_code'];

    protected function casts(): array
    {
        return ['access_code' => 'encrypted', 'access_code_generated_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'parent_student')->withPivot('relation', 'is_primary');
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function hasChild(int $studentId): bool
    {
        return $this->students()->whereKey($studentId)->exists();
    }
}
