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
| GET | `/catalog/funding-providers` | Portefeuilles Mobile Money de paiement (MTN, Airtel) | — |
| GET | `/catalog/networks` | Réseaux (WU, MoneyGram, Ria) et destinations | — |
| GET | `/catalog/networks/{network}/fields` | Champs bénéficiaire exigés pour un corridor | — |
| GET | `/catalog/simulation-scenarios` | Scénarios de démonstration (vide hors simulation) | — |
| POST | `/transfers/quote` | Devis : frais détaillés, taux, montant reçu, expiration | — |
| POST | `/transfers` | Confirmation (PIN Cashop) et demande de débit Mobile Money | **Oui** |
| GET | `/transfers` | Historique (`state`, `q`) | — |
| GET | `/transfers/{id}` | Détail + chronologie | — |
| POST | `/transfers/{id}/refresh` | Relire le statut auprès des prestataires | — |
| GET | `/transfers/{id}/receipt` | Reçus (transfert livré, remboursement) | — |
| GET/POST/PUT/DELETE | `/beneficiaries` | Bénéficiaires | — |
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
| `LIMITS_NOT_CONFIGURED` | 403 | Aucun plafond défini pour le niveau KYC (envoi refusé par prudence) |
| `LIMIT_EXCEEDED` | 422 | Plafond par opération, jour ou mois dépassé |
| `CORRIDOR_UNAVAILABLE` | 422 | Réseau, pays ou mode de retrait non proposé |
| `ENVIRONMENT_MISMATCH` | 422 | Portefeuille et réseau dans des environnements différents |
| `QUOTE_EXPIRED` | 409 | Devis expiré ou déjà utilisé |
| `RECEIPT_NOT_AVAILABLE` | 409 | Ni livré ni remboursé |
| `PROVIDER_UNAVAILABLE` | 503 | Connecteur absent ou environnement désactivé |
| `INVALID_WEBHOOK` | 401 | Signature ou horodatage de notification invalide |
| `RISK_REVIEW` / `RISK_BLOCKED` | 403 | Décision du moteur de risque |
| `STEP_UP_REQUIRED` | 403 | OTP supplémentaire exigé |
| `INVALID_STATE` | 409 | Opération incompatible avec le statut |
| `IDEMPOTENCY_CONFLICT` | 409 | Clé réutilisée avec un autre corps |
| `REQUEST_IN_PROGRESS` | 409 | Requête identique en cours |

## Exemple de parcours

```
GET  /catalog/networks                     → réseaux, pays, modes de retrait
POST /transfers/quote                      → 201 devis (frais, taux, reçu, expires_at)
POST /transfers   Idempotency-Key: 0b8f…   → 201 { status: "PAYMENT_PENDING" }
     (le client valide le débit sur son téléphone, auprès de son opérateur)
POST /transfers/{id}/refresh               → PAYMENT_CONFIRMED → TRANSFER_AVAILABLE_FOR_PICKUP
GET  /transfers/{id}/receipt               → reçu (mention « SIMULATION » en démonstration)
```

Cycle de vie complet : [TRANSFER-STATES.md](TRANSFER-STATES.md).

## Génération des clients

- `web/` et `admin/` : types TypeScript générés depuis `openapi.yaml` (`openapi-typescript`).
- `mobile/` : modèles Dart générés (`openapi-generator`, cible `dart-dio`) ou écrits à la main avec freezed.
- Tests de contrat côté Laravel : chaque réponse est validée contre la spécification.
