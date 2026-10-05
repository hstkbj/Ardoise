<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cs = $this->classSubject;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'subject_id' => $cs?->subject_id,
            'subject_name' => $cs?->subject?->name,
            'class_id' => $cs?->class_room_id,
            'class_name' => $cs?->classRoom?->name,
            'teacher_id' => $this->teacher_id,
            'teacher_name' => $this->teacher?->full_name ?? $cs?->teacher?->full_name,
            'term_id' => $this->term_id,
            'term' => $this->term?->name,
            'date' => $this->date?->toDateString(),
            'coefficient' => $this->coefficient,
            'max_score' => $this->max_score,
            'graded_count' => (int) ($this->graded_count ?? 0),
            'students_count' => (int) ($this->students_count ?? 0),
            'status' => $this->status,
            'validated_at' => $this->validated_at,
        ];
    }
}
