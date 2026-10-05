<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'coefficient' => $this->default_coefficient,
            'level' => $this->level,
            'category' => $this->category,
            'teacher_id' => $this->teacher_id,
            'teacher_name' => $this->teacher?->full_name ?? '—',
            'status' => $this->status,
        ];
    }
}
