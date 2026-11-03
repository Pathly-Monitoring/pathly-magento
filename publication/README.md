# Dossier de publication — Adobe Commerce Marketplace

Tout ce qu'il faut pour publier l'extension `pathly/module-monitoring` (module `Pathly_Monitoring`). Rien n'a été soumis, aucun compte n'a été créé et aucune condition n'a été acceptée : ces étapes sont pour Simon.

## Contenu

| Fichier | Rôle |
|---|---|
| `listing-en.md` | Fiche à soumettre, en anglais (titre, résumé, description, FAQ, notes de version, catégories, compatibilité) |
| `fiche-fr.md` | La même fiche en français, pour relecture |
| `user-guide-en.pdf` (source `user-guide-en.html`) | Guide d'installation et d'utilisation en anglais, exigé par la revue (au moins un document en anglais) |
| `screenshots/*-en.png`, `screenshots/*-fr.png` | Captures 1440 × 900 (ou de la zone utile) faites sur Magento 2.4.8-p3 en Docker, administration en anglais puis en français : 1 réglages, 2 propositions de Lumi, 3 encart du tableau de bord, 4 badge en pied de page |
| `assets/icon.svg`, `assets/icon-{128,240,512}.png` | Icône (sans logo Magento ni Adobe, comme le veut la revue marketing) |
| `assets/banner-{fr,en}-1280x640.png` | Bannière (GitHub, réseaux, galerie si besoin) |
| Archive | `build/pathly-module-monitoring-<version>.zip`, construite par la CI et jointe à chaque release GitHub |

Les captures viennent de la boutique de test du test d'intégration : boutique Luma vide, API Pathly simulée. « Boutique test », le Flow en échec, son message et sa capture sont des données de test. La « capture de l'étape en échec » montre le panier de la boutique de test.

Refaire les captures : `dev/integration/run.sh --keep`, puis `dev/screenshots/run.sh en` et `dev/screenshots/run.sh fr`. Refaire l'icône, les bannières et le PDF : voir l'en-tête de `dev/screenshots/assets.mjs`.

## Ce que dit Adobe (pages consultées le 03/11/2026)

- Deux revues, lancées ensemble ou séparément. **Technique** : automatique pour une extension (recherche de logiciel malveillant, de plagiat, Magento Coding Standard, installation). **Marketing** : présentation, contenu et marque de la fiche. Les deux doivent passer pour que la fiche soit publiée.
- Avant la soumission : Magento Coding Standard (il passe déjà dans la CI, sans erreur ni avertissement), archive zip du paquet Composer, notes de version en texte, guide en PDF.
- Titre : 5 mots au plus, en anglais, sans « Magento », « Adobe », « M2 », « Extension », « Module » ni « Plugin ». D'où « Pathly Checkout Monitoring ».
- Frais tiers (abonnement Pathly) : à écrire **en gras** dans le premier paragraphe de la description longue. C'est fait dans `listing-en.md`.
- Galerie : au moins deux images, JPG ou PNG, 5 Mo au plus chacune. Une vignette et une icône sont demandées.
- Un compte développeur neuf ne soumet que pour Magento Open Source. Pour cocher Adobe Commerce, le titulaire doit être partenaire ou demander une licence développeur Adobe Commerce, avec la même adresse e-mail.
- Une fois des ventes faites, le nom de vendeur ne se change plus. Pour une extension gratuite, pas de vente : le choix reste à soigner.
- Prix : gratuit, ou de 25 $ à 999 999 $. Ici : gratuit.

Sources : [revue technique](https://developer.adobe.com/commerce/marketplace/guides/sellers/submit-for-technical-review), [revue marketing](https://developer.adobe.com/commerce/marketplace/guides/sellers/submit-for-marketing-review), [règles marketing](https://developer.adobe.com/commerce/marketplace/guides/sellers/marketing-review-guidelines), [création d'une extension](https://developer.adobe.com/commerce/marketplace/guides/sellers/extension-create), [création du compte](https://developer.adobe.com/commerce/marketplace/guides/sellers/account-setup-process), [partage des revenus](https://developer.adobe.com/commerce/marketplace/guides/sellers/revenue-share).

## Non vérifié

- **Délais de revue.** Aucune page ne donne de délai. La soumission part en file d'attente, avec un e-mail de confirmation.
- **Nom de vendeur et préfixe du paquet.** Le paquet s'appelle `pathly/module-monitoring`. Si le Marketplace exige que le préfixe Composer soit le nom de vendeur du compte et que « pathly » est pris, il faudra renommer le paquet : un commit et une nouvelle release.
- **Informations fiscales et de paiement.** Pas lues pour une extension gratuite. Elles servent aux ventes.
- **Adobe Commerce sur le cloud.** Non essayé. Ne cocher que les versions testées (Open Source 2.4.6, 2.4.7, 2.4.8).

## Étapes pour Simon

### A. Avant la soumission (dépôt)

1. Relire `listing-en.md`, `fiche-fr.md` et les captures. Corriger ce qui ne va pas : je reprends dans le dépôt.
2. Fusionner la PR « chore(main): release 1.0.0 » ouverte par release-please. La release `v1.0.0` est créée, avec `pathly-module-monitoring-1.0.0.zip` en pièce jointe.
3. Télécharger ce zip depuis la page de la release. C'est le fichier à envoyer à la revue technique.
4. Facultatif, plus tard : passer le dépôt `Pathly-Monitoring/pathly-magento` en public (il est privé), après relecture du README.

### B. Compte développeur

1. Aller sur https://commercemarketplace.adobe.com, « Sign in », puis « Create an Account » avec `simon@pathlyhq.com`. Un seul compte, au nom de Pathly, identité claire (règle de `legal-comptes.md`).
2. Choisir un compte **entreprise**, nom de vendeur **pathly** (voir « Non vérifié »).
3. Lire les conditions générales (« master ») et les conditions développeur du Marketplace, puis les accepter. C'est un engagement juridique : à faire par vous seul.
4. Compléter le profil d'entreprise : description courte de Pathly, logo (`assets/icon-512.png`), site https://pathlyhq.com, e-mail de support `simon@pathlyhq.com`.

### C. Soumission de l'extension

1. Developer Portal > Extensions > « Add Extension ». Nom : **Pathly Checkout Monitoring**. SKU : `pathly/module-monitoring` (repris du `composer.json`).
2. **Technical Submission** :
   - envoyer le zip de la release ;
   - version 1.0.0 ;
   - compatibilité : Magento Open Source 2.4.6, 2.4.7, 2.4.8 (Adobe Commerce seulement après la licence développeur, voir plus haut) ;
   - notes de version : le bloc « Release notes » de `listing-en.md` ;
   - documentation : `user-guide-en.pdf` ;
   - licence : OSL-3.0.
3. **Marketing Submission** :
   - titre, description courte et longue de `listing-en.md`, sans retirer la phrase en gras sur l'abonnement ;
   - icône : `assets/icon-512.png` ;
   - galerie : les quatre captures `screenshots/*-en.png` (la première sert de vignette) ;
   - catégories : celles de `listing-en.md`, ou les plus proches proposées ;
   - prix : gratuit (0) ;
   - stabilité : Stable ;
   - support : e-mail `simon@pathlyhq.com`, sans offre de support payante.
4. Envoyer les deux revues. Suivre les e-mails d'Adobe.
5. En cas d'échec technique, transmettre le rapport : je corrige dans le dépôt, une nouvelle release produit un nouveau zip. En cas d'échec marketing, modifier les champs de la fiche et renvoyer.
6. Ne jamais solliciter d'avis, ni en acheter.

### D. Après la publication

1. Sur pathlyhq.com : une page « Pathly pour Magento » avec le lien de la fiche (chantier du monorepo, à ouvrir en issue : non fait ici).
2. Les inscriptions venues du module arrivent avec `?ref=magento` et apparaissent dans `/platform/canaux`.
3. À chaque version de Magento (2.4.9 et suivantes) : ajouter la version à la CI, publier une release, mettre à jour la compatibilité dans le Developer Portal.

## Clé d'API à créer pour un marchand (rappel dans la fiche)

Clé limitée à un Espace, portées : `org:read scenarios:read scenarios:write runs:read runs:trigger assistant:run assistant:save status:read status:write`.
