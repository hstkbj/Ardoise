<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->data;

        return [
            'id' => $this->id,
            'title' => $data['title'] ?? '',
            'body' => $data['body'] ?? '',
            'type' => $data['type'] ?? 'system',
            'channel' => $data['channel'] ?? 'in_app',
            'link' => $data['link'] ?? null,
            'created_at' => $this->created_at,
            'read' => $this->read_at !== null,
        ];
    }
}
