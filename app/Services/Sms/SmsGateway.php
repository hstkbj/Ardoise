<?php

namespace App\Services\Sms;

interface SmsGateway
{
    /** Envoie un SMS ; retourne vrai si le fournisseur l'a accepté. */
    public function send(string $to, string $message): bool;
}
