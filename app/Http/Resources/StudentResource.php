<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $enrollment = $this->currentEnrollment;
        $class = $enrollment?->classRoom;
        $guardian = $this->relationLoaded('parents') ? ($this->parents->firstWhere('pivot.is_primary', true) ?? $this->parents->first()) : null;

        return [
            'id' => $this->id,
            'matricule' => $this->matricule,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'gender' => $this->gender,
            'birth_date' => $this->birth_date?->toDateString(),
            'birth_place' => $this->birth_place,
            'photo_url' => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null,
            'class_id' => $class?->id,
            'class_name' => $class?->name,
            'school_id' => $class?->campus_id,
            'school_name' => $class?->campus?->name,
            'level' => $class?->level?->name,
            'enrolled_on' => $enrollment?->enrolled_on?->toDateString(),
            'guardian_id' => $guardian?->id,
            'guardian_name' => $guardian?->full_name,
            'guardian_phone' => $guardian?->phone,
            'guardian_relation' => $guardian?->pivot?->relation,
            'parents' => $this->whenLoaded('parents', fn () => $this->parents->map(fn ($p) => [
                'id' => $p->id, 'full_name' => $p->full_name, 'phone' => $p->phone, 'relation' => $p->pivot->relation,
            ])),
            'average' => isset($this->latest_average) ? (float) $this->latest_average : null,
            'status' => $this->status,
        ];
    }
}
