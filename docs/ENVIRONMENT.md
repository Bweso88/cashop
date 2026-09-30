# Variables d'environnement

Fichier modèle : [`../.env.example`](../.env.example). **Aucune valeur réelle dans Git.**
En production, les valeurs viennent du gestionnaire de secrets et sont injectées au démarrage.

| Variable | Obligatoire | Description |
|---|---|---|
| `APP_ENV` | oui | `local`, `testing`, `staging`, `production` |
| `APP_KEY` | oui | Clé de chiffrement Laravel (`php artisan key:generate`) ; rotation via `APP_PREVIOUS_KEYS` |
| `APP_URL` | oui | URL publique de l'API |
| `DB_CONNECTION` | oui | `pgsql` |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | oui | PostgreSQL |
| `REDIS_HOST` / `REDIS_PORT` / `REDIS_PASSWORD` | oui | Redis (cache, files, verrous, rate limit) |
| `WEB_URL` / `ADMIN_URL` | oui | Origines autorisées (CORS, Sanctum) |
| `SANCTUM_STATEFUL_DOMAINS` / `SESSION_DOMAIN` | oui | Sessions cookies du web et de l'admin |
| `WEBHOOK_SIGNING_SECRET` | oui | HMAC des webhooks des mocks et des jetons d'URL de callback |
| `IDEMPOTENCY_TTL_HOURS` | non (24) | Durée de conservation des clés d'idempotence |
| `<PROVIDER>_MODE` | oui | `mock`, `sandbox`, `production` — `mock` par défaut |

## Providers

| Variable | Usage | Statut |
|---|---|---|
| `VISA_USERNAME` / `VISA_PASSWORD` | Identifiants de projet Visa (Basic auth sur mTLS) | Mécanisme OFFICIEL, valeurs à obtenir |
| `VISA_CERTIFICATE` / `VISA_PRIVATE_KEY` / `VISA_CA_BUNDLE` | **Chemins** vers les fichiers mTLS montés | idem |
| `VISA_API_KEY` | Requise pour certaines API Visa | NOT CONFIRMED pour Visa Direct |
| `VISA_MLE_*` | Message Level Encryption (Key ID, certificat serveur, clé privée client) | MLE requis (OFFICIEL), détails à obtenir |
| `MONEYGRAM_CLIENT_ID` / `MONEYGRAM_CLIENT_SECRET` | OAuth 2.0 | Mécanisme OFFICIEL |
| `MONEYGRAM_PARTNER_ID` | Identifiant partenaire | NOT CONFIRMED |
| `WESTERN_UNION_*` | URL, identifiants, certificats | NOT CONFIRMED (contrat partenaire) |
| `MTN_CLIENT_ID` / `MTN_CLIENT_SECRET` | Variables génériques demandées | Le modèle réel (clé d'abonnement + API user/API key par produit **et par pays**) est à confirmer ; la configuration par pays vivra dans `config/providers/mtn.php` avec des variables `MTN_<PAYS>_<PRODUIT>_*` |
| `AIRTEL_CLIENT_ID` / `AIRTEL_CLIENT_SECRET` | OAuth 2.0 client credentials | PUBLIC-SECONDAIRE ; configuration par pays dans `config/providers/airtel.php` |

## Services transverses

| Variable | Description |
|---|---|
| `FX_RATES_PROVIDER` | `manual` (saisie admin) tant qu'aucun fournisseur de taux n'est choisi |
| `KYC_VENDOR` | `manual` tant qu'aucun fournisseur KYC n'est choisi |
| `SANCTIONS_SCREENING_VENDOR` | `none` en dev ; **obligatoire** avant la production |
| `SMS_PROVIDER` | `log` en dev (les OTP sont écrits dans les logs locaux uniquement) |
| `SENTRY_LARAVEL_DSN` / `OTEL_EXPORTER_OTLP_ENDPOINT` | Observabilité |

## Front-ends

`web/` et `admin/` n'ont **que** des variables non sensibles : `NEXT_PUBLIC_APP_URL` et l'URL interne de
l'API (`CASHOP_API_URL`, lue côté serveur par le BFF). Aucune clé de provider.
