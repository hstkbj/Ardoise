/**
 * Textes de la politique de confidentialité et des conditions générales.
 *
 * Les coordonnées de l'éditeur viennent du serveur (LEGAL_* dans .env, via window.__ARDOISE__) ;
 * ce qui n'est pas renseigné s'affiche entre crochets, à compléter avant la mise en ligne.
 * Ces textes sont un modèle de départ : à faire relire par un juriste du pays d'exploitation.
 */

export const LEGAL_UPDATED = '15 novembre 2026';

/** Coordonnées de l'éditeur, avec un repère visible quand une valeur manque. */
export function legalInfo() {
  const l = (typeof window !== 'undefined' && window.__ARDOISE__?.legal) || {};
  return {
    company: l.company || '[nom de l’éditeur]',
    address: l.address || '[adresse]',
    email: l.email || '[adresse e-mail de contact]',
    phone: l.phone || '',
    host: l.host || '[hébergeur et pays d’hébergement]',
    authority: l.authority || 'Autorité de protection des données personnelles (APDP)',
  };
}

const ANALYTICS_NAMES = { plausible: 'Plausible Analytics', umami: 'Umami', ga4: 'Google Analytics' };

/** Outil de mesure d'audience configuré côté serveur, ou null. */
export function analyticsInfo() {
  const a = (typeof window !== 'undefined' && window.__ARDOISE__?.analytics) || null;
  return a ? { name: ANALYTICS_NAMES[a.provider] || a.provider, cookies: a.provider === 'ga4' } : null;
}

/** Paragraphes « cookies » : ils décrivent la mesure d'audience seulement si elle est activée. */
function cookieParagraphs() {
  const a = analyticsInfo();
  const essential = 'Ardoise utilise les cookies indispensables au fonctionnement : le cookie de session (pour rester connecté) et le cookie de sécurité qui protège les formulaires. Ils ne nécessitent pas de consentement.';
  const manage = 'Votre choix (accepter ou refuser les cookies facultatifs) est mémorisé dans votre navigateur. Vous pouvez le modifier à tout moment avec le lien « Gérer les cookies » en bas de page.';

  if (!a) {
    return [
      essential,
      'Nous n’utilisons aucun cookie publicitaire ni de suivi à ce jour. Si nous ajoutons un jour des outils de mesure d’audience ou d’autres cookies facultatifs, ils ne seront déposés qu’avec votre accord.',
      manage,
    ];
  }

  return [
    essential,
    a.cookies
      ? `Nous mesurons l’audience des pages publiques de ce site (accueil, fonctionnalités, tarifs, contact, guide, pages légales) avec ${a.name}. Cet outil dépose des cookies facultatifs, uniquement si vous cliquez sur « Accepter » ; ses données peuvent être traitées hors du pays d’exploitation.`
      : `Nous mesurons l’audience des pages publiques de ce site (accueil, fonctionnalités, tarifs, contact, guide, pages légales) avec ${a.name}, sans cookie et sans vous suivre d’un site à l’autre. Les statistiques sont agrégées. Le signal « Ne pas me suivre » de votre navigateur est respecté.`,
    'Aucune mesure d’audience n’est faite dans les espaces des établissements (administration, enseignants, connexion). Nous n’utilisons aucun cookie publicitaire.',
    manage,
  ];
}

/** Une section = { id, title, paragraphs?: string[], items?: string[] }. */
export function privacySections() {
  const i = legalInfo();

  return [
    {
      id: 'editeur',
      title: 'Qui sommes-nous ?',
      paragraphs: [
        `Ardoise est une plateforme de gestion scolaire éditée par ${i.company}, ${i.address}. Pour toute question sur vos données personnelles : ${i.email}${i.phone ? ` · ${i.phone}` : ''}.`,
        'Cette politique explique quelles données sont traitées, pourquoi, pendant combien de temps, comment elles sont protégées et comment exercer vos droits.',
      ],
    },
    {
      id: 'roles',
      title: 'Qui est responsable de quoi ?',
      paragraphs: [
        'Pour les données des élèves, des parents et du personnel saisies dans l’espace d’un établissement, c’est l’établissement qui décide de la finalité et des moyens du traitement : il est le responsable du traitement. Ardoise agit pour son compte, en tant que sous-traitant, et ne les utilise pas pour son propre usage.',
        'Pour les données collectées sur ce site (demande de démonstration, lettre d’information, contact), Ardoise est responsable du traitement.',
      ],
    },
    {
      id: 'donnees',
      title: 'Quelles données traitons-nous ?',
      items: [
        'Élèves : identité, matricule, photo, classe et année scolaire, notes et bulletins, présences et absences (avec leurs justificatifs), frais de scolarité et paiements.',
        'Parents ou tuteurs : nom, numéro de téléphone, adresse e-mail, lien avec l’élève et code d’accès personnel.',
        'Personnel (direction, secrétariat, comptabilité, enseignants) : identité, coordonnées, rôle, matières et classes affectées.',
        'Comptes utilisateurs : identifiant, mot de passe (conservé sous forme chiffrée irréversible), paramètres de double authentification, journal des actions importantes.',
        'Visiteurs du site : informations saisies dans le formulaire de démonstration (nom, fonction, établissement, ville, e-mail, téléphone, effectif, message) et adresse e-mail de la lettre d’information.',
        'Données techniques : adresse IP et journaux de sécurité, nécessaires au fonctionnement et à la protection du service.',
        ...(analyticsInfo() ? ['Statistiques de fréquentation des pages publiques du site : pages vues, type d’appareil, pays (sans identification personnelle).'] : []),
      ],
    },
    {
      id: 'finalites',
      title: 'Pourquoi ces données ?',
      items: [
        'Gérer la scolarité : inscriptions, classes, emplois du temps, évaluations, bulletins, présences.',
        'Gérer les frais de scolarité et enregistrer les paiements.',
        'Informer les familles : notifications, annonces, documents, par application, SMS ou e-mail.',
        'Fournir, sécuriser et améliorer le service, et assurer le support.',
        'Facturer l’abonnement de l’établissement et répondre aux demandes reçues sur le site.',
      ],
    },
    {
      id: 'bases',
      title: 'Sur quelle base légale ?',
      items: [
        'L’exécution du contrat entre Ardoise et l’établissement, et la mission éducative de l’établissement.',
        'Notre intérêt légitime à protéger le service (sécurité, prévention des fraudes).',
        'Votre consentement, pour la lettre d’information et pour tout cookie facultatif : vous pouvez le retirer à tout moment.',
        'Nos obligations légales, lorsqu’elles s’appliquent.',
      ],
    },
    {
      id: 'destinataires',
      title: 'Qui accède aux données ?',
      paragraphs: [
        'Au sein d’un établissement, chacun n’accède qu’à ce que son rôle permet : un enseignant voit ses classes et ses matières, un parent voit ses enfants, un site ne voit que son campus.',
        'Nous faisons appel à des prestataires strictement nécessaires au service : l’hébergement, le prestataire de paiement en ligne (FedaPay) et le fournisseur d’envoi de SMS choisi pour le service. Ils ne reçoivent que les données utiles à leur mission. Nous ne vendons aucune donnée personnelle et ne les utilisons pas à des fins publicitaires.',
      ],
    },
    {
      id: 'hebergement',
      title: 'Où sont-elles hébergées ?',
      paragraphs: [
        `Les données sont hébergées chez ${i.host}. Chaque établissement dispose de sa propre base de données, séparée de celles des autres établissements. Tout transfert de données hors du pays d’exploitation est encadré conformément à la réglementation applicable.`,
      ],
    },
    {
      id: 'duree',
      title: 'Combien de temps ?',
      items: [
        'Les données d’un établissement sont conservées pendant la durée de son abonnement. À la fin du contrat, l’établissement peut les exporter ; elles sont ensuite supprimées sous un délai de [délai à préciser].',
        'Les sauvegardes quotidiennes sont conservées 14 jours par défaut, puis supprimées automatiquement.',
        'Les demandes de démonstration sont conservées [durée à préciser] ; la lettre d’information jusqu’à votre désinscription.',
      ],
    },
    {
      id: 'securite',
      title: 'Comment sont-elles protégées ?',
      items: [
        'Une base de données par établissement : aucune donnée n’est mélangée entre écoles.',
        'Accès par rôle et par site, selon les permissions accordées par l’établissement.',
        'Mots de passe stockés sous forme chiffrée irréversible, double authentification disponible, nombre d’essais de connexion limité.',
        'Secrets sensibles (identifiants de base de données, secrets de double authentification, codes d’accès) chiffrés.',
        'Connexion chiffrée (HTTPS) et sauvegardes régulières.',
        'Les paiements en ligne passent par FedaPay : Ardoise ne conserve aucun numéro de carte ni code de paiement mobile.',
      ],
      paragraphs: [
        'Aucun système n’est infaillible : en cas de violation de données personnelles, nous en informons l’établissement concerné dans les meilleurs délais, ainsi que l’autorité compétente lorsque la loi l’exige.',
      ],
    },
    {
      id: 'cookies',
      title: 'Cookies et traceurs',
      paragraphs: cookieParagraphs(),
    },
    {
      id: 'droits',
      title: 'Vos droits',
      paragraphs: [
        'Vous pouvez demander l’accès à vos données, leur rectification, leur suppression, la limitation de leur traitement, vous y opposer et, lorsque c’est applicable, demander leur portabilité. Vous pouvez aussi retirer un consentement à tout moment.',
        `Pour les données scolaires (élèves, parents, personnel), adressez-vous d’abord à votre établissement, responsable du traitement ; nous l’aidons à répondre. Pour les données collectées sur ce site, écrivez à ${i.email}. Nous répondons dans un délai raisonnable.`,
        `Si vous estimez que vos droits ne sont pas respectés, vous pouvez saisir l’${i.authority}.`,
      ],
    },
    {
      id: 'mineurs',
      title: 'Données des élèves mineurs',
      paragraphs: [
        'Les données des élèves sont saisies par leur établissement, qui est responsable d’informer les familles et d’obtenir les autorisations nécessaires. Ardoise ne contacte pas directement les élèves et n’utilise leurs données que pour fournir le service à l’établissement.',
      ],
    },
    {
      id: 'modifications',
      title: 'Modifications',
      paragraphs: [
        `Nous pouvons mettre à jour cette politique, par exemple si le service évolue. La date de dernière mise à jour est indiquée en haut de la page : ${LEGAL_UPDATED}.`,
      ],
    },
  ];
}

export function termsSections() {
  const i = legalInfo();

  return [
    {
      id: 'objet',
      title: 'Objet',
      paragraphs: [
        `Les présentes conditions régissent l’utilisation de la plateforme Ardoise, éditée par ${i.company}, ${i.address} (« l’Éditeur »), par les établissements scolaires abonnés (« l’École ») et par leurs utilisateurs : direction, personnel, enseignants, parents.`,
        'Utiliser le service implique d’accepter ces conditions. Un contrat ou un devis signé avec l’École peut les compléter : en cas de différence, il prévaut.',
      ],
    },
    {
      id: 'comptes',
      title: 'Accès et comptes',
      items: [
        'L’Éditeur crée l’espace de l’École. L’administrateur de l’École crée ensuite les comptes de son personnel et remet aux parents leur code d’accès personnel.',
        'Chaque utilisateur garde ses identifiants confidentiels et répond de l’usage de son compte. La double authentification est recommandée.',
        'Tout accès suspect doit être signalé sans délai à l’Éditeur.',
      ],
    },
    {
      id: 'abonnement',
      title: 'Abonnement et paiement',
      items: [
        'Le service est proposé sous forme d’abonnement selon le plan choisi ; les modules disponibles dépendent du plan. Les tarifs en vigueur sont indiqués sur la page Tarifs ou dans le devis.',
        'Une période d’essai peut être accordée : [conditions à préciser].',
        'L’abonnement est payable à l’avance, par les moyens proposés dans le service. Le renouvellement et le changement de plan se font depuis l’espace Abonnement.',
        'En cas de non-paiement, l’accès est d’abord maintenu pendant un délai de grâce, puis limité à la page d’abonnement tant que le paiement n’est pas régularisé. Les données ne sont pas supprimées pour autant.',
      ],
    },
    {
      id: 'obligations-ecole',
      title: 'Obligations de l’École',
      items: [
        'Saisir des informations exactes et à jour, et utiliser le service de manière licite.',
        'Informer les familles et le personnel du traitement de leurs données, et obtenir les autorisations nécessaires, notamment pour les élèves mineurs.',
        'Attribuer les droits d’accès avec discernement, et retirer les accès des personnes qui quittent l’établissement.',
        'Ne pas tenter de contourner les limites techniques ou de sécurité du service, ni d’accéder aux données d’une autre École.',
      ],
    },
    {
      id: 'obligations-editeur',
      title: 'Engagements de l’Éditeur',
      items: [
        'Fournir le service avec le soin d’un professionnel et en assurer la maintenance.',
        'Protéger les données par des mesures techniques et organisationnelles adaptées, décrites dans la politique de confidentialité.',
        'Effectuer des sauvegardes régulières et assurer un support : [niveau de support à préciser].',
        'L’Éditeur s’engage sur les moyens mis en œuvre, non sur une disponibilité ininterrompue : des opérations de maintenance ou des incidents peuvent interrompre temporairement le service.',
      ],
    },
    {
      id: 'donnees',
      title: 'Données de l’École',
      paragraphs: [
        'Les données saisies par l’École restent la propriété de l’École. L’Éditeur les traite uniquement pour fournir le service, sur instruction de l’École et conformément à la politique de confidentialité, qui fait partie des présentes conditions.',
        'L’École peut exporter ses données pendant son abonnement et pendant [durée à préciser] après la fin du contrat. Passé ce délai, elles sont supprimées.',
      ],
    },
    {
      id: 'paiements-parents',
      title: 'Paiement des frais de scolarité',
      paragraphs: [
        'Lorsque l’École active le paiement en ligne, les parents paient les frais de scolarité par l’intermédiaire d’un prestataire de paiement tiers (FedaPay). Les sommes sont destinées à l’École : la relation financière avec les familles reste entre l’École et les parents. L’Éditeur n’est pas partie à cette relation et ne conserve aucune donnée bancaire.',
      ],
    },
    {
      id: 'propriete',
      title: 'Propriété intellectuelle',
      paragraphs: [
        'Le service, son code, ses interfaces, sa marque et sa documentation appartiennent à l’Éditeur. L’École dispose d’un droit d’utilisation personnel, non exclusif et non transférable, pendant la durée de son abonnement. Les logos et contenus fournis par l’École restent les siens.',
      ],
    },
    {
      id: 'responsabilite',
      title: 'Responsabilité',
      paragraphs: [
        'Dans les limites permises par la loi, l’Éditeur n’est pas responsable des dommages indirects (perte d’exploitation, perte de chance…), ni des conséquences d’informations erronées saisies par l’École, ni des interruptions dues à un tiers (opérateur, prestataire de paiement ou de SMS, force majeure). La responsabilité de l’Éditeur est limitée, dans la mesure permise par la loi, aux sommes payées par l’École sur les [durée à préciser] derniers mois.',
      ],
    },
    {
      id: 'resiliation',
      title: 'Suspension et résiliation',
      items: [
        'L’École peut résilier à tout moment depuis l’espace Abonnement ou par écrit ; l’abonnement en cours n’est pas remboursé, sauf disposition contraire du contrat.',
        'L’Éditeur peut suspendre ou résilier l’accès en cas de manquement grave (usage illicite, atteinte à la sécurité, impayé persistant), après information de l’École lorsque c’est possible.',
        'À la fin du contrat, les données sont traitées comme indiqué à la section « Données de l’École ».',
      ],
    },
    {
      id: 'modifications',
      title: 'Modification des conditions',
      paragraphs: [
        `L’Éditeur peut faire évoluer ces conditions. Les écoles sont informées des changements importants avant leur entrée en vigueur. Dernière mise à jour : ${LEGAL_UPDATED}.`,
      ],
    },
    {
      id: 'droit',
      title: 'Droit applicable et litiges',
      paragraphs: [
        'Les présentes conditions sont régies par le droit [pays à préciser]. En cas de litige, les parties recherchent d’abord une solution amiable ; à défaut, les tribunaux de [ville ou juridiction à préciser] sont compétents.',
        `Contact : ${i.email}${i.phone ? ` · ${i.phone}` : ''}.`,
      ],
    },
  ];
}
