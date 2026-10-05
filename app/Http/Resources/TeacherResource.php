<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'subjects' => $this->whenLoaded('subjects', fn () => $this->subjects->pluck('name')->implode(', ')),
            'subject_ids' => $this->whenLoaded('subjects', fn () => $this->subjects->pluck('id')),
            'school_name' => $this->whenLoaded('campuses', fn () => $this->campuses->pluck('name')->implode(', ')),
            'school_ids' => $this->whenLoaded('campuses', fn () => $this->campuses->pluck('id')),
            'classes_count' => (int) ($this->classes_count ?? 0),
            'hired_on' => $this->hired_on?->toDateString(),
            'contract' => $this->contract,
            'status' => $this->status,
            'user_id' => $this->user_id,
        ];
    }
}
