# Pathly pour Magento

Sachez avant vos clients quand votre tunnel de commande casse.

*Pathly watches the path. Ping if it's up. Flow if a user can walk it. Chain if the services behind it hold.*

Le serveur répond, la page d'accueil s'affiche, et pourtant plus personne ne peut commander : un module mis à jour a cassé le bouton « Passer commande », le panier reste vide, la page de paiement est blanche. Un simple test de disponibilité ne le voit pas. Ce module relie votre boutique Magento à [Pathly](https://pathlyhq.com) :

- **Ping** : dès la connexion, Pathly vérifie toutes les 5 minutes que la boutique répond. Un Ping déjà créé pour la boutique est rattaché, sans doublon.
- **Flow du tunnel préparé par Lumi** : Lumi, l'assistant de Pathly, reconnaît Magento et prépare le Flow de votre tunnel : ajouter un produit au panier, puis aller jusqu'à la page de paiement. Il propose aussi la connexion client et le formulaire de contact. Chaque Flow proposé est rejoué avant de vous être montré. Vous le validez en un clic.
- **Rejeu après chaque mise à jour** : après `bin/magento setup:upgrade`, ou quand Magento ou un module change de version, Pathly rejoue vos Flow une fois le mode maintenance coupé.
- **Encart du tableau de bord** : état du Ping et des Flow, date et capture du dernier échec, offre et quotas, lien vers Pathly.
- **Badge de disponibilité**, éteint par défaut, relié à votre page de statut publique.

Lumi ne paie rien, n'envoie aucun formulaire et ne saisit que des valeurs de test. Vous validez chaque Flow avant son enregistrement.

## Prérequis

| | |
|---|---|
| Magento Open Source ou Adobe Commerce | 2.4.6, 2.4.7, 2.4.8 |
| PHP | 8.1 à 8.4, selon votre version de Magento |
| Compte Pathly | offre Solo gratuite, sans carte bancaire : [créer un compte](https://pathlyhq.com/fr/login?mode=signup&ref=magento&utm_source=magento&utm_medium=module) |

## Installation

Avec Composer, depuis l'Adobe Commerce Marketplace une fois le module publié :

```bash
composer require pathly/module-monitoring
bin/magento module:enable Pathly_Monitoring
bin/magento setup:upgrade
bin/magento cache:flush
```

Ou depuis l'archive d'une [release GitHub](https://github.com/Pathly-Monitoring/pathly-magento/releases) : la déplier dans `app/code/Pathly/Monitoring`, puis lancer les trois dernières commandes.

## Configuration

1. Dans Pathly : Réglages, Clés d'API. Créez une clé, de préférence limitée à un Espace, avec les portées `org:read scenarios:read scenarios:write runs:read runs:trigger assistant:run assistant:save status:read status:write`.
2. Dans Magento : **Magasins > Configuration > Pathly > Surveillance**. Collez la clé et enregistrez. Le module la vérifie auprès de Pathly, Magento la garde chiffrée, et elle n'est plus jamais affichée. Le Ping de la boutique est créé ou rattaché aussitôt.
3. **Flow du tunnel** : cochez « Je gère cette boutique », puis cliquez sur « Préparer le Flow avec Lumi ». Après une à deux minutes, validez le Flow voulu.
4. Facultatif : **Badge de disponibilité**. L'allumer publie votre page de statut Pathly. Affichez-le en pied de page, ou placez le widget « Badge de disponibilité Pathly » (Contenu > Widgets).

En ligne de commande :

```bash
bin/magento config:set pathly_monitoring/connection/api_key sp_...
bin/magento pathly:sync            # ce que fait la tâche planifiée, tout de suite
bin/magento pathly:sync --replay   # rejouer les Flow de la boutique maintenant
```

Les droits passent par les rôles (Système > Permissions > Rôles) : `Pathly_Monitoring::config` pour les réglages, `Pathly_Monitoring::lumi` pour Lumi, `Pathly_Monitoring::dashboard` pour l'encart.

## Rejeu après une mise à jour

Deux signaux demandent un rejeu :

- chaque `bin/magento setup:upgrade` (classe `Setup/RecurringData`, lancée par Magento à la fin de chaque mise à jour) ;
- un changement de l'empreinte des versions installées (version et édition de Magento, modules actifs et version de leur paquet), comparée par la tâche planifiée `pathly_monitoring_sync` toutes les 5 minutes.

Le rejeu attend la fin du mode maintenance, puis appelle `POST /v1/scenarios/{id}/run` pour chaque Flow validé avec Lumi depuis le module, et pour les Flow saisis dans « Autres Flow à rejouer ». Deux rejeux sont espacés d'au moins 10 minutes. La tâche planifiée de Magento (cron) doit tourner.

## Bon à savoir : les paniers de test

Chaque exécution d'un Flow crée un panier de test sur la boutique, et peut connecter un client de test. Elle peut déclencher vos relances de panier abandonné. Excluez-en le client ou l'adresse de test.

## Données et sécurité

- Rien ne part vers Pathly avant qu'un administrateur ait enregistré une clé d'API.
- Ensuite, seules l'adresse de la boutique (URL de base sécurisée de la vue par défaut) et les versions de Magento et du module (dans l'en-tête `User-Agent`) sont envoyées. Aucune donnée client, aucune commande.
- Le module n'appelle que `https://api.pathlyhq.com`. Il n'appelle jamais une adresse saisie par un utilisateur.
- La clé d'API est chiffrée par Magento (champ `obscure`, marqué sensible : `app:config:dump` ne l'exporte pas), jamais réaffichée, jamais écrite dans les journaux.
- Écrans et actions réservés aux rôles ci-dessus, avec le jeton de formulaire de Magento.
- Le badge est une copie gardée et servie par la boutique (`/pathly/badge/svg`) : un visiteur de la boutique ne charge rien depuis Pathly.
- La capture du dernier échec est un lien signé par Pathly, valable 15 minutes, chargé par le seul navigateur de l'administrateur.
- Nous appliquons le RGPD. Application, API, workers et captures : Google Cloud europe-west1. DPA disponible.

## Désinstallation

Installé avec Composer :

```bash
bin/magento module:uninstall -r Pathly_Monitoring
```

Copié dans `app/code` :

```bash
bin/magento module:disable Pathly_Monitoring
bin/magento module:uninstall --non-composer Pathly_Monitoring
rm -rf app/code/Pathly/Monitoring
bin/magento setup:upgrade
```

Les deux effacent les réglages, l'état du module, ses tâches planifiées et ses widgets de badge. Vos Ping et Flow restent dans votre compte Pathly, où vous pouvez les supprimer.

## Développement

Voir la section « Development » du [README anglais](README.md) : tests unitaires en Docker (100 % des lignes et des branches), test d'intégration sur Magento 2.4.8, archive.

## Licence

[OSL-3.0](LICENSE.txt). Questions : simon@pathlyhq.com.
