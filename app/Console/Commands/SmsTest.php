<?php

namespace App\Console\Commands;

use App\Services\Sms\SmsGateway;
use Illuminate\Console\Command;

/** Vérifie la configuration SMS en envoyant un message de test. */
class SmsTest extends Command
{
    protected $signature = 'sms:test {to : Numéro au format international, ex. +2250700000000} {--message= : Texte du message}';

    protected $description = 'Envoie un SMS de test avec le fournisseur configuré (SMS_DRIVER)';

    public function handle(SmsGateway $sms): int
    {
        $driver = (string) config('ardoise.sms.driver');
        $message = (string) ($this->option('message') ?: config('app.name').' : SMS de test. La configuration fonctionne.');

        if ($sms->send((string) $this->argument('to'), $message)) {
            $this->info("SMS accepté par le fournisseur « {$driver} ».".($driver === 'log' ? ' (pilote log : message écrit dans storage/logs)' : ''));

            return self::SUCCESS;
        }

        $this->error("Échec de l’envoi avec « {$driver} » : consultez storage/logs/laravel.log.");

        return self::FAILURE;
    }
}
