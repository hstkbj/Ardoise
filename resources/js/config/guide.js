/**
 * Contenu du guide d'utilisation (page /guide).
 *
 * Un « public » par espace de l'application ; chaque section décrit une tâche :
 *  - where : chemin dans les menus (affiché en fil d'Ariane) ;
 *  - steps : étapes numérotées ; tips : encadrés (tone : info | warning | success).
 * Mise en forme légère dans les textes : **gras** et `code`.
 * `platformOnly` : public visible seulement par l'équipe de la plateforme.
 */

export const GUIDE = [
  // ───────────────────────────────────────────────────────────── École
  {
    key: 'ecole',
    label: 'Administration de l’école',
    short: 'École',
    icon: 'building',
    tint: 'bg-mint',
    accent: 'text-brand-700',
    who: 'Direction, secrétariat, comptabilité, responsables pédagogiques',
    intro: 'Tout ce qu’il faut pour configurer l’école, inscrire les élèves, suivre les notes, les absences et les paiements, et gérer l’abonnement.',
    sections: [
      {
        id: 'premiers-pas',
        title: 'Premiers pas',
        icon: 'home',
        where: ['Connexion'],
        intro: 'À la création de votre école, l’administrateur reçoit un e-mail avec l’adresse de l’école, son identifiant et un mot de passe provisoire.',
        steps: [
          'Ouvrez l’adresse reçue, par exemple **https://votre-ecole.ardoise.app/login**.',
          'Choisissez le profil **Établissement**, saisissez votre e-mail et le mot de passe provisoire.',
          'Changez tout de suite le mot de passe : menu en haut à droite → **Mon profil** → Mot de passe.',
          'Le menu de gauche regroupe les modules par thème (Pilotage, Scolarité, Pédagogie, Communication, Administration).',
          'Si l’école a plusieurs sites, le sélecteur en haut de page limite le tableau de bord et les listes à un établissement.',
        ],
        tips: [
          { tone: 'info', text: 'Depuis l’adresse générale de la plateforme, la page de connexion demande en plus le **code établissement** (indiqué dans l’e-mail de bienvenue).' },
          { tone: 'warning', text: 'E-mail perdu ? L’équipe de la plateforme peut renvoyer de nouveaux identifiants ; l’ancien mot de passe ne fonctionnera plus.' },
        ],
      },
      {
        id: 'configuration',
        title: 'Configurer l’école',
        icon: 'sliders',
        where: ['Administration', 'Paramètres'],
        intro: 'À faire une fois, avant la rentrée. Les réglages sont rangés par onglets.',
        steps: [
          '**Identité** et **Coordonnées** : nom, logo, couleur, adresse, téléphone. Ils apparaissent sur les bulletins et les reçus.',
          '**Année scolaire** : trimestres ou semestres, jours de cours.',
          '**Notation** : note maximale, moyenne de passage, arrondi des moyennes.',
          '**Bulletins** : afficher ou non le rang et la moyenne de classe, décisions du conseil proposées.',
          '**Finances** : devise, préfixe des reçus, moyens de paiement, délai avant retard.',
          'Dans **Pilotage → Années scolaires**, vérifiez l’année active (« Définir comme année active »). Une année clôturée n’accepte plus de notes ni d’absences.',
          'Créez vos **Établissements** (sites), puis les **Matières** (coefficient par défaut) et les **Classes** (niveau, salle, professeur principal, capacité).',
        ],
        tips: [
          { tone: 'info', text: 'À la création d’une classe, les matières de son cycle lui sont rattachées automatiquement. Les coefficients restent modifiables par classe.' },
        ],
      },
      {
        id: 'equipe',
        title: 'Équipe, rôles et permissions',
        icon: 'shield',
        where: ['Administration', 'Utilisateurs'],
        intro: 'Chaque membre du personnel a un compte et un rôle qui détermine ce qu’il voit.',
        steps: [
          '**Scolarité → Enseignants → Ajouter un enseignant** : nom, e-mail, matières, établissements. Avec un e-mail, l’enseignant reçoit un lien pour choisir son mot de passe.',
          '**Administration → Utilisateurs → Inviter un utilisateur** pour le personnel (directeur, comptable, secrétariat…).',
          '**Administration → Rôles et permissions** : cochez ce que chaque rôle peut consulter, créer, modifier, supprimer ou publier.',
          'Pour suspendre un compte : liste des utilisateurs → menu « ⋯ » → Activer / désactiver.',
        ],
        tips: [
          { tone: 'info', text: 'Le rôle **Administrateur** a toujours tous les droits et n’est pas modifiable. Un enseignant ne voit que ses classes.' },
        ],
      },
      {
        id: 'eleves',
        title: 'Inscrire et suivre les élèves',
        icon: 'users',
        where: ['Scolarité', 'Élèves'],
        steps: [
          'Cliquez sur **Inscrire un élève** : identité, classe, puis le parent ou tuteur principal (nom, téléphone, lien).',
          'Le matricule est généré automatiquement si vous le laissez vide. Les frais de la classe sont affectés à l’élève.',
          'À l’enregistrement, si un nouveau parent est créé, son **code d’accès** s’affiche : notez-le ou imprimez la fiche du parent.',
          'La fiche élève regroupe l’aperçu, le bulletin, les absences et les paiements.',
          'Depuis la fiche : **Changer de classe**, **Transférer** ou **Réactiver**. Depuis la liste, cochez plusieurs élèves pour les transférer ou les archiver ensemble.',
        ],
        tips: [
          { tone: 'info', text: 'Un parent déjà enregistré avec le même numéro de téléphone est réutilisé : ses enfants sont regroupés sur un seul compte.' },
        ],
      },
      {
        id: 'parents',
        title: 'Parents et codes d’accès',
        icon: 'key',
        where: ['Scolarité', 'Parents'],
        intro: 'Les parents n’ont pas de mot de passe : ils se connectent avec un code personnel que l’école leur remet.',
        steps: [
          'Ouvrez la fiche du parent : le code s’affiche en grand (format **XXXX-XXXX-XXXX**).',
          '**Imprimer la fiche** produit une feuille à remettre au parent, avec le mode d’emploi.',
          '**Nouveau code** en cas de perte : l’ancien code ne fonctionne plus et le parent est déconnecté de tous ses appareils.',
          '**Désactiver l’accès** bloque la connexion sans supprimer le parent ; **Réactiver l’accès** la rétablit.',
          'Pour rattacher d’autres enfants : **Modifier** → Enfants associés.',
        ],
        tips: [
          { tone: 'warning', text: 'Remettez le code en main propre ou par un canal sûr : il donne accès aux notes et aux paiements des enfants.' },
        ],
      },
      {
        id: 'notes',
        title: 'Évaluations et saisie des notes',
        icon: 'pencil',
        where: ['Pédagogie', 'Saisie des notes'],
        steps: [
          '**Pédagogie → Évaluations → Nouvelle évaluation** : intitulé, type, date, classe, matière, coefficient, note maximale.',
          'Ouvrez **Saisie des notes**, filtrez par classe et période, puis choisissez l’évaluation.',
          'Tapez les notes au clavier : **Entrée** ou **↓** passe à l’élève suivant, la virgule est acceptée (14,5). Cochez « Absent » si besoin.',
          '**Enregistrer le brouillon** à tout moment : les parents ne voient rien.',
          '**Valider et publier** quand chaque élève a une note ou est absent : les notes sont verrouillées, visibles par les parents, qui sont notifiés.',
        ],
        tips: [
          { tone: 'info', text: 'Les moyennes sont ramenées sur 20 et pondérées par les coefficients ; un élève absent n’est pas compté.' },
          { tone: 'warning', text: 'Corriger une note validée : la direction clique sur **Déverrouiller** (action enregistrée), corrige, puis valide à nouveau.' },
        ],
      },
      {
        id: 'bulletins',
        title: 'Bulletins',
        icon: 'file',
        where: ['Pédagogie', 'Bulletins'],
        steps: [
          'Choisissez la classe et la période. Tant qu’ils ne sont pas générés, la liste montre un aperçu provisoire.',
          '**Générer** calcule moyennes, rangs et appréciations à partir des notes validées. **Recalculer** après une correction.',
          'Ouvrez un bulletin pour saisir l’appréciation du professeur principal et la décision du conseil.',
          '**Publier** (tous ou la sélection) : les parents sont prévenus et peuvent télécharger le PDF.',
          '**Imprimer** ou **PDF** depuis le bulletin d’un élève.',
        ],
      },
      {
        id: 'absences',
        title: 'Absences, retards et emploi du temps',
        icon: 'check-circle',
        where: ['Pédagogie', 'Absences'],
        steps: [
          'Choisissez la classe, la date et la séance (les créneaux viennent de l’emploi du temps).',
          'Marquez chaque élève Présent, Absent ou Retard (minutes). **Tous présents** remet la liste à zéro.',
          '**Enregistrer l’appel** : les parents des absents et des retardataires sont notifiés.',
          '**Historique** : filtrez par classe, type ou période, et **Justifier** une absence avec un motif.',
          '**Pédagogie → Emploi du temps** : choisissez une classe et **Ajouter un cours**. Les chevauchements (classe, enseignant, salle) sont refusés.',
        ],
      },
      {
        id: 'communication',
        title: 'Devoirs, annonces et documents',
        icon: 'megaphone',
        where: ['Communication', 'Annonces'],
        steps: [
          '**Pédagogie → Devoirs → Nouveau devoir** : consignes, date limite, pièces jointes. Publié, il apparaît chez les parents de la classe.',
          '**Communication → Annonces** : choisissez les destinataires (établissement, classe, enseignants, parents) et les canaux.',
          '**Administration → Documents** : partagez un fichier avec un élève ou avec tous les parents (catégorie « Administratif »).',
          'Les demandes de documents envoyées par les parents arrivent dans **Notifications**.',
        ],
      },
      {
        id: 'paiements',
        title: 'Frais scolaires et encaissements',
        icon: 'wallet',
        where: ['Administration', 'Paiements'],
        steps: [
          '**Frais scolaires → Définir des frais** : montant total, niveaux concernés, nombre d’échéances, première échéance. Les échéances de chaque élève sont créées automatiquement.',
          '**Paiements → Enregistrer un paiement** : élève, frais, montant reçu, méthode. Le montant est réparti sur les échéances les plus anciennes.',
          'Le reçu PDF est disponible depuis la ligne (menu « ⋯ » → Reçu) ou la fiche de l’échéance.',
          'Filtrez par statut **En retard** pour relancer les familles. Les chiffres clés sont en haut de la page.',
          'Paiements en ligne sans FedaPay : le filtre « En ligne : À confirmer » liste les demandes à **Confirmer** ou **Refuser** après réception de l’argent.',
        ],
      },
      {
        id: 'paiement-en-ligne',
        title: 'Paiement en ligne des frais (FedaPay)',
        icon: 'smartphone',
        where: ['Administration', 'Paramètres', 'Paiement en ligne'],
        intro: 'Les parents paient par Mobile Money ou carte ; l’argent arrive directement sur le compte FedaPay de l’école.',
        steps: [
          'Créez un compte sur **fedapay.com** au nom de l’école (testez d’abord en mode sandbox).',
          'Dans FedaPay → Paramètres → Clés API, copiez la clé publique et la clé secrète dans l’onglet **Paiement en ligne**.',
          'Copiez l’**adresse du webhook** affichée dans l’onglet, déclarez-la dans FedaPay (événements `transaction.*`), puis collez le secret du webhook.',
          'Choisissez **Activé (FedaPay)** et enregistrez. Le bouton **Payer** des parents ouvre désormais la page FedaPay.',
          'Chaque paiement confirmé met à jour l’échéance, envoie le reçu au parent et prévient la comptabilité.',
        ],
        tips: [
          { tone: 'info', text: 'Les clés sont chiffrées et ne sont jamais réaffichées : laissez un champ vide pour conserver la valeur enregistrée.' },
          { tone: 'warning', text: 'Ce module dépend de votre abonnement (« Paiement en ligne des frais »).' },
        ],
      },
      {
        id: 'notifications',
        title: 'Qui reçoit quelles notifications',
        icon: 'bell',
        where: ['Administration', 'Paramètres', 'Destinataires'],
        intro: 'Pour chaque événement, vous choisissez qui est prévenu et par quel canal.',
        steps: [
          'Événements : absence ou retard, justificatif envoyé, notes validées, bulletin publié, nouveau devoir, paiement encaissé, paiement en ligne reçu ou à confirmer, demande de document.',
          'Destinataires possibles : parents de l’élève, professeur principal, enseignants de la classe, administrateurs, direction, responsables pédagogiques, comptabilité, secrétariat.',
          'Canaux : **Application** (cloche), **E-mail**, **SMS**. Cochez puis **Enregistrer**.',
          'Chacun retrouve ses notifications sous la cloche en haut de page ; « Tout marquer comme lu » vide le compteur.',
        ],
        tips: [
          { tone: 'info', text: 'Les envois se font en arrière-plan : quelques secondes peuvent s’écouler. Le SMS n’est envoyé que si votre abonnement inclut les notifications SMS.' },
        ],
      },
      {
        id: 'abonnement',
        title: 'Abonnement de l’école',
        icon: 'refresh',
        where: ['Administration', 'Abonnement'],
        intro: 'Visible par l’administrateur et la direction : formule, modules inclus, consommation et paiement.',
        steps: [
          'La page indique la formule, la date de fin, les jours restants et la consommation (élèves, comptes, établissements) par rapport aux limites.',
          'Un bandeau orange apparaît 7 jours avant la fin ; un bandeau rouge pendant le délai de grâce (15 jours après l’échéance).',
          'Pour payer : choisissez une formule → **Choisir** ou **Renouveler** → durée → **Payer avec FedaPay**. Au retour, le paiement est vérifié et l’abonnement prolongé.',
          'Après le délai de grâce, l’école est **bloquée** : enseignants, personnel et parents ne peuvent plus se connecter ; l’administrateur se connecte uniquement pour payer.',
        ],
        tips: [
          { tone: 'info', text: 'Un menu absent signifie que le module n’est pas inclus dans votre formule. Le message « Limite de votre plan atteinte » indique qu’il faut changer de formule pour ajouter des élèves ou des comptes.' },
          { tone: 'warning', text: 'Formule « Sur devis » : le paiement en ligne n’est pas proposé, contactez le support.' },
        ],
      },
      {
        id: 'support',
        title: 'Support',
        icon: 'help',
        where: ['Administration', 'Support'],
        steps: [
          '**Nouvelle demande** : sujet, catégorie, priorité, description.',
          'Suivez la réponse de l’équipe dans le fil de la demande et répondez directement.',
        ],
      },
    ],
    faq: [
      ['Un menu a disparu (Paiements, Emploi du temps…).', 'Le module n’est pas inclus dans la formule de l’école, ou votre rôle n’a pas la permission « Consulter ». Voir la page Abonnement ou Rôles et permissions.'],
      ['Un enseignant ne voit pas une classe.', 'Il doit être professeur principal de la classe ou enseignant d’une de ses matières (fiche de la classe → matières).'],
      ['Un parent a perdu son code.', 'Fiche du parent → Nouveau code, puis réimprimez la fiche. L’ancien code est désactivé.'],
      ['Une note est fausse après validation.', 'La direction déverrouille l’évaluation, la note est corrigée, puis l’évaluation est validée de nouveau.'],
      ['Les parents ne reçoivent pas les e-mails.', 'Vérifiez l’adresse e-mail du parent et les canaux cochés dans Paramètres → Destinataires. L’application (cloche) reçoit toujours la notification.'],
    ],
  },

  // ─────────────────────────────────────────────────────── Enseignant
  {
    key: 'enseignant',
    label: 'Enseignants',
    short: 'Enseignant',
    icon: 'user',
    tint: 'bg-sky',
    accent: 'text-info-700',
    who: 'Professeurs principaux et enseignants',
    intro: 'Faire l’appel, saisir les notes, donner des devoirs et suivre ses classes, depuis un ordinateur ou un téléphone.',
    sections: [
      {
        id: 'connexion-enseignant',
        title: 'Se connecter',
        icon: 'lock',
        where: ['Connexion'],
        steps: [
          'Ouvrez le lien reçu par e-mail pour choisir votre mot de passe.',
          'Ensuite, connectez-vous avec le profil **Établissement**, votre e-mail ou téléphone et votre mot de passe.',
          'Mot de passe oublié : **Mot de passe oublié ?** sur la page de connexion. Par e-mail vous recevez un lien, par téléphone un code à 6 chiffres.',
        ],
      },
      {
        id: 'tableau-de-bord',
        title: 'Votre tableau de bord',
        icon: 'dashboard',
        where: ['Mon espace', 'Tableau de bord'],
        steps: [
          '**Mes cours aujourd’hui** : chaque cours de la journée, avec un bouton **Appel** tant qu’il n’est pas fait.',
          '**Notes à saisir** : vos évaluations incomplètes, avec l’avancement.',
          '**Devoirs récents** et chiffres clés (classes, élèves, absences de la semaine).',
        ],
      },
      {
        id: 'appel',
        title: 'Faire l’appel',
        icon: 'check-circle',
        where: ['Pédagogie', 'Appel / absences'],
        steps: [
          'Choisissez la classe, la date et la séance.',
          'Tout le monde est « Présent » par défaut : marquez seulement les absents et les retards (avec les minutes).',
          '**Enregistrer l’appel**. Vous pouvez le modifier ensuite : le bouton devient « Mettre à jour l’appel ».',
        ],
        tips: [{ tone: 'info', text: 'Les parents des élèves absents ou en retard sont prévenus automatiquement.' }],
      },
      {
        id: 'notes-enseignant',
        title: 'Évaluations et notes',
        icon: 'pencil',
        where: ['Pédagogie', 'Saisie des notes'],
        steps: [
          '**Évaluations → Nouvelle évaluation** : intitulé, type, date, classe, matière, coefficient, note maximale.',
          'Dans **Saisie des notes**, choisissez l’évaluation et tapez les notes ; **Entrée** passe à l’élève suivant.',
          '**Enregistrer le brouillon** pour continuer plus tard.',
          '**Valider et publier** quand tout est saisi : les notes deviennent visibles par les parents et ne sont plus modifiables.',
        ],
        tips: [{ tone: 'warning', text: 'Une erreur après validation ? Demandez à la direction de déverrouiller l’évaluation.' }],
      },
      {
        id: 'devoirs-enseignant',
        title: 'Donner un devoir',
        icon: 'list',
        where: ['Pédagogie', 'Devoirs'],
        steps: [
          '**Nouveau devoir** : matière, classe, date limite, consignes et pièces jointes.',
          'Statut **Publié** : les parents de la classe le voient et sont prévenus. **Brouillon** pour le préparer à l’avance.',
          'Les parents cochent le devoir quand leur enfant l’a terminé.',
        ],
      },
      {
        id: 'classes-enseignant',
        title: 'Classes, élèves et emploi du temps',
        icon: 'layers',
        where: ['Mon espace', 'Mes classes'],
        steps: [
          '**Mes classes** : les classes dont vous êtes professeur principal ou dans lesquelles vous enseignez.',
          '**Mes élèves** : la fiche de chaque élève (bulletin, absences).',
          '**Emploi du temps** : votre semaine de cours.',
          '**Notifications** : justificatifs d’absence, annonces, messages de la direction.',
        ],
      },
    ],
    faq: [
      ['Je ne vois pas une classe.', 'Demandez à la direction de vous affecter à une matière de la classe ou comme professeur principal.'],
      ['Le bouton « Valider » refuse.', 'Chaque élève doit avoir une note (entre 0 et la note maximale) ou être marqué absent.'],
      ['Un menu n’apparaît pas.', 'Le module n’est pas inclus dans l’abonnement de l’école.'],
    ],
  },

  // ─────────────────────────────────────────────────────────── Parent
  {
    key: 'parent',
    label: 'Parents',
    short: 'Parent',
    icon: 'home',
    tint: 'bg-sun-soft',
    accent: 'text-warn-700',
    who: 'Parents et tuteurs',
    intro: 'Suivre la scolarité de vos enfants depuis votre téléphone : notes, bulletins, absences, devoirs et paiements.',
    sections: [
      {
        id: 'connexion-parent',
        title: 'Se connecter avec votre code',
        icon: 'key',
        where: ['Connexion', 'Parent'],
        intro: 'Pas de mot de passe : l’école vous remet un code personnel.',
        steps: [
          'Ouvrez l’application ou la page de connexion et choisissez le profil **Parent**.',
          'Saisissez votre code, par exemple **K7QM-4XTR-9HCP** (majuscules, minuscules ou sans tirets : tout est accepté).',
          'Touchez **Se connecter** : l’application retrouve automatiquement l’école et vos enfants.',
        ],
        tips: [
          { tone: 'warning', text: 'Ne partagez pas votre code. En cas de perte, demandez-en un nouveau au secrétariat : l’ancien sera désactivé.' },
        ],
      },
      {
        id: 'enfants',
        title: 'Choisir un enfant et l’accueil',
        icon: 'users',
        where: ['Accueil'],
        steps: [
          'Si vous avez plusieurs enfants, touchez son prénom dans le bandeau en haut de chaque page.',
          'L’accueil résume la moyenne et le rang, les absences, les devoirs à faire et le reste à payer.',
          'Sur téléphone, la barre en bas donne accès à l’Accueil, aux Enfants, aux Notes, aux Devoirs et aux Alertes.',
        ],
      },
      {
        id: 'notes-parent',
        title: 'Notes et bulletins',
        icon: 'file',
        where: ['Notes', 'Bulletins'],
        steps: [
          '**Notes** : chaque note validée, avec la moyenne de la classe et le commentaire de l’enseignant. Filtrez par matière.',
          '**Bulletins** : ouvrez un bulletin publié et **Télécharger le PDF**.',
        ],
        tips: [{ tone: 'info', text: 'Une note apparaît dès que l’enseignant la valide ; vous recevez une alerte.' }],
      },
      {
        id: 'absences-parent',
        title: 'Absences : envoyer un justificatif',
        icon: 'check-circle',
        where: ['Absences'],
        steps: [
          'Les absences et retards de la période sont listés.',
          'Touchez **Justifier**, indiquez le motif et ajoutez si besoin une photo ou un PDF (certificat médical…).',
          'L’école valide le justificatif ; l’absence passe alors en « Justifiée ».',
        ],
      },
      {
        id: 'devoirs-parent',
        title: 'Devoirs et emploi du temps',
        icon: 'list',
        where: ['Devoirs'],
        steps: [
          'Les devoirs donnés récemment, avec la date limite et les consignes.',
          'Cochez un devoir quand votre enfant l’a terminé : l’enseignant le voit.',
          '**Emploi du temps** : la semaine de cours (jour par jour sur téléphone).',
        ],
      },
      {
        id: 'payer',
        title: 'Payer les frais scolaires',
        icon: 'wallet',
        where: ['Paiements'],
        steps: [
          'La page affiche le reste à payer et chaque échéance (payée, en attente, en retard).',
          'Touchez **Payer**, vérifiez le montant (vous pouvez payer une partie), puis **Continuer**.',
          'Si l’école a activé le paiement en ligne, vous êtes redirigé vers **FedaPay** : Mobile Money ou carte bancaire. Vous revenez ensuite automatiquement dans l’application.',
          'Sinon, votre demande est transmise à la comptabilité, qui la confirme à réception du paiement.',
          'Le **Reçu** se télécharge depuis l’échéance dès qu’un montant est payé.',
        ],
      },
      {
        id: 'autres-parent',
        title: 'Annonces, documents et alertes',
        icon: 'bell',
        where: ['Annonces', 'Documents', 'Alertes'],
        steps: [
          '**Annonces** : messages de l’école (réunions, sorties, congés…).',
          '**Documents** : fichiers partagés par l’école ; **Demander un document** (certificat de scolarité, attestation…).',
          '**Alertes** : toutes vos notifications. **Profil** : vos coordonnées.',
        ],
      },
    ],
    faq: [
      ['« Code invalide ».', 'Vérifiez les 12 caractères (il n’y a ni O ni 0, ni I ni 1). Si le problème persiste, le code a peut-être été renouvelé : contactez le secrétariat.'],
      ['« L’accès à cet établissement est suspendu ».', 'L’école doit régulariser son abonnement. Contactez-la.'],
      ['Mon paiement FedaPay n’apparaît pas.', 'La confirmation peut prendre quelques minutes. Gardez la référence du paiement et contactez la comptabilité si besoin.'],
      ['Un menu n’apparaît pas.', 'Le service n’est pas proposé par l’école de votre enfant.'],
    ],
  },

  // ─────────────────────────────────────────────────────── Plateforme
  {
    key: 'plateforme',
    label: 'Console plateforme',
    short: 'Plateforme',
    icon: 'shield',
    tint: 'bg-lavender',
    accent: 'text-lavender-700',
    who: 'Équipe Ardoise (superadmin)',
    platformOnly: true,
    intro: 'Mettre en service la plateforme, créer les écoles, gérer les formules, les abonnements, FedaPay et les envois en arrière-plan.',
    sections: [
      {
        id: 'mise-en-service',
        title: 'Mise en service',
        icon: 'sliders',
        where: ['Serveur', '.env'],
        steps: [
          '`APP_URL` et `TENANCY_BASE_DOMAIN` : l’adresse de la plateforme et le domaine des écoles (`{code}.votredomaine`). Un DNS générique `*.votredomaine` doit pointer vers le serveur.',
          '`MAIL_*` : un vrai serveur SMTP (Brevo, Mailgun…) et une adresse d’expédition de votre domaine.',
          '`FEDAPAY_*` : clés du compte FedaPay de la plateforme (voir « FedaPay »).',
          'Lancez en permanence le worker `php artisan queue:work --tries=3` (e-mails, notifications) et le planificateur `php artisan schedule:work` (ou la tâche cron `schedule:run`).',
          'Après chaque modification du `.env` : `php artisan config:clear` puis `php artisan queue:restart`.',
        ],
      },
      {
        id: 'plans',
        title: 'Formules (plans)',
        icon: 'tag',
        where: ['Facturation', 'Plans'],
        steps: [
          '**Nouveau plan** : nom, description, prix, période (mensuel ou annuel).',
          '**Limites** : nombre maximal d’établissements, d’élèves actifs et de comptes (hors parents). Vide = illimité.',
          '**Modules** : cochez ce que la formule donne (notes, absences, espace parent, devoirs, emplois du temps, frais, paiement en ligne, SMS, documents…). Les menus des écoles s’adaptent automatiquement.',
        ],
        tips: [{ tone: 'warning', text: 'Un plan sans prix s’affiche « Sur devis » et ne peut pas être payé en ligne par les écoles.' }],
      },
      {
        id: 'creer-ecole',
        title: 'Créer une école',
        icon: 'building',
        where: ['Clients', 'Tenants'],
        steps: [
          '**Nouveau tenant** : nom de l’école (le code et le sous-domaine en sont tirés), plan, statut initial (essai ou actif), administrateur (nom, e-mail).',
          'La base de l’école est créée et préparée (rôles, niveaux, année scolaire).',
          'L’administrateur reçoit un **e-mail** avec l’adresse, son identifiant et un mot de passe provisoire. Le mot de passe s’affiche aussi une seule fois à l’écran.',
          'Liste des tenants → menu « ⋯ » → **Renvoyer les identifiants** génère et envoie un nouveau mot de passe.',
        ],
      },
      {
        id: 'abonnements',
        title: 'Suivre les abonnements',
        icon: 'refresh',
        where: ['Facturation', 'Abonnements'],
        steps: [
          'La colonne **Abonnement** des tenants indique l’état : essai, actif, délai de grâce, expiré, suspendu.',
          'Les écoles reçoivent des rappels automatiques (7, 3 et 1 jour avant la fin, puis pendant le délai de grâce).',
          'Paiement reçu hors ligne (virement, espèces) : **Modifier** le tenant et repoussez la **Date d’expiration**.',
          '**Suspendre** bloque immédiatement une école ; **Activer** la rétablit.',
          '**Paiements** liste les paiements d’abonnement, dont ceux reçus via FedaPay.',
        ],
      },
      {
        id: 'fedapay',
        title: 'FedaPay',
        icon: 'wallet',
        where: ['Serveur', '.env'],
        intro: 'Deux comptes distincts : le vôtre pour les abonnements, celui de chaque école pour les frais scolaires.',
        steps: [
          'Dans votre compte FedaPay → Paramètres → Clés API : copiez la clé secrète dans `FEDAPAY_SECRET_KEY` et la clé publique dans `FEDAPAY_PUBLIC_KEY`.',
          'Créez un webhook vers `{APP_URL}/api/v1/webhooks/fedapay` (événements `transaction.*`) et copiez son secret dans `FEDAPAY_WEBHOOK_SECRET`.',
          'Testez avec `FEDAPAY_ENVIRONMENT=sandbox`, puis passez à `live` avec les clés de production.',
          'Les webhooks `{APP_URL}/api/v1/webhooks/fedapay/{code-école}` sont déclarés par chaque école sur **son** compte FedaPay ; ils ne vont pas dans votre `.env`.',
        ],
        tips: [{ tone: 'info', text: 'Un paiement resté « en attente » (webhook perdu) est revérifié auprès de FedaPay toutes les 15 minutes par le planificateur.' }],
      },
      {
        id: 'file-attente',
        title: 'E-mails et file d’attente',
        icon: 'mail',
        where: ['Serveur', 'Terminal'],
        steps: [
          'Les e-mails et notifications partent par la file `jobs` : sans worker, rien n’est envoyé.',
          'Voir les envois en échec : `php artisan queue:failed`. Les relancer : `php artisan queue:retry all`.',
          'En développement : `MAIL_MAILER=log` (e-mails écrits dans `storage/logs/laravel.log`) ou Mailpit sur le port 1025.',
        ],
        tips: [{ tone: 'warning', text: 'Erreur « Connection could not be established » : le serveur SMTP indiqué dans `MAIL_HOST` / `MAIL_PORT` n’est pas joignable.' }],
      },
      {
        id: 'suivi',
        title: 'Suivi de la plateforme',
        icon: 'chart',
        where: ['Plateforme', 'Tableau de bord'],
        steps: [
          '**Tableau de bord** et **Statistiques** : écoles, élèves gérés, revenus, utilisateurs actifs.',
          '**Utilisation** : consommation par école (élèves, stockage, SMS), collectée chaque nuit.',
          '**Support** : répondez aux demandes des écoles, passez-les « Pris en charge » puis « Résolu ».',
          '**Annonces** : messages adressés aux écoles.',
        ],
      },
    ],
    faq: [
      ['L’e-mail de bienvenue n’arrive pas.', 'Vérifiez que le worker tourne et `php artisan queue:failed`. Puis « Renvoyer les identifiants ».'],
      ['Une école a payé mais reste bloquée.', 'Vérifiez le webhook FedaPay et son secret, ou prolongez manuellement la date d’expiration du tenant.'],
      ['Une école voit des modules en trop ou en moins.', 'Ce sont les modules cochés dans son plan (Facturation → Plans).'],
    ],
  },
];
