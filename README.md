# Cashop

Plateforme fintech d'envoi, de réception et de gestion d'argent, multi-providers :
Visa Direct, MoneyGram, Western Union, MTN Mobile Money et Airtel Money, avec la possibilité d'en ajouter
d'autres (Orange Money, Wave, banques…).

```
Mobile (Flutter) / Web (Next.js) / Admin (Next.js)
        ↓
    Cashop API (Laravel)
        ↓
    PaymentOrchestrator
        ↓
    Provider Adapter (réel ou Mock)
        ↓
    API externe du provider
```

Les applications clientes ne communiquent **jamais** directement avec les API financières externes.

## État du projet

| Phase | Contenu | État |
|---|---|---|
| 1 | Architecture, documentation, OpenAPI v0, infrastructure Docker | ✅ |
| 2 | Base de données : 54 tables, invariants PostgreSQL, seeders, tests | ✅ |
| 3 | Authentification : inscription, OTP, MFA TOTP, PIN, biométrie, RBAC, audit chaîné | ✅ |
| 4–13 | Wallet, Ledger, Mocks, Orchestrateur, API, Webhooks, Admin, Mobile, Sécurité, Tests | À venir |
| 14 | Intégrations réelles des providers | À venir |

Tous les providers fonctionnent en mode **mock** jusqu'à la phase 14.

## Stack

| Couche | Technologies |
|---|---|
| Backend | Laravel (PHP 8.3+), PostgreSQL 16, Redis 7, Queues/Horizon, Events, Jobs, REST + OpenAPI |
| Web clients (`web/`) | Next.js, React, TypeScript, Tailwind CSS |
| Admin (`admin/`) | Next.js, React, TypeScript, Tailwind CSS (application séparée) |
| Mobile (`mobile/`) | Flutter, Dart |
| Infrastructure | Docker, Nginx, PostgreSQL, Redis |

## Démarrage

Avec Docker :

```bash
cp .env.example .env    # définir au minimum DB_PASSWORD et APP_KEY
docker compose up -d postgres redis mailpit
docker compose --profile backend up -d
docker compose exec api php artisan migrate --seed
```

Sans Docker (PHP 8.3+, Composer, PostgreSQL 16) :

```bash
cd backend
cp .env.example .env    # DB_HOST=127.0.0.1, identifiants PostgreSQL locaux
composer install
php artisan key:generate
php artisan migrate --seed
php artisan test        # nécessite une base cashop_test
```

Comptes de démonstration (local uniquement) : `client@cashop.test`, `admin@cashop.test`…
(mot de passe dans `database/seeders/Development/DemoUserSeeder.php`).

## Documentation

| Document | Contenu |
|---|---|
| [ARCHITECTURE.md](docs/ARCHITECTURE.md) | Vue d'ensemble, modules, orchestrateur, adapters, cycle de vie, mobile, web |
| [DATABASE.md](docs/DATABASE.md) | Schéma complet, conventions, ledger en partie double |
| [API.md](docs/API.md) · [openapi.yaml](docs/openapi.yaml) | API REST v1 |
| [SECURITY.md](docs/SECURITY.md) | Authentification, RBAC, secrets, webhooks, mobile |
| [PROVIDERS.md](docs/PROVIDERS.md) | Ce qui est confirmé / NOT CONFIRMED pour chaque provider |
| [KYC-AML.md](docs/KYC-AML.md) | Niveaux KYC, moteur de risque, conformité |
| [DEPLOYMENT.md](docs/DEPLOYMENT.md) | Environnements, CI/CD, production |
| [ENVIRONMENT.md](docs/ENVIRONMENT.md) | Variables d'environnement |
| [INTEGRATION-GUIDE.md](docs/INTEGRATION-GUIDE.md) | Les 18 étapes pour intégrer un provider réel |
| [adr/](docs/adr/) | Décisions d'architecture |

## Cadre réglementaire

L'exploitation d'un service de transfert d'argent nécessite un agrément, ou un partenariat avec un
établissement agréé, dans chaque pays concerné. Les règles KYC/AML et les plafonds sont configurables :
aucune valeur réglementaire n'est codée en dur (voir [KYC-AML.md](docs/KYC-AML.md)).
