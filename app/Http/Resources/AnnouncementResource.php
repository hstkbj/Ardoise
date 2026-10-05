<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    protected const AUDIENCES = ['school' => 'Établissement', 'class' => 'Classe', 'teachers' => 'Enseignants', 'parents' => 'Parents', 'students' => 'Élèves'];

    protected const CHANNELS = ['in_app' => 'In-app', 'email' => 'E-mail', 'sms' => 'SMS', 'push' => 'Push'];

    public function toArray(Request $request): array
    {
        $audiences = collect($this->audiences ?? [])->map(fn ($a) => self::AUDIENCES[$a] ?? $a)->implode(', ');
        $scope = $this->classRoom?->name ?? $this->campus?->name;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'audiences' => $this->audiences ?? [],
            'audience' => trim($audiences.($scope ? ' · '.$scope : '')) ?: 'Tous',
            'school_id' => $this->campus_id,
            'class_id' => $this->class_room_id,
            'channels' => collect($this->channels ?? [])->map(fn ($c) => self::CHANNELS[$c] ?? $c)->implode(', '),
            'channel_keys' => $this->channels ?? [],
            'author' => $this->author?->name ?? 'Direction',
            'published_at' => $this->published_at?->toDateString(),
            'status' => $this->status,
        ];
    }
}
