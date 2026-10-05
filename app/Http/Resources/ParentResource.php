<?php

namespace App\Http\Resources;

use App\Services\ParentAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParentResource extends JsonResource
{
    /** Le code n'est exposé qu'au personnel autorisé, sur la fiche du parent. */
    public bool $withCode = false;

    public function toArray(Request $request): array
    {
        $students = $this->relationLoaded('students') ? $this->students : collect();

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'profession' => $this->profession,
            'status' => $this->status,
            'children_count' => $students->count(),
            'children' => $students->map(fn ($s) => $s->first_name.($s->currentEnrollment?->classRoom ? ' ('.$s->currentEnrollment->classRoom->name.')' : ''))->implode(', '),
            'student_ids' => $students->pluck('id')->all(),
            'access_code_generated_at' => $this->access_code_generated_at,
            'access_code_last4' => $this->access_code ? substr($this->access_code, -4) : null,
            'access_code' => $this->when($this->withCode, fn () => app(ParentAccessService::class)->formatted($this->resource)),
        ];
    }
}
