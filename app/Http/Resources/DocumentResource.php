<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'owner' => $this->student?->full_name ?? $this->teacher?->full_name ?? 'Établissement',
            'student_id' => $this->student_id,
            'size' => self::humanSize($this->size),
            'mime' => $this->mime,
            'uploaded_at' => $this->created_at?->toDateString(),
            'uploaded_by' => $this->uploader?->name,
            'download_url' => url('/api/v1/documents/'.$this->id.'/download'),
        ];
    }

    public static function humanSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', ' ').' Mo';
        }

        return max(1, (int) round($bytes / 1024)).' Ko';
    }
}
