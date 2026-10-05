<?php

namespace App\Models\Central;

class PlatformSetting extends CentralModel
{
    protected function casts(): array
    {
        return ['values' => 'array'];
    }
}
