<?php

return [

    // Préfixe ajouté aux jetons des applications mobiles : t_{code}.{jeton}
    'token_prefix' => 't_',

    'parent_codes' => [
        // Alphabet sans caractères ambigus (0/O, 1/I/L)
        'alphabet' => 'ABCDEFGHJKMNPQRSTUVWXYZ23456789',
        'length' => 12,
        'group' => 4,
    ],

    'sms' => [
        // log (développement) | http (passerelle générique) | orange | twilio | africastalking
        'driver' => env('SMS_DRIVER', 'log'),
        'sender' => env('SMS_SENDER', 'ARDOISE'),
        // Indicatif ajouté aux numéros saisis sans « + » (ex. 229 Bénin, 225 Côte d'Ivoire)
        'default_country_code' => env('SMS_DEFAULT_COUNTRY_CODE', '229'),
        'http' => [
            'url' => env('SMS_HTTP_URL'),
            'method' => env('SMS_HTTP_METHOD', 'post'),
            'format' => env('SMS_HTTP_FORMAT', 'json'),
            'auth' => env('SMS_HTTP_AUTH', 'bearer'),
            'token' => env('SMS_HTTP_TOKEN'),
            'auth_key' => env('SMS_HTTP_AUTH_KEY', 'X-API-Key'),
            'fields' => env('SMS_HTTP_FIELDS', 'to:to,from:from,message:message'),
            'extra' => env('SMS_HTTP_EXTRA'),
            'success_path' => env('SMS_HTTP_SUCCESS_PATH'),
            'success_value' => env('SMS_HTTP_SUCCESS_VALUE'),
            'timeout' => (int) env('SMS_HTTP_TIMEOUT', 10),
        ],
        'orange' => [
            'client_id' => env('SMS_ORANGE_CLIENT_ID'),
            'client_secret' => env('SMS_ORANGE_CLIENT_SECRET'),
            'sender_number' => env('SMS_ORANGE_SENDER_NUMBER'),
            'sender_name' => env('SMS_ORANGE_SENDER_NAME'),
        ],
        'twilio' => [
            'sid' => env('SMS_TWILIO_SID'),
            'token' => env('SMS_TWILIO_TOKEN'),
            'from' => env('SMS_TWILIO_FROM'),
            'messaging_service' => env('SMS_TWILIO_MESSAGING_SERVICE'),
        ],
        'africastalking' => [
            'username' => env('SMS_AT_USERNAME'),
            'api_key' => env('SMS_AT_API_KEY'),
            'from' => env('SMS_AT_FROM'),
            'sandbox' => (bool) env('SMS_AT_SANDBOX', false),
        ],
    ],

    'payments' => [
        // manual : paiement au guichet / instructions ; agrégateur à brancher plus tard
        'gateway' => env('PAYMENT_GATEWAY', 'manual'),
    ],

    /*
     * FedaPay — compte de la PLATEFORME : encaisse les abonnements des écoles.
     * Les frais scolaires payés par les parents vont sur le compte FedaPay de
     * chaque école (clés saisies dans Paramètres → Paiement en ligne).
     */
    'fedapay' => [
        'environment' => env('FEDAPAY_ENVIRONMENT', 'sandbox'), // sandbox | live
        'public_key' => env('FEDAPAY_PUBLIC_KEY'),
        'secret_key' => env('FEDAPAY_SECRET_KEY'),
        'webhook_secret' => env('FEDAPAY_WEBHOOK_SECRET'),
        'currency' => env('FEDAPAY_CURRENCY', 'XOF'),
        'country' => env('FEDAPAY_COUNTRY', 'bj'),
        'timeout' => (int) env('FEDAPAY_TIMEOUT', 20),
        // Tolérance (secondes) sur l'horodatage des webhooks signés
        'webhook_tolerance' => (int) env('FEDAPAY_WEBHOOK_TOLERANCE', 300),
    ],

    'billing' => [
        // Rappels envoyés à l'administrateur N jours avant la fin de l'essai / de l'abonnement
        'reminder_days' => array_map('intval', explode(',', (string) env('BILLING_REMINDER_DAYS', '7,3,1'))),
        'max_periods' => 24,
    ],

    /*
     * Sauvegarde quotidienne de la base centrale et de chaque base d'école
     * (php artisan backup:run, planifiée à 01:30). Fichiers compressés .gz.
     */
    'backup' => [
        // Disque Laravel (config/filesystems.php) : local, s3…
        'disk' => env('BACKUP_DISK', 'local'),
        'path' => env('BACKUP_PATH', 'backups'),
        // Nombre de jours de sauvegardes conservées
        'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),
        'mysqldump' => env('BACKUP_MYSQLDUMP', 'mysqldump'),
        'pg_dump' => env('BACKUP_PG_DUMP', 'pg_dump'),
        'timeout' => (int) env('BACKUP_TIMEOUT', 1800),
    ],

    'superadmin' => [
        'name' => env('SUPERADMIN_NAME', 'Équipe Ardoise'),
        'email' => env('SUPERADMIN_EMAIL', 'admin@ardoise.test'),
        'password' => env('SUPERADMIN_PASSWORD'),
    ],
];
