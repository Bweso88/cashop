# Architecture Cashop

> Statut : **v0 — validée** (monolithe modulaire Laravel, deux applications Next.js séparées).
> Décisions détaillées : [`adr/`](adr/).

## 1. Vue d'ensemble

```
 Flutter (iOS/Android)     web/ Next.js (clients)     admin/ Next.js (back-office)
          │                        │ BFF (cookies httpOnly)       │ BFF (cookies httpOnly, VPN/IP allowlist)
          └──────── HTTPS ─────────┴──────────────┬───────────────┘
                                                  ▼
                          Nginx (TLS, en-têtes, correlation ID, rate limit)
                                                  ▼
 ┌────────────────────────── Cashop API — Laravel (PHP 8.3) ──────────────────────────┐
 │ Http (Controllers minces, FormRequests, Resources)  /api/v1  /api/admin/v1         │
 │   ▼                                                                                 │
 │ Application (Actions) ──► PaymentOrchestrator ──► FeeEngine · FxService · Risk      │
 │                                   ▼                                                 │
 │                          ProviderRegistry ──► Provider Adapters (réels | Mock)      │
 │ Domain : Identity · Wallet · Ledger · Payments · Kyc · Risk · Webhooks · Audit      │
 └──────┬──────────────────┬──────────────────────┬───────────────────┬───────────────┘
   PostgreSQL 16       Redis 7                Horizon workers        Vault / KMS
   (ledger = vérité)   cache, verrous,        jobs, retries,         secrets, certificats
                       rate limit, queue      polling, réconciliation mTLS
                                                  ▼
          Visa Direct · MoneyGram · Western Union · MTN MoMo · Airtel Money · (Orange, Wave, banques…)
```

**Règle absolue :** les clients (mobile, web, admin) ne parlent **qu'à l'API Cashop**. Aucun identifiant,
certificat ou URL de provider n'existe côté front.

## 2. Backend — monolithe modulaire

Chaque module de `backend/app/Domain/<Module>` suit la même structure :

```
Domain/<Module>/
├── Models/          # Eloquent (persistance uniquement, pas de logique métier lourde)
├── Actions/         # Cas d'usage (une classe = une opération : CreateTransferAction…)
├── Services/        # Services de domaine (LedgerService, FeeEngine…)
├── DTO/             # Objets immuables (readonly classes PHP 8.3)
├── Enums/           # États, types (backed enums)
├── Events/          # Événements de domaine
├── Jobs/            # Traitements asynchrones
├── Policies/        # Autorisations
└── Exceptions/
```

| Module | Responsabilité | Dépend de |
|---|---|---|
| `Identity` | Inscription, login, OTP, MFA, PIN, appareils, RBAC | — |
| `Wallet` | Portefeuilles, soldes (projection), historique | Ledger |
| `Ledger` | Partie double, invariants, réconciliation | — |
| `Payments` | Orchestrateur, quotes, transferts, remboursements, litiges | Ledger, Wallet, Fx, Fees, Risk, Kyc, Providers |
| `Providers` | Interface, registry, adapters, clients, mocks, santé | — (n'appelle jamais le domaine) |
| `Fx` / `Fees` | Taux, spreads, règles de frais | — |
| `Kyc` | Profils, documents, vérifications, niveaux, limites | Identity |
| `Risk` | Règles, scores, événements, dossiers de conformité, sanctions | Kyc |
| `Webhooks` | Réception, vérification, anti-rejeu, dispatch | Providers, Payments |
| `Notifications` · `Audit` · `Reporting` | Transverses | — |

Règles de dépendance vérifiées en CI (Deptrac ou Pest Arch) :
- `Providers` ne dépend d'aucun autre module ; il reçoit et renvoie des DTO.
- Les controllers n'appellent que des `Actions` ; aucune logique spécifique à un provider dans `Http/`.
- Seul `Ledger` écrit dans les tables `ledger_*`.

### Asynchronisme

| Événement | Listener / Job | File Horizon |
|---|---|---|
| `TransferAuthorized` | `ExecuteTransferJob` | `providers-{provider}` |
| `TransferSubmitted` | `PollProviderStatusJob` (backoff exponentiel) | `polling` |
| `WebhookReceived` | `ProcessWebhookJob` | `webhooks` |
| `TransferFailed` | `ReverseLedgerJob`, notification | `ledger`, `notifications` |
| Planifié (5 min) | `ReconcileUnknownTransfersJob` | `reconciliation` |
| Planifié (nuit) | `ReconcileLedgerBalancesJob`, `ReconcileProviderStatementsJob` | `reconciliation` |
| Planifié (1 min) | `ProbeProviderHealthJob` | `health` |

Une file par provider isole les pannes : un provider lent ne bloque pas les autres.

## 3. Payment Orchestrator

```
PaymentOrchestrator
├── quote(QuoteRequest): QuoteCollection
│     CapabilityResolver  → providers actifs pour (pays, devise, payment_method, payout_method)
│     QuoteAggregator     → getQuote() en parallèle (timeout par provider, échecs tolérés)
│     FeeEngine / FxService → frais et taux Cashop appliqués
│     LimitChecker        → plafonds KYC (configurables)
│     RiskEngine::preCheck
│     → transfer_quotes (expires_at)
├── execute(quoteId, AuthProof, IdempotencyKey): Transfer
│     Idempotence → KYC → Risk → vérification PIN/biométrie
│     Ledger::reserve()  (même transaction SQL que la création du transfert)
│     dispatch ExecuteTransferJob → createTransaction() → commitTransaction()
├── handleStatus(ProviderStatus)   // depuis webhook ou polling
│     TransferStateMachine::transition() → écritures ledger → événements
├── cancel(transfer) / refund(transfer, amount)
└── RoutingStrategy (meilleur montant reçu | coût | fiabilité | préférence utilisateur)
```

Gestion des erreurs provider :

| Situation | Action |
|---|---|
| Refus explicite (4xx métier) | `FAILED` + extourne ledger immédiate |
| Timeout, 5xx, réseau | `UNKNOWN` → réconciliation par `getTransactionStatus()` ; **jamais** de remboursement à l'aveugle |
| Erreur transitoire avant soumission | Retry (backoff, max N) uniquement si l'opération est idempotente côté provider |
| Taux d'échec élevé | Circuit breaker ouvert → provider exclu des quotes, statut `DEGRADED`/`OFFLINE` |

## 4. Architecture des adapters

```
PaymentProviderInterface
  getCapabilities(): ProviderCapabilities
  getCountries(): array            getCurrencies(): array
  getPaymentMethods(): array       getPayoutMethods(): array
  getQuote(QuoteRequest): ProviderQuote
  createTransaction(TransferRequest): ProviderTransaction
  commitTransaction(ProviderTransaction): ProviderTransaction
  getTransactionStatus(ProviderTransactionRef): ProviderStatus
  cancelTransaction(ProviderTransactionRef): ProviderStatus
  refund(ProviderTransactionRef, Money): ProviderStatus
  handleWebhook(WebhookRequest): WebhookEvent
        ▲
  AbstractProvider   (logs structurés, métriques latence/succès, mapping d'erreurs, correlation ID)
        ▲
  <X>Provider ─► <X>Client (HTTP, mTLS, timeouts) ─► <X>Authenticator (OAuth2 / Basic / certificats)
             ├─► <X>Mapper (DTO Cashop ⇄ payload provider)
             └─► <X>WebhookHandler (signature, timestamp, parsing)
```

- Une opération non offerte par un provider → capacité `false` + `UnsupportedOperationException`.
- Configuration **par pays** (`provider_country_configs`) : un même provider peut avoir des URL, identifiants,
  devises et capacités différents selon le pays (indispensable pour MTN et Airtel).
- Sélection réel/Mock par `<PROVIDER>_MODE` et par ligne `provider_country_configs.mode`.

### Mock providers

`MockVisaProvider`, `MockMoneyGramProvider`, `MockWesternUnionProvider`, `MockMtnProvider`, `MockAirtelProvider`
implémentent la même interface et permettent de tester Cashop de bout en bout sans identifiants.

| Déclencheur (hors production) | Scénario |
|---|---|
| défaut | `PENDING` → `PROCESSING` → `COMPLETED` |
| montant se terminant par `.13` / MSISDN finissant par `0000` | `FAILED` |
| MSISDN finissant par `9999` | reste `PROCESSING` (test du polling / `UNKNOWN`) |
| en-tête `X-Mock-Scenario: refund` | `COMPLETED` puis `REFUNDED` |
| en-tête `X-Mock-Scenario: timeout` | timeout client → `UNKNOWN` |

Les mocks émettent de **faux webhooks signés** (HMAC `WEBHOOK_SIGNING_SECRET`) pour exercer toute la chaîne.
Ils n'imitent **aucun** format réel de provider : ils utilisent un format Cashop documenté.

## 5. Cycle de vie d'un transfert

```
QUOTED ─► AWAITING_CONFIRMATION ─► AUTHORIZED ─► FUNDS_RESERVED ─► SUBMITTED ─► PENDING ─► PROCESSING ─► COMPLETED
   │                │                    │                             │            │            │            │
   ▼                ▼                    ▼                             ▼            └────────────┴─► FAILED ─► REVERSED
 EXPIRED     REJECTED_KYC /        CANCELLED                        UNKNOWN ──(réconciliation)──► PENDING|COMPLETED|FAILED
             REJECTED_RISK                                                          COMPLETED ─► REFUND_PENDING ─► REFUNDED
                                                          PENDING/PROCESSING ─► CANCEL_REQUESTED ─► CANCELLED ─► REVERSED
```

- Transitions autorisées définies dans un enum `TransferStatus::allowedTransitions()` ; toute autre est rejetée.
- Chaque transition : ligne `transfer_events` (horodatage, acteur, source : api/webhook/polling/admin, payload).
- Identifiants de traçabilité : `id` (transaction_id), `correlation_id`, `external_reference` (référence
  Cashop envoyée au provider), `provider_reference`, `provider_request_id`.

## 6. Ledger

Voir [DATABASE.md §Ledger](DATABASE.md#ledger). Résumé : partie double, écritures immuables, Σdébits = Σcrédits
par `ledger_transaction`, soldes wallet = projection, réconciliation quotidienne.

## 7. Mobile (Flutter)

```
mobile/lib/
├── core/        # config, thème, router (go_router), client Dio, stockage sécurisé, erreurs
├── shared/      # widgets design system, formatage monétaire, validateurs
└── features/<feature>/{data,domain,presentation}
     auth · onboarding · home · wallet · send · receive · beneficiaries · quote
     transfer (confirmation/auth/processing/success/failed/details) · history
     notifications · profile · kyc · security · settings
```

- État : Riverpod. Modèles : freezed + json_serializable.
- Intercepteurs Dio : `Authorization`, `X-Correlation-Id`, `Idempotency-Key` (générée une fois par
  intention de paiement et conservée jusqu'à réponse finale), rafraîchissement de session.
- Sécurité : `flutter_secure_storage`, `local_auth` (biométrie qui déverrouille une clé liée à l'appareil,
  utilisée pour signer un challenge serveur), certificate pinning, détection root/jailbreak,
  `FLAG_SECURE` / masquage en arrière-plan.

## 8. Web (deux applications Next.js)

| | `web/` — clients | `admin/` — back-office |
|---|---|---|
| Public | Utilisateurs Cashop | Employés (support, conformité, finance, admin) |
| Auth | Jeton Sanctum gardé par le BFF (cookie httpOnly), MFA proposée | Jeton `staff` gardé par le BFF, MFA **obligatoire**, restriction IP/VPN |
| Déploiement | Domaine public | Domaine distinct, non indexé, WAF strict |
| API | `/api/v1` | `/api/admin/v1` (RBAC par permission) |

Structure commune : App Router, TypeScript strict, Tailwind, `src/lib/api` (client typé généré depuis
`docs/openapi.yaml`), Server Components pour les données sensibles, aucune clé côté navigateur.

Écrans admin : Dashboard, Transactions (timeline), Users, KYC, Wallets, Providers, Provider Health, Fees, FX,
Refunds, Disputes, Risk, Compliance, Reports, Audit Logs, Settings — tables avec filtres, recherche,
pagination serveur, graphiques.

## 9. Observabilité

- Logs JSON (Monolog) avec `correlation_id`, `transfer_id`, `provider`, `user_id` ; données sensibles masquées.
- Métriques (Prometheus) : latence et taux de succès par provider/opération, transferts par statut,
  âge des transferts `UNKNOWN`, profondeur des files, écarts de réconciliation.
- Traces OpenTelemetry (API → job → appel provider).
- Suivi d'erreurs : Sentry. Health checks : `/health/live`, `/health/ready` (DB, Redis, files).

## 10. Arborescence du repository

```
cashop/
├── backend/                 # Laravel 11+ (phase 2)
├── web/                     # Next.js — application clients (phase 11+)
├── admin/                   # Next.js — back-office (phase 10)
├── mobile/                  # Flutter (phase 11)
├── infra/
│   ├── docker/php/          # Dockerfile PHP-FPM 8.3, php.ini
│   ├── docker/node/         # Dockerfile commun Next.js
│   ├── nginx/               # reverse proxy API
│   └── postgres/            # scripts d'initialisation
├── docs/                    # cette documentation + openapi.yaml + adr/
├── docker-compose.yml
├── .env.example
└── README.md
```
