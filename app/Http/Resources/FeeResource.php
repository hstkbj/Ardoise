<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'amount' => $this->amount,
            'installments' => $this->installments,
            'interval_months' => $this->interval_months,
            'first_due_date' => $this->first_due_date?->toDateString(),
            'level' => $this->whenLoaded('levels', fn () => $this->levels->pluck('name')->implode(', ') ?: 'Tous niveaux'),
            'level_ids' => $this->whenLoaded('levels', fn () => $this->levels->pluck('id')),
            'school_id' => $this->campus_id,
            'school_name' => $this->campus?->name ?? 'Tous',
            'academic_year_id' => $this->academic_year_id,
            'academic_year' => $this->academicYear?->name,
            'assignments_count' => (int) ($this->assignments_count ?? 0),
            'status' => $this->status,
        ];
    }
}
