<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomeworkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'instructions' => $this->instructions,
            'subject_id' => $this->subject_id,
            'subject_name' => $this->subject?->name,
            'class_id' => $this->class_room_id,
            'class_name' => $this->classRoom?->name,
            'teacher_id' => $this->teacher_id,
            'teacher_name' => $this->teacher?->full_name,
            'due_date' => $this->due_date?->toDateString(),
            'published_at' => $this->published_at?->toDateString(),
            'attachments' => $this->attachments ?? [],
            'attachments_count' => count($this->attachments ?? []),
            'status' => $this->status,
        ];
    }
}
