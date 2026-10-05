<?php

use Illuminate\Support\Facades\Schedule;

// Statistiques plateforme (instantané quotidien de chaque école)
Schedule::command('platform:collect-usage')->dailyAt('02:00')->withoutOverlapping();

// Abonnements arrivés à échéance → expirés / suspendus après le délai de grâce
Schedule::command('subscriptions:check')->dailyAt('03:00');

// Nettoyage des jetons mobiles expirés dans chaque école
Schedule::command('tenants:prune-tokens')->weekly();
