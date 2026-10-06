<x-mail::message>
# Bonjour {{ $adminName }},

@if ($isReset)
Voici vos nouveaux identifiants pour administrer **{{ $schoolName }}** sur {{ config('app.name') }}. L’ancien mot de passe ne fonctionne plus.
@else
L’espace de **{{ $schoolName }}** est prêt sur {{ config('app.name') }}. Vous en êtes l’administrateur : vous pouvez dès maintenant inscrire vos élèves, ajouter vos enseignants et inviter votre équipe.
@endif

<x-mail::panel>
**Adresse de votre école** : [{{ $domain }}]({{ $loginUrl }})
**Identifiant** : {{ $email }}
**Mot de passe provisoire** : `{{ $password }}`
**Code établissement** : {{ $schoolCode }}
</x-mail::panel>

<x-mail::button :url="$loginUrl">
Se connecter
</x-mail::button>

Pour votre sécurité, changez ce mot de passe dès votre première connexion (menu **Mon profil**).

@if ($planName)
**Formule** : {{ $planName }}@if ($trialEndsAt) — période d’essai jusqu’au {{ $trialEndsAt }}@elseif ($expiresAt) — abonnement valable jusqu’au {{ $expiresAt }}@endif.
@endif

Vous pourrez consulter et renouveler votre abonnement à tout moment depuis **Administration → Abonnement**.

À bientôt,<br>
L’équipe {{ config('app.name') }}
</x-mail::message>
