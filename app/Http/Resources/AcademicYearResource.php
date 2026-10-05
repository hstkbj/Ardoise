<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcademicYearResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'period_type' => $this->period_type,
            'terms_count' => (int) ($this->terms_count ?? $this->terms()->count()),
            'terms' => $this->whenLoaded('terms', fn () => $this->terms->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'starts_on' => $t->starts_on?->toDateString(), 'ends_on' => $t->ends_on?->toDateString()])),
            'status' => $this->status,
        ];
    }
}
