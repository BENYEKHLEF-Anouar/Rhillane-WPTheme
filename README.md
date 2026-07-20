<div align="center">
  <h1>Vault Child — Rhillane (Staging)</h1>
  <p><b>Thème Enfant WordPress — Environnement de Préproduction (Staging) pour rhillane.com</b></p>

  [![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg?logo=wordpress&logoColor=white)](https://wordpress.org)
  [![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4.svg?logo=php&logoColor=white)](https://php.net)
  [![Elementor](https://img.shields.io/badge/Elementor-Builder-92003B.svg?logo=elementor&logoColor=white)](https://elementor.com)
  [![Parent Theme](https://img.shields.io/badge/Parent-UiCore_Vault-000000.svg?logo=wordpress&logoColor=white)](https://uicore.co)
  [![Child Theme](https://img.shields.io/badge/WordPress-Child_Theme-21759B.svg?logo=wordpress&logoColor=white)](https://developer.wordpress.org/themes/advanced-topics/child-themes/)
  [![Deploy](https://img.shields.io/badge/CI%2FCD-GitHub_Actions_→_FTP-2088FF.svg?logo=githubactions&logoColor=white)](.github/workflows/deploy-staging.yml)
  [![License](https://img.shields.io/badge/License-GPLv2-green.svg)](LICENSE)

  <br />
  <a href="#présentation"><b>Présentation</b></a> •
  <a href="#rôle--fonctionnalités"><b>Rôle</b></a> •
  <a href="#architecture--structure-du-projet"><b>Architecture</b></a> •
  <a href="#guide-de-développement-local"><b>Guide Dev Local</b></a> •
  <a href="#déploiement-continu-cicd"><b>Déploiement</b></a> •
  <a href="#auteurs--crédits"><b>Auteurs</b></a>
</div>

---

## Présentation

**Vault Child** est le thème enfant WordPress utilisé pour l'environnement de **préproduction (staging)** de [rhillane.com](https://rhillane.com), l'agence de marketing digital opérant à l'international (France, Maroc, Émirats Arabes Unis, États-Unis).

Il repose sur le thème parent **UiCore Vault**, un thème multi-usage basé sur **Elementor**, et sert de bac à sable isolé pour tester, refondre et valider des évolutions du site **avant tout passage en production**. Le staging vit dans son propre sous-répertoire serveur (`/staging/`), totalement séparé de l'installation Multisite de production.

Toute personnalisation (styles, hooks, fonctions) est réalisée dans ce thème enfant afin de **préserver le thème parent Vault** lors de ses mises à jour.

---

## Rôle & Fonctionnalités

- **Environnement de Staging Isolé** : Sandbox de préproduction hébergée sous `/staging/`, indépendante du site de production, pour valider les changements sans risque.
- **Thème Enfant Propre** : Surcharge le thème parent UiCore Vault sans modifier ses fichiers, garantissant des mises à jour du parent sans perte des personnalisations.
- **Construction visuelle via Elementor** : Mise en page et composants pilotés par le builder Elementor du thème parent Vault.
- **Personnalisation Centralisée** : Styles additionnels dans `style.css` et logique métier dans `functions.php` (hooks & filtres UiCore).
- **Déploiement Automatisé** : Synchronisation FTP automatique vers le serveur de staging à chaque push sur `main` (voir [CI/CD](#déploiement-continu-cicd)).

---

## Stack Technique

| Composant             | Technologie                     | Description                                                        |
| :-------------------- | :------------------------------ | :---------------------------------------------------------------- |
| **CMS Core**          | WordPress 6.x+ (PHP 8.1+)       | Installation de staging dédiée sous `/staging/`                    |
| **Thème Parent**      | UiCore Vault                    | Thème multi-usage basé sur Elementor (`Template: vault`)          |
| **Page Builder**      | Elementor                       | Construction visuelle des pages et composants                     |
| **Personnalisation**  | Child Theme (CSS + hooks PHP)   | `style.css` & `functions.php` (hooks/filtres UiCore)              |
| **Déploiement**       | GitHub Actions → FTP            | Déploiement continu vers l'environnement de staging               |

---

## Architecture & Structure du Projet

```text
Rhillane-WPTheme/
├── .github/
│   └── workflows/
│       └── deploy-staging.yml   # CI/CD : déploiement FTP automatique vers le staging
├── functions.php                # Enqueue des styles parent/enfant & hooks UiCore
├── screenshot.png               # Image de couverture du thème (Admin WP)
├── style.css                    # En-tête du thème enfant (Template: vault) & styles custom
├── .gitignore
└── README.md
```

> Le thème enfant est volontairement minimal : la mise en page provient d'Elementor et du parent Vault. Les surcharges vivent dans `style.css` (styles) et `functions.php` (comportements).

---

## Guide de Développement Local

### 1. Prérequis

- **PHP** >= 8.1
- **WordPress** local (Studio, LocalWP ou Valet)
- Thème parent **UiCore Vault** installé dans `wp-content/themes/vault/`
- Extension **Elementor** activée

### 2. Installation

Cloner le dépôt dans le dossier des thèmes WordPress (`wp-content/themes/`) sous le nom **`vault-child`** :

```bash
git clone https://github.com/BENYEKHLEF-Anouar/Rhillane-WPTheme.git vault-child
cd vault-child
```

Puis, dans l'administration WordPress : **Apparence > Thèmes > Vault Child > Activer**.

> Le dossier **doit** s'appeler `vault-child` et le thème parent `vault` doit être présent, sinon WordPress ne pourra pas résoudre le `Template: vault`.

### 3. Personnalisation

- **Styles** : ajoutez vos règles CSS à la fin de `style.css`.
- **Fonctions & Hooks** : ajoutez votre logique dans `functions.php`. La liste complète des hooks et filtres du parent est documentée sur [help.uicore.co](https://help.uicore.co/docs/hooks-and-filters).

---

## Déploiement Continu (CI/CD)

Le projet intègre un déploiement automatisé via **GitHub Actions** ([`.github/workflows/deploy-staging.yml`](.github/workflows/deploy-staging.yml)).

À chaque push sur la branche `main`, le thème est synchronisé par **FTP** vers l'environnement de **staging** :

```
/staging/wp-content/themes/vault-child/
```

- **Stamp de version** : le numéro de build GitHub est ajouté à la version du thème (ex. `1.0.0.42`), visible dans **Apparence > Thèmes**, pour identifier le déploiement en ligne.
- **Robustesse** : jusqu'à **3 tentatives** FTP successives (Hostinger refuse parfois une IP de runner), chacune sur une IP différente.
- **Dossier propre** : les fichiers de dépôt (`.github/`, `README.md`, `.gitignore`, `.git*`) sont **exclus** de l'upload — seuls les vrais fichiers du thème sont déployés.
- **Secrets requis** (GitHub → *Settings > Secrets and variables > Actions*) : `STAGING_FTP_SERVER`, `STAGING_FTP_USERNAME`, `STAGING_FTP_PASSWORD`.

> ⚠️ Ce workflow cible **uniquement** le staging. La production (Multisite) n'est jamais touchée par ce dépôt.

---

## Auteurs & Crédits

Environnement de staging pensé et maintenu par :

- **Anouar BENYEKHLEF** — [*Développeur Full Stack*](https://anouar-benyekhlef-portfolio.vercel.app/)
- **Ayoub JALYTA** — [*Développeur Full Stack*](https://ayoub-jlita.vercel.app/)
- **Jallal KADDORI**

Thème parent **Vault** développé par [UiCore](https://uicore.co).

---

<div align="center">
  <p>Environnement de préproduction (staging) pour Rhillane Marketing Digital.</p>
</div>
