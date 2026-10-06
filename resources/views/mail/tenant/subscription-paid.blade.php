<x-mail::message>
# Merci {{ $adminName }} !

Nous avons bien reçu le paiement de l’abonnement de **{{ $schoolName }}**.

<x-mail::panel>
**Référence** : {{ $reference }}
**Montant** : {{ $amount }} FCFA
@if ($planName)
**Formule** : {{ $planName }}
@endif
**Abonnement valable jusqu’au** : {{ $expiresAt }}
</x-mail::panel>

<x-mail::button :url="$billingUrl">
Voir mon abonnement
</x-mail::button>

L’équipe {{ config('app.name') }}
</x-mail::message>
