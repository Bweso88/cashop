# Base de données Cashop

PostgreSQL 16. Implémenté en phase 2 : `backend/database/migrations/`, 54 tables.

Commandes utiles :

```bash
php artisan migrate --seed          # schéma + données de référence (+ démo en local/testing)
php artisan test                    # tests sur la base cashop_test (PostgreSQL requis)
```

## Garanties portées par la base

| Garantie | Mécanisme |
|---|---|
| Valeurs de statut/type valides | Contraintes `CHECK` générées depuis les enums PHP (`App\Support\Database\Check::enum`) |
| Ledger équilibré par devise | Trigger de contrainte différé `ledger_entries_balanced` (vérifié au COMMIT) |
| ≥ 2 écritures par transaction comptable | Trigger différé `ledger_transactions_min_entries` |
| Devise écriture = devise compte | Trigger `ledger_entries_currency` |
| Ajout seul | Trigger `cashop_forbid_mutation` sur `ledger_transactions`, `ledger_entries`, `transfer_events`, `risk_events`, `audit_logs` |
| Capacité supportée ⇒ confirmée | `CHECK (NOT is_supported OR confirmation_status = 'CONFIRMED')` |
| Quatre yeux sur les remboursements | `CHECK (approved_by <> requested_by)` |
| Montants cohérents | `total_debit = send_amount + total_fees`, montants > 0, soldes ≥ 0 |
| Anti-rejeu webhooks | Uniques (`provider_id`, `payload_sha256`) et (`provider_id`, `provider_event_id`) |
| Idempotence | Unique (`user_id`, `key`) ; `COMPLETED` ⇒ réponse enregistrée |
| Aucun FLOAT | Vérifié par le test `SchemaTest` |

## Données initiales (seeders)

| Seeder | Environnements | Contenu |
|---|---|---|
| `Reference\*` | tous | Devises (XAF, EUR, USD, GBP actives), pays (tous désactivés), 5 providers (désactivés) et leur matrice de capacités, niveaux KYC, rôles et permissions, comptes système du ledger, catalogue des règles de risque (inactives, sans seuil) |
| `Development\*` | local, testing | Providers en mode `mock` et activés, corridors de test (`settings.fixture = true`, `NOT_CONFIRMED`), frais/plafonds `DEV_ONLY`, taux `DEV_ONLY_ILLUSTRATIVE` (sauf la parité fixe EUR/XAF), comptes de démo (`*@cashop.test`) |

Seules 3 capacités sont `CONFIRMED` (MoneyGram : quote, update, commit), avec le lien vers la documentation.

## Précisions apportées en phase 2

- `users.failed_pin_count` : verrouillage du PIN de transaction.
- `providers.mock_adapter_class` : classe Mock associée à chaque provider.
- `kyc_levels.rank` : ordre des niveaux ; `kyc_documents.side` (`FRONT`/`BACK`).
- `ledger_accounts.is_system` ; comptes système supplémentaires `refunds_payable` et `fx_position`.
- `transfers.status_poll_count` / `next_status_poll_at` : planification du polling ; unique
  (`provider_id`, `provider_reference`).
- `refunds.idempotency_key`, `disputes.resolved_at`, `compliance_cases.closed_at`, `webhooks.source_ip`,
  `idempotency_keys.response_status`, `provider_transactions.is_success` / `correlation_id`.
- Tables Laravel : `sessions`, `password_reset_tokens`, `cache`, `jobs`, `personal_access_tokens` (Sanctum,
  clés UUID), tables `spatie/laravel-permission` (clés UUID).

## Conventions

| Sujet | Règle |
|---|---|
| Clés primaires | `uuid` (UUID v7, triable dans le temps) ; exposées telles quelles dans l'API |
| Montants | `bigint` en **unités mineures** ISO 4217 (XAF : 0 décimale, EUR/USD/GBP : 2). **Jamais de FLOAT** |
| Devises | `char(3)` ISO 4217, clé étrangère vers `currencies.code` |
| Taux de change | `numeric(24,12)` |
| Pourcentages | `integer` en points de base (1 % = 100 bps) |
| Dates | `timestamptz`, stockées en UTC |
| États | `varchar` + contrainte `CHECK` alignée sur l'enum PHP |
| Données personnelles | chiffrées au niveau applicatif (casts `encrypted`) + colonne `*_hash` (HMAC) pour la recherche exacte |
| Suppression | pas de suppression physique des données financières ; `deleted_at` seulement pour les données non financières |
| JSON | `jsonb` |

## ERD (vue d'ensemble)

```
users ─1:1─ user_profiles           users ─1:n─ user_devices          users ─n:n─ roles (spatie)
users ─1:1─ kyc_profiles ─1:n─ kyc_documents
                         └─1:n─ kyc_verifications                    kyc_levels ─1:n─ kyc_profiles
users ─1:n─ wallets ─1:n─ wallet_balances (1 par devise)
                    └─1:n─ wallet_transactions ─n:1─ ledger_transactions
ledger_accounts ─1:n─ ledger_entries ─n:1─ ledger_transactions
users ─1:n─ beneficiaries
users ─1:n─ transfer_quotes ─1:1─ transfers ─1:n─ transfer_events
                                            ├─1:n─ provider_transactions ─n:1─ providers
                                            ├─1:n─ refunds
                                            └─1:n─ disputes
providers ─1:n─ provider_capabilities
providers ─1:n─ provider_country_configs ─n:1─ countries ─n:1─ currencies
countries ─1:n─ country_payment_methods / country_payout_methods
providers ─1:n─ webhooks            providers ─1:n─ provider_health_checks
fees · fx_rates · limit_rules · risk_rules
users/transfers ─1:n─ risk_events · risk_scores · compliance_cases
notifications · audit_logs · idempotency_keys
```

## Identité

### users
| Colonne | Type | Notes |
|---|---|---|
| id | uuid PK | |
| email | varchar(255) unique | normalisé en minuscules |
| phone_e164 | varchar(16) unique null | format E.164 |
| password | varchar | Argon2id |
| transaction_pin_hash | varchar null | Argon2id, distinct du mot de passe |
| status | varchar | `ACTIVE`, `LOCKED`, `SUSPENDED`, `CLOSED` |
| email_verified_at / phone_verified_at | timestamptz null | |
| mfa_secret | text null | chiffré (TOTP) |
| mfa_enabled_at | timestamptz null | |
| failed_login_count / locked_until | int / timestamptz | |
| last_login_at / last_login_ip | | |
| created_at / updated_at | | |

### user_profiles
`user_id` (PK, FK), `first_name`, `last_name` (chiffrés), `date_of_birth` (chiffré), `nationality` char(2),
`country_of_residence` char(2), `address_line1/2`, `city`, `postal_code` (chiffrés), `preferred_currency` char(3),
`locale`.

### user_devices
`id`, `user_id`, `device_id`, `platform` (`ios`/`android`/`web`), `public_key` (clé liée à l'appareil pour la
signature biométrique), `push_token`, `trusted_at`, `last_seen_at`, `revoked_at`.

### otp_codes
`id`, `user_id` null, `channel` (`sms`/`email`), `destination_hash`, `purpose` (`login`, `register`,
`transfer`, `reset`), `code_hash`, `attempts`, `expires_at`, `consumed_at`. (Limitation de débit dans Redis.)

### roles / permissions
Tables du paquet `spatie/laravel-permission`. Rôles : `customer`, `support`, `compliance`, `finance`,
`admin`, `super_admin`.

## Référentiels multi-pays

### currencies
`code` char(3) PK, `name`, `minor_units` smallint, `is_active`. Initial : `XAF`(0), `EUR`(2), `USD`(2), `GBP`(2).

### countries
`code` char(2) PK, `name`, `default_currency` FK, `dial_code`, `is_send_enabled`, `is_receive_enabled`.

### providers
`id` uuid, `code` unique (`visa_direct`, `moneygram`, `western_union`, `mtn_momo`, `airtel_money`), `name`,
`adapter_class`, `mode` (`mock`/`sandbox`/`production`), `is_enabled`, `health_status`
(`CONNECTED`/`DEGRADED`/`OFFLINE`/`UNKNOWN`), `health_checked_at`.

### provider_capabilities
`provider_id`, `capability` (`quote`, `create`, `commit`, `status`, `cancel`, `refund`, `webhook`,
`collection`, `disbursement`, `remittance`, `name_lookup`), `is_supported`, `confirmation_status`
(`CONFIRMED` / `NOT_CONFIRMED`), `source_reference` (lien vers la documentation officielle). Unique
(`provider_id`, `capability`).

### provider_country_configs
| Colonne | Notes |
|---|---|
| id, provider_id, country_code, currency_code | unique (provider, country, currency) |
| direction | `SEND`, `RECEIVE`, `BOTH` |
| mode | `mock` / `sandbox` / `production` (surcharge le mode global) |
| is_enabled | activation fine |
| settings | jsonb non sensible (ex. `target_environment`) |
| credentials_ref | **référence** vers le gestionnaire de secrets, jamais la valeur |
| min_amount / max_amount | bigint null — **NOT_CONFIRMED** tant que non fourni par le provider |
| confirmation_status | `CONFIRMED` / `NOT_CONFIRMED` |

### country_payment_methods / country_payout_methods
`country_code`, `method` (`wallet`, `card`, `mobile_money`, `bank_account`, `cash_pickup`), `provider_id` null,
`currency_code`, `is_enabled`. Permettent d'activer/désactiver une combinaison pays × provider × devise × méthode.

## KYC

### kyc_levels
`code` (`LEVEL_0`, `LEVEL_1`, `LEVEL_2`, …), `name`, `requirements` jsonb (documents requis), `description`.
Les plafonds associés sont dans `limit_rules` (configurables, **aucune valeur réglementaire par défaut**).

### kyc_profiles
`id`, `user_id` unique, `level_code`, `status` (`PENDING`, `IN_REVIEW`, `VERIFIED`, `REJECTED`, `EXPIRED`),
`verified_at`, `expires_at`, `reviewed_by`, `rejection_reason`, `risk_rating` (`LOW`/`MEDIUM`/`HIGH`).

### kyc_documents
`id`, `kyc_profile_id`, `type` (`NATIONAL_ID`, `PASSPORT`, `DRIVER_LICENSE`, `PROOF_OF_ADDRESS`, `SELFIE`),
`number_encrypted`, `number_hash`, `issuing_country`, `issued_at`, `expires_at`, `storage_path` (stockage objet
chiffré, accès par URL signée courte), `mime_type`, `sha256`, `status`.

### kyc_verifications
`id`, `kyc_profile_id`, `vendor` (ou `manual`), `vendor_reference`, `check_type` (`DOCUMENT`, `LIVENESS`,
`FACE_MATCH`, `SANCTIONS`, `PEP`), `result` (`PASS`/`FAIL`/`REVIEW`), `score` numeric(5,4) null,
`raw_response` jsonb (chiffré si données personnelles), `performed_by`, `created_at`.

## Wallet

### wallets
`id`, `user_id`, `type` (`PERSONAL`, `SHARED`, `SAVINGS`), `status` (`ACTIVE`, `FROZEN`, `CLOSED`), `label`.

### wallet_balances (projection)
`wallet_id`, `currency_code` (unique ensemble), `ledger_account_id` (1:1), `available` bigint,
`reserved` bigint, `version` int (verrou optimiste), `updated_at`. **Pas la source de vérité** : recalculable
depuis `ledger_entries`.

### wallet_transactions
Vue métier lisible par l'utilisateur : `id`, `wallet_id`, `currency_code`, `type` (`DEPOSIT`, `WITHDRAWAL`,
`TRANSFER_OUT`, `TRANSFER_IN`, `FEE`, `REFUND`, `REVERSAL`), `amount` bigint signé, `ledger_transaction_id` FK,
`transfer_id` null, `description`, `created_at`.

## Ledger

### ledger_accounts
| Colonne | Notes |
|---|---|
| id | uuid |
| code | unique, ex. `wallet:{wallet_id}:EUR`, `in_flight:EUR`, `provider_clearing:mtn_momo:XAF`, `fee_revenue:EUR`, `fx_revenue:EUR`, `suspense:EUR` |
| type | `ASSET`, `LIABILITY`, `REVENUE`, `EXPENSE`, `EQUITY` |
| currency_code | un compte = une devise |
| normal_balance | `DEBIT` ou `CREDIT` |
| owner_type / owner_id | polymorphique (wallet, provider, système) |

### ledger_transactions
`id`, `type` (`TRANSFER_RESERVE`, `TRANSFER_SETTLE`, `TRANSFER_REVERSE`, `DEPOSIT`, `REFUND`, `FX_CONVERSION`,
`ADJUSTMENT`), `reference_type` / `reference_id` (ex. transfer), `idempotency_key` unique, `description`,
`posted_at`, `created_by`.

### ledger_entries (immuable)
`id`, `ledger_transaction_id`, `ledger_account_id`, `direction` (`DEBIT`/`CREDIT`), `amount` bigint **> 0**,
`currency_code`, `created_at`.

Invariants :
1. Par `ledger_transaction` et par devise : `SUM(amount) WHERE DEBIT = SUM(amount) WHERE CREDIT`.
   Vérifié en PHP avant insertion **et** par un trigger `CONSTRAINT TRIGGER … DEFERRABLE INITIALLY DEFERRED`.
2. `ledger_entries` : trigger refusant `UPDATE` et `DELETE`.
3. Une conversion de devises = deux jambes, chacune équilibrée dans sa devise, via des comptes `fx_position:{devise}`.

Exemple — envoi de 100,00 EUR avec 2,00 EUR de frais :

| Étape | Débit | Crédit |
|---|---|---|
| Réservation | `wallet:{w}:EUR` 10 200 | `in_flight:EUR` 10 200 |
| Succès | `in_flight:EUR` 10 200 | `provider_clearing:{p}:EUR` 10 000 · `fee_revenue:EUR` 200 |
| Échec | `in_flight:EUR` 10 200 | `wallet:{w}:EUR` 10 200 |

## Transferts

### beneficiaries
`id`, `user_id`, `name` (chiffré), `country_code`, `payout_method`, `details` jsonb **chiffré** (MSISDN, PAN
tokenisé, IBAN…), `details_hash`, `nickname`, `last_used_at`. **Aucun PAN en clair** : tokenisation ou stockage
chez le provider.

### transfer_quotes
`id`, `user_id`, `provider_id`, `send_amount`, `send_currency`, `receive_amount`, `receive_currency`,
`fx_rate` numeric(24,12), `fx_rate_source`, `fee_breakdown` jsonb (par composant du FeeEngine), `total_fees`,
`total_debit`, `payment_method`, `payout_method`, `destination_country`, `provider_quote_reference` null,
`expires_at`, `status` (`ACTIVE`, `USED`, `EXPIRED`).

### transfers
| Colonne | Notes |
|---|---|
| id | = `transaction_id` exposé |
| user_id, wallet_id, quote_id, beneficiary_id, provider_id | |
| status | voir cycle de vie ([ARCHITECTURE.md §5](ARCHITECTURE.md#5-cycle-de-vie-dun-transfert)) |
| send_amount, send_currency, receive_amount, receive_currency, fx_rate, total_fees, total_debit | figés à la confirmation |
| payment_method, payout_method, destination_country | |
| correlation_id | propagé dans tous les logs |
| external_reference | référence Cashop envoyée au provider (unique) |
| provider_reference / provider_request_id | renvoyés par le provider |
| pickup_code | chiffré (MTCN, référence de retrait) |
| failure_code / failure_reason | normalisés Cashop + message provider |
| idempotency_key | |
| authorized_at, submitted_at, completed_at, failed_at | |

### transfer_events
`id`, `transfer_id`, `from_status`, `to_status`, `source` (`API`, `WEBHOOK`, `POLLING`, `ADMIN`, `SYSTEM`),
`actor_id` null, `payload` jsonb, `correlation_id`, `created_at`. (Timeline.)

### provider_transactions
Chaque appel sortant : `id`, `transfer_id`, `provider_id`, `operation` (`QUOTE`, `CREATE`, `COMMIT`, `STATUS`,
`CANCEL`, `REFUND`), `request_id`, `http_status`, `latency_ms`, `request_body`/`response_body` jsonb
(**masqués** : pas de PAN, PIN, secret), `error_code`, `created_at`. Alimente la santé des providers.

### refunds
`id`, `transfer_id`, `amount`, `currency_code`, `reason`, `status` (`REQUESTED`, `APPROVED`, `PROCESSING`,
`COMPLETED`, `FAILED`, `REJECTED`), `requested_by`, `approved_by` (≠ requested_by : 4-eyes),
`provider_reference`, `ledger_transaction_id`.

### disputes
`id`, `transfer_id`, `opened_by`, `reason`, `status` (`OPEN`, `INVESTIGATING`, `RESOLVED_CUSTOMER`,
`RESOLVED_MERCHANT`, `CLOSED`), `assigned_to`, `resolution`, timestamps.

## Tarification et change

### fees
Règles évaluées par le `FeeEngine` : `id`, `name`, `component` (`FIXED`, `PERCENTAGE`, `PROVIDER`, `FX`,
`COUNTRY`, `PAYMENT_METHOD`, `PAYOUT_METHOD`), critères nullable (`provider_id`, `source_country`,
`destination_country`, `currency_code`, `payment_method`, `payout_method`, `min_amount`, `max_amount`),
`fixed_amount` bigint, `percentage_bps` int, `priority`, `valid_from`, `valid_to`, `is_active`.
Les frais **propres au provider** viennent de sa quote quand l'API en fournit ; sinon de la grille
contractuelle saisie ici — **NOT_CONFIRMED** tant que non reçue.

### fx_rates
`id`, `base_currency`, `quote_currency`, `rate` numeric(24,12), `spread_bps`, `source` (`MANUAL`, `PROVIDER`,
nom du fournisseur), `valid_from`, `valid_to`, `created_by`. Le taux appliqué est **copié** dans
`transfer_quotes` / `transfers`.

## Risque et conformité

### limit_rules
`id`, `kyc_level_code`, `scope` (`PER_TRANSACTION`, `DAILY`, `MONTHLY`), `currency_code`, `max_amount`,
`max_count`, `country_code` null. **Valeurs à fournir par la conformité** ; aucune valeur par défaut en production.

### risk_rules
`id`, `code` (`VELOCITY`, `AMOUNT_THRESHOLD`, `DUPLICATE`, `NEW_DEVICE`, `GEO_MISMATCH`, `SANCTIONS_HIT`…),
`parameters` jsonb, `action` (`ALLOW`, `REVIEW`, `BLOCK`, `STEP_UP_AUTH`), `score_weight`, `is_active`.

### risk_events
`id`, `user_id`, `transfer_id` null, `rule_code`, `score`, `action_taken`, `details` jsonb, `created_at`.

### risk_scores
`id`, `subject_type` (`USER`, `TRANSFER`), `subject_id`, `score` smallint (0–100), `factors` jsonb, `computed_at`.

### compliance_cases
`id`, `user_id`, `transfer_id` null, `type` (`AML_ALERT`, `SANCTIONS`, `KYC_REVIEW`, `FRAUD`, `SAR`),
`status` (`OPEN`, `IN_REVIEW`, `ESCALATED`, `CLOSED_NO_ACTION`, `CLOSED_REPORTED`), `priority`, `assigned_to`,
`notes` (chiffrées), timestamps.

## Webhooks, notifications, audit, idempotence

### webhooks
`id` (event ID Cashop), `provider_id`, `provider_event_id` null, `signature_valid` bool, `received_at`,
`provider_timestamp` null, `headers` jsonb (filtrés), `payload` jsonb, `payload_sha256`, `status`
(`RECEIVED`, `PROCESSED`, `IGNORED`, `FAILED`, `REJECTED`), `transfer_id` null, `processed_at`, `error`.
Unique (`provider_id`, `provider_event_id`) et (`provider_id`, `payload_sha256`) → anti-rejeu.

### notifications
`id`, `user_id`, `channel` (`PUSH`, `SMS`, `EMAIL`, `IN_APP`), `type`, `title`, `body`, `data` jsonb,
`sent_at`, `read_at`, `status`.

### audit_logs (ajout seul)
`id` bigserial, `actor_type` (`USER`, `ADMIN`, `SYSTEM`, `PROVIDER`), `actor_id`, `action`, `subject_type`,
`subject_id`, `ip`, `user_agent`, `correlation_id`, `changes` jsonb (avant/après, champs sensibles masqués),
`created_at`, `hash`, `previous_hash` (chaînage pour détecter une altération). Trigger interdisant UPDATE/DELETE.

### idempotency_keys
| Colonne | Notes |
|---|---|
| key | varchar(100) |
| user_id | unique (`user_id`, `key`) |
| endpoint | méthode + route |
| request_hash | SHA-256 du corps canonique |
| response | jsonb (statut + corps renvoyés à l'identique) |
| status | `PROCESSING`, `COMPLETED`, `FAILED` |
| created_at / expires_at | TTL configurable (`IDEMPOTENCY_TTL_HOURS`) |

Même clé + même hash → réponse rejouée. Même clé + hash différent → `409 IDEMPOTENCY_CONFLICT`.
Clé en `PROCESSING` → `409 REQUEST_IN_PROGRESS`. Verrou Redis + contrainte unique contre les courses.

### provider_health_checks
`id`, `provider_id`, `country_code` null, `checked_at`, `status`, `latency_ms`, `error`. Agrégé avec
`provider_transactions` pour l'écran Provider Health (latence, taux de succès, dernière transaction réussie,
dernière erreur).
