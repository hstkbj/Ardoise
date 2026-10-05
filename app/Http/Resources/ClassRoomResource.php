<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassRoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'level' => $this->level?->name,
            'level_id' => $this->level_id,
            'school_id' => $this->campus_id,
            'school_name' => $this->campus?->name,
            'academic_year_id' => $this->academic_year_id,
            'academic_year' => $this->academicYear?->name,
            'head_teacher_id' => $this->head_teacher_id,
            'head_teacher' => $this->headTeacher?->full_name,
            'capacity' => $this->capacity,
            'students_count' => (int) ($this->students_count ?? 0),
            'room' => $this->room,
            'status' => $this->status,
            'subjects' => $this->whenLoaded('classSubjects', fn () => $this->classSubjects->map(fn ($cs) => [
                'id' => $cs->id,
                'subject_id' => $cs->subject_id,
                'subject_name' => $cs->subject?->name,
                'teacher_id' => $cs->teacher_id,
                'teacher_name' => $cs->teacher?->full_name,
                'coefficient' => $cs->coefficient,
            ])),
        ];
    }
}
