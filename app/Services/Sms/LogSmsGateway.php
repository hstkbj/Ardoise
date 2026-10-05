<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/** Pilote de développement : les SMS sont écrits dans storage/logs. */
class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): bool
    {
        Log::channel(config('logging.default'))->info('[SMS] '.$to.' : '.$message, ['tenant' => tenant()?->code]);

        return true;
    }
}
