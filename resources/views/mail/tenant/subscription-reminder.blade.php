<x-mail::message>
# Bonjour {{ $adminName }},

@if ($kind === 'expired')
Le délai de grâce de **{{ $schoolName }}** est terminé : l’accès est suspendu pour toute l’équipe et pour les parents. Vous seul pouvez encore vous connecter, pour renouveler l’abonnement. L’accès est rétabli dès le paiement confirmé.
@elseif ($kind === 'grace')
L’{{ $period }} de **{{ $schoolName }}** a pris fin le {{ $endsAt }}. Votre école reste accessible jusqu’au **{{ $graceEndsAt }}** ; au-delà, l’accès sera suspendu jusqu’au paiement.
@else
L’{{ $period }} de **{{ $schoolName }}** se termine le **{{ $endsAt }}** (dans {{ $daysLeft }} jour(s)). Renouvelez dès maintenant pour éviter toute interruption.
@endif

<x-mail::button :url="$billingUrl">
Renouveler l’abonnement
</x-mail::button>

Le paiement se fait en ligne par Mobile Money ou carte bancaire (FedaPay).

L’équipe {{ config('app.name') }}
</x-mail::message>
