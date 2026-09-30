# Sécurité Cashop

Référentiels visés : OWASP ASVS niveau 2 (niveau 3 pour les flux de paiement), OWASP MASVS pour le mobile.
PCI DSS : **à cadrer** — l'objectif est de ne jamais stocker de PAN (tokenisation / saisie chez le provider),
ce qui réduit le périmètre. Périmètre exact : **NOT CONFIRMED — dépend des programmes Visa retenus**.

## 1. Authentification

| Mécanisme | Mise en œuvre |
|---|---|
| Mot de passe | Argon2id, politique de longueur (≥ 10), vérification contre les fuites connues (k-anonymity), verrouillage progressif |
| OTP | 6 chiffres, haché en base, 5 min, 5 essais max, limite d'envoi par destinataire et par IP (Redis) |
| MFA | TOTP (RFC 6238) ; obligatoire pour l'admin, proposée aux clients, imposée par le moteur de risque (step-up) |
| PIN de transaction | 6 chiffres, Argon2id, distinct du mot de passe, verrouillage après N échecs |
| Biométrie (mobile) | `local_auth` déverrouille une clé privée liée à l'appareil (Secure Enclave / Android Keystore) ; le serveur envoie un challenge, l'app le signe, le serveur vérifie avec la clé publique enregistrée (`user_devices.public_key`). La biométrie ne quitte jamais l'appareil. |
| Sessions | Mobile : jetons Sanctum à portée limitée (abilities) et révocables par appareil. Web/admin : cookies httpOnly, `Secure`, `SameSite=Lax`, protection CSRF Sanctum. Admin : session courte, ré-authentification pour les actions sensibles. |

**Confirmation d'un transfert :** quote valide + PIN **ou** signature biométrique + (step-up OTP si le moteur
de risque l'exige). Le jeton de preuve est à usage unique et lié à l'identifiant de quote.

## 2. Autorisation (RBAC)

| Rôle | Périmètre |
|---|---|
| `customer` | Ses propres ressources uniquement (policies Laravel sur chaque modèle) |
| `support` | Lecture utilisateurs/transactions, données personnelles masquées |
| `compliance` | KYC, risque, dossiers de conformité |
| `finance` | Ledger, réconciliation, remboursements (validation) |
| `admin` | Configuration providers, frais, FX, paramètres |
| `super_admin` | Gestion des rôles ; compte « bris de glace » journalisé |

Double validation (4-eyes) : remboursements, ajustements de ledger, changement de frais/FX, décisions KYC de
niveau élevé, activation d'un provider en production.

## 3. Protection des données

- TLS 1.2+ partout, HSTS en production.
- Chiffrement applicatif des données personnelles (casts `encrypted`, clé `APP_KEY` + rotation via
  `APP_PREVIOUS_KEYS`) ; colonnes `*_hash` (HMAC) pour la recherche.
- Documents KYC : stockage objet chiffré, bucket privé, URL signées de courte durée, accès journalisé.
- Journaux : masquage automatique (PAN, PIN, OTP, mots de passe, jetons, secrets, numéros de document).
- Minimisation et durées de conservation : **NOT CONFIRMED — à fixer selon la réglementation du pays d'agrément**.

## 4. Secrets et certificats

- Aucun secret dans Git : `.env.example` ne contient que des noms de variables.
- Production : gestionnaire de secrets (HashiCorp Vault ou KMS du cloud). Les certificats mTLS et clés MLE
  sont montés en fichiers en lecture seule dans le conteneur API uniquement.
- `provider_country_configs.credentials_ref` ne contient qu'une **référence** vers le secret.
- Rotation planifiée et documentée ; détection de secrets en CI (gitleaks).

## 5. Sécurité de l'API

- Validation stricte (FormRequest), rejet des champs inconnus sur les routes financières.
- Rate limiting Redis : global, par utilisateur, et renforcé sur auth/OTP/transferts.
- `Idempotency-Key` obligatoire sur toutes les opérations financières (voir [DATABASE.md](DATABASE.md#idempotency_keys)).
- En-têtes : CSP, `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy`, HSTS.
- CORS limité aux domaines `web` et `admin`.
- Erreurs normalisées sans fuite d'information interne.
- Protection contre l'énumération (réponses identiques à l'inscription et à la réinitialisation).

## 6. Webhooks entrants

Pipeline appliqué à chaque requête `POST /api/v1/webhooks/{provider}` :

1. **Enregistrement** immédiat du payload brut (`webhooks`), génération d'un event ID Cashop.
2. **Vérification de la signature** selon le provider.
   - Mécanisme documenté par le provider : **NOT CONFIRMED pour les 5 providers** (voir [PROVIDERS.md](PROVIDERS.md)).
   - En attendant : jeton secret par provider dans l'URL de callback, liste blanche d'IP si le provider la
     publie, TLS obligatoire.
3. **Horodatage** : rejet si hors fenêtre (ex. ± 5 min) quand le provider fournit un horodatage.
4. **Anti-rejeu** : unicité (`provider`, `provider_event_id`) et (`provider`, `payload_sha256`).
5. **Réponse 2xx rapide**, traitement asynchrone (`ProcessWebhookJob`).
6. **Pas de confiance aveugle** : le statut est relu via `getTransactionStatus()` avant toute écriture
   comptable ; traitement idempotent.
7. **Journalisation** et mise à jour de la transaction via la machine à états.

Les webhooks des Mock providers sont signés en HMAC-SHA256 (`WEBHOOK_SIGNING_SECRET`) avec horodatage.

## 7. Mobile

Stockage sécurisé (Keychain/Keystore), certificate pinning, détection root/jailbreak/debug (signal de risque,
pas un blocage seul), masquage des écrans sensibles, pas de données sensibles dans les logs ou les captures,
obfuscation des builds release.

## 8. Journal d'audit

`audit_logs` en ajout seul, chaîné par hachage (`previous_hash`), couvrant : connexions, changements de
sécurité, actions admin, décisions KYC/risque, remboursements, modifications de configuration.

## 9. Détection des prises de contrôle de compte

Nouvel appareil, changement d'IP/pays, changement récent de téléphone/e-mail/PIN suivi d'un transfert,
nouveau bénéficiaire + montant élevé → événements de risque → step-up, blocage temporaire ou revue.

## 10. Chaîne logicielle

Dépendances verrouillées (`composer.lock`, `package-lock.json`, `pubspec.lock`), audit automatique en CI,
images Docker minimales et non-root, SAST, revue de code obligatoire, branches protégées.

## Signaler une vulnérabilité

Contact sécurité : **à définir** (adresse dédiée + `security.txt`).
