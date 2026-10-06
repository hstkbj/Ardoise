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
        // log (développement) | http (fournisseur à configurer)
        'driver' => env('SMS_DRIVER', 'log'),
        'sender' => env('SMS_SENDER', 'ARDOISE'),
        'http' => [
            'url' => env('SMS_HTTP_URL'),
            'token' => env('SMS_HTTP_TOKEN'),
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

    'superadmin' => [
        'name' => env('SUPERADMIN_NAME', 'Équipe Ardoise'),
        'email' => env('SUPERADMIN_EMAIL', 'admin@ardoise.test'),
        'password' => env('SUPERADMIN_PASSWORD'),
    ],
];
