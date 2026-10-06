<?php

use Illuminate\Support\Facades\Schedule;

// Statistiques plateforme (instantané quotidien de chaque école)
Schedule::command('platform:collect-usage')->dailyAt('02:00')->withoutOverlapping();

// Abonnements : statut et rappels de renouvellement (J-7, J-3, J-1, délai de grâce, blocage)
Schedule::command('subscriptions:check')->dailyAt('08:00');

// Paiements FedaPay restés « en attente » (webhook perdu) : relecture auprès de l'API
Schedule::command('payments:sync-fedapay')->everyFifteenMinutes()->withoutOverlapping();

// Nettoyage des jetons mobiles expirés dans chaque école
Schedule::command('tenants:prune-tokens')->weekly();
