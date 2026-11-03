# Fiche de l'extension — français

Texte de référence pour Simon. La fiche publiée sur l'Adobe Commerce Marketplace est en anglais (`listing-en.md`) : la revue marketing la demande en anglais.

## Titre (5 mots au plus, sans « Magento », « Adobe », « Module » ni « Extension »)

Pathly Checkout Monitoring

## Résumé court

Sachez avant vos clients quand votre tunnel casse. Ping vérifie que la boutique répond, Flow rejoue le parcours d'achat jusqu'à la page de paiement, après chaque mise à jour.

## Description

**L'extension est gratuite. Elle se connecte à un compte Pathly : l'offre Solo est gratuite et sans carte bancaire. Les offres payantes de Pathly sont un abonnement à part (tarifs sur pathlyhq.com).**

### Le problème

Le serveur répond, la page d'accueil s'affiche, et pourtant plus personne ne peut commander : un module mis à jour a cassé le bouton « Passer commande », le panier ne se remplit plus, la page de paiement reste blanche. Un simple test de disponibilité ne le voit pas. Vous l'apprenez par un client, ou par la chute des ventes.

### Ce que fait l'extension

Pathly watches the path. Ping if it's up. Flow if a user can walk it. Chain if the services behind it hold.

- **Ping** : dès la connexion, Pathly vérifie toutes les 5 minutes que votre boutique répond. Un Ping déjà créé pour la boutique est rattaché, sans doublon.
- **Flow préparé par Lumi** : Lumi, l'assistant de Pathly, reconnaît Magento et prépare le Flow de votre tunnel : ajouter un produit au panier, puis aller jusqu'à la page de paiement. Il propose aussi la connexion client et le formulaire de contact. Chaque Flow proposé est rejoué avant de vous être montré. Vous le validez en un clic.
- **Rejeu après une mise à jour** : après `setup:upgrade`, ou quand Magento ou un module change de version, Pathly rejoue le Flow dès la fin du mode maintenance. Vous savez dans les minutes qui suivent si la mise à jour a cassé le tunnel.
- **Encart dans le tableau de bord** : état du Ping et du Flow, date et capture d'écran du dernier échec, offre et quotas, lien vers Pathly.
- **Badge de disponibilité** en pied de page ou en widget, relié à votre page de statut publique. Éteint par défaut.

### Lumi ne fait rien à votre place

Il ne paie rien, n'envoie aucun formulaire et ne saisit que des valeurs de test. Vous validez chaque Flow avant son enregistrement.

### Données et sécurité

- Rien ne part vers Pathly avant que vous ayez enregistré une clé d'API.
- Ensuite, seules l'adresse de la boutique et les versions de Magento et de l'extension sont envoyées. Aucune donnée client, aucune commande.
- La clé d'API est chiffrée par Magento, jamais réaffichée.
- Le badge est servi par votre boutique : vos visiteurs ne chargent rien depuis Pathly.
- Écrans réservés aux rôles autorisés (droits d'accès de Magento).
- Nous appliquons le RGPD. Application, API, workers et captures : GCP europe-west1. DPA disponible.

### Bon à savoir

Chaque exécution du Flow crée un panier de test sur la boutique. Elle peut déclencher vos relances de panier abandonné.

### Compatibilité

Magento Open Source et Adobe Commerce 2.4.6, 2.4.7, 2.4.8 et 2.4.9, PHP 8.1 à 8.5 selon la version de Magento. La tâche planifiée (cron) de Magento doit tourner. Multiboutique : l'extension surveille la vue de magasin par défaut.

## Questions fréquentes

**Faut-il un compte Pathly ?**
Oui. L'extension propose de créer un compte gratuit, sans carte bancaire, puis vous collez une clé d'API.

**Quelle clé d'API créer ?**
Dans Pathly, Réglages puis Clés d'API. De préférence une clé limitée à un Espace, avec les portées `org:read scenarios:read scenarios:write runs:read runs:trigger assistant:run assistant:save status:read status:write`.

**Le Flow passe-t-il de vraies commandes ?**
Non. Il s'arrête avant le paiement et ne saisit que des valeurs de test. Il crée en revanche un panier de test à chaque exécution.

**Que se passe-t-il après la mise à jour d'un module ?**
Magento lance l'extension à la fin de chaque `setup:upgrade`, et la tâche planifiée compare les versions installées toutes les 5 minutes. Pathly rejoue alors le Flow, une fois le mode maintenance coupé. Deux rejeux sont espacés d'au moins 10 minutes.

**Mes clients voient-ils quelque chose ?**
Seulement si vous allumez le badge : une petite image en pied de page, servie par votre boutique, qui renvoie vers votre page de statut.

**Et si je désinstalle l'extension ?**
Ses réglages, son état et ses tâches planifiées sont effacés de la boutique. Vos Ping et Flow restent dans votre compte Pathly, où vous pouvez les supprimer.

**Lumi reconnaît-il toutes les boutiques ?**
Il reconnaît Magento. Si votre thème est très personnalisé et que Lumi ne trouve pas le tunnel, vous pouvez enregistrer le Flow vous-même dans Pathly, sans code, puis saisir son identifiant dans « Autres Flow à rejouer ».

## Journal des changements

### 1.0.0

- Connexion par clé d'API, lien de création de compte gratuit.
- Ping de la boutique créé ou rattaché automatiquement.
- Flow du tunnel préparé par Lumi, validé en un clic.
- Rejeu des Flow après `setup:upgrade` ou un changement de version de Magento ou d'un module.
- Encart du tableau de bord : état, dernier échec avec capture, offre et quotas.
- Badge de disponibilité, éteint par défaut, servi par la boutique.
- Traductions français, anglais, espagnol et allemand.
