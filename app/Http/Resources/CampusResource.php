<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CampusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'address' => $this->address,
            'city' => $this->city,
            'phone' => $this->phone,
            'email' => $this->email,
            'manager' => $this->manager,
            'status' => $this->status,
            'logo_url' => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null,
            'students_count' => (int) ($this->students_count ?? 0),
            'classes_count' => (int) ($this->class_rooms_count ?? 0),
            'teachers_count' => (int) ($this->teachers_count ?? 0),
        ];
    }
}
