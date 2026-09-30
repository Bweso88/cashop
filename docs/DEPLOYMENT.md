# Déploiement

## Environnements

| Environnement | Providers | Données | Accès |
|---|---|---|---|
| `local` | `mock` | Seeders fictifs | Développeurs |
| `staging` | `mock` puis `sandbox` | Fictives | Équipe + recette |
| `production` | `production` (provider par provider, après certification) | Réelles | Restreint, 4-eyes |

## Développement local

```bash
cp .env.example .env            # renseigner DB_PASSWORD, WEBHOOK_SIGNING_SECRET…
docker compose up -d postgres redis mailpit            # phase 1
docker compose --profile backend up -d                 # à partir de la phase 2
docker compose --profile web --profile admin up -d     # à partir des phases 10-11
```

| Service | URL |
|---|---|
| API (via Nginx) | http://localhost:8080 |
| Web clients | http://localhost:3000 |
| Admin | http://localhost:3001 |
| Mailpit | http://localhost:8025 |

## Production (cible)

- Images immuables construites en CI (`infra/docker/php` cible `prod`, `infra/docker/node` cible `prod`).
- Orchestration : **NOT CONFIRMED — hébergeur à choisir** (Kubernetes managé ou conteneurs managés).
  Contrainte possible de localisation des données selon le pays d'agrément.
- PostgreSQL managé (haute disponibilité, sauvegardes chiffrées, PITR), Redis managé.
- Processus séparés : `api` (php-fpm + Nginx), `queue` (Horizon, une file par provider), `scheduler`.
- Secrets : gestionnaire de secrets ; certificats mTLS montés en lecture seule dans `api` et `queue` uniquement.
- IP de sortie fixes (NAT) : souvent exigées par les providers pour leurs listes blanches.
- Admin sur un domaine distinct, derrière VPN ou liste d'IP.

## Pipeline CI/CD

1. Lint et analyse statique (Pint, PHPStan niveau max, ESLint, `dart analyze`), `redocly lint`.
2. Tests (Pest, tests de contrat OpenAPI, Vitest/Playwright, tests Flutter).
3. Contrôle des frontières de modules (Deptrac / Pest Arch).
4. Audit des dépendances, détection de secrets (gitleaks), scan des images.
5. Build des images, déploiement staging, tests E2E sur mocks.
6. Production : validation manuelle, migrations rétro-compatibles (expand/contract), déploiement progressif.

## Exploitation

- Health checks : `/health/live`, `/health/ready`.
- Alertes : taux d'échec par provider, transferts `UNKNOWN` > N minutes, écarts de réconciliation,
  profondeur des files, erreurs 5xx.
- Runbooks à rédiger : panne d'un provider, réconciliation manuelle, rotation des certificats, incident de sécurité.
