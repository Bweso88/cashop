# API Cashop

Spécification de référence : [`openapi.yaml`](openapi.yaml) (OpenAPI 3.1, validée par `redocly lint`).
Base : `/api/v1` (clients) et `/api/admin/v1` (back-office : personnel, MFA obligatoire ; `GET /me` disponible, le reste en phase 10).

## Conventions

| Sujet | Règle |
|---|---|
| Format | JSON, `snake_case` |
| Montants | Entiers en unités mineures + code devise (`{ "amount": 10000, "currency": "EUR" }`) |
| Taux | Chaînes décimales (`"655.957000000000"`) pour éviter les arrondis |
| Dates | ISO 8601 UTC |
| Pagination | Par curseur (`cursor`, `per_page`) |
| Versionnage | Dans l'URL (`/v1`) ; changements incompatibles = nouvelle version |
| Traçabilité | `X-Correlation-Id` accepté en entrée, toujours renvoyé |
| Erreurs | `{ "code", "message", "errors", "correlation_id" }` |

## Endpoints v1

| Méthode | Route | Rôle | Idempotency-Key |
|---|---|---|---|
| POST | `/auth/register` | Inscription + envoi OTP | — |
| POST | `/auth/login` | Connexion (défi OTP/MFA) | — |
| POST | `/auth/verify-otp` | Validation du second facteur → jeton | — |
| POST | `/auth/logout` | Révocation du jeton | — |
| PUT | `/auth/pin` | PIN de transaction | — |
| POST / DELETE | `/auth/mfa/totp` | Démarrer / désactiver la MFA TOTP | — |
| POST | `/auth/mfa/totp/confirm` | Confirmer la MFA | — |
| POST | `/auth/device/key` | Clé publique biométrique de l'appareil | — |
| POST | `/auth/device/challenge` | Challenge à signer | — |
| GET | `/profile` | Profil | — |
| GET | `/wallet` | Portefeuilles et soldes | — |
| GET | `/wallet/transactions` | Historique | — |
| POST | `/transfers/quote` | Comparaison des offres | — |
| POST | `/transfers` | Création et exécution | **Oui** |
| GET | `/transfers/{id}` | Détail + timeline | — |
| POST | `/transfers/{id}/cancel` | Annulation | **Oui** |
| POST | `/transfers/{id}/refund` | Demande de remboursement | **Oui** |
| GET/POST | `/beneficiaries` | Bénéficiaires | — |
| GET | `/kyc` · POST `/kyc/documents` | KYC | — |
| GET | `/providers` | Providers actifs | — |
| GET | `/providers/{provider}/capabilities` | Capacités | — |
| POST | `/webhooks/{provider}` | Notifications providers | (anti-rejeu interne) |

## Codes d'erreur métier

| Code | HTTP | Signification |
|---|---|---|
| `VALIDATION_ERROR` | 422 | Données invalides |
| `UNAUTHENTICATED` | 401 | Jeton absent ou expiré |
| `INVALID_CREDENTIALS` | 401 | Identifiants incorrects (sans préciser lequel) |
| `INVALID_OTP` | 401 | Code invalide, expiré, déjà utilisé ou tentatives épuisées |
| `ACCOUNT_LOCKED` | 423 | Verrouillage temporaire après échecs |
| `PIN_INVALID` / `PIN_LOCKED` | 401 / 423 | PIN de transaction |
| `MFA_REQUIRED` / `STAFF_ONLY` | 403 | Accès back-office |
| `TOO_MANY_REQUESTS` | 429 | Limite de débit (en-tête `Retry-After`) |
| `KYC_REQUIRED` | 403 | Niveau KYC insuffisant |
| `RISK_REVIEW` / `RISK_BLOCKED` | 403 | Décision du moteur de risque |
| `STEP_UP_REQUIRED` | 403 | OTP supplémentaire exigé |
| `LIMIT_EXCEEDED` | 422 | Plafond atteint |
| `INSUFFICIENT_FUNDS` | 422 | Solde insuffisant |
| `QUOTE_EXPIRED` | 409 | Quote expirée |
| `INVALID_STATE` | 409 | Opération incompatible avec le statut |
| `IDEMPOTENCY_CONFLICT` | 409 | Clé réutilisée avec un autre corps |
| `REQUEST_IN_PROGRESS` | 409 | Requête identique en cours |
| `PROVIDER_UNAVAILABLE` | 503 | Aucun provider disponible pour le corridor |

## Exemple de parcours

```
POST /transfers/quote          → quotes[] (id, provider, frais, taux, total, expires_at)
POST /transfers                → 201 { status: "FUNDS_RESERVED" | "SUBMITTED", ... }
  Idempotency-Key: 0b8f…       (même clé en cas de nouvel essai réseau)
GET  /transfers/{id}           → statut + timeline, jusqu'à COMPLETED / FAILED
```

## Génération des clients

- `web/` et `admin/` : types TypeScript générés depuis `openapi.yaml` (`openapi-typescript`).
- `mobile/` : modèles Dart générés (`openapi-generator`, cible `dart-dio`) ou écrits à la main avec freezed.
- Tests de contrat côté Laravel : chaque réponse est validée contre la spécification.
