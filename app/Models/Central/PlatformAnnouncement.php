<?php

namespace App\Models\Central;

class PlatformAnnouncement extends CentralModel
{
    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }
}
