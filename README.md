# Vault Child — Rhillane (Staging)

Thème **enfant** WordPress pour l'environnement de **préproduction (staging)** de [rhillane.com](https://rhillane.com).

Basé sur le thème parent **UiCore Vault** (thème multi-usage Elementor). À ce stade, il s'agit du thème enfant initial : les personnalisations (styles, hooks) seront ajoutées dans `style.css` et `functions.php`.

## Contenu du dépôt

```text
Rhillane-WPTheme/
├── .github/workflows/deploy-staging.yml   # Déploiement FTP automatique vers le staging
├── functions.php                          # Enqueue des styles parent/enfant
├── screenshot.png                         # Aperçu du thème (Admin WP)
├── style.css                              # En-tête du thème enfant (Template: vault)
└── README.md
```

## Prérequis

- WordPress avec le thème parent **`vault`** installé (`wp-content/themes/vault/`)
- **Elementor** activé
- PHP >= 8.1

## Installation locale

Cloner dans `wp-content/themes/` sous le nom **`vault-child`**, puis activer via **Apparence > Thèmes** :

```bash
git clone https://github.com/BENYEKHLEF-Anouar/Rhillane-WPTheme.git vault-child
```

> Le dossier doit s'appeler `vault-child` et le parent `vault` doit être présent (`Template: vault`).

## Déploiement

Chaque push sur `main` déploie le thème par FTP vers le staging :
`/staging/wp-content/themes/vault-child/` — voir [`.github/workflows/deploy-staging.yml`](.github/workflows/deploy-staging.yml).

Secrets requis (GitHub → *Settings > Secrets and variables > Actions*) :
`STAGING_FTP_SERVER`, `STAGING_FTP_USERNAME`, `STAGING_FTP_PASSWORD`.

> Ce workflow cible **uniquement** le staging — la production n'est pas touchée.
