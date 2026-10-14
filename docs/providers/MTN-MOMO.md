# MTN Mobile Money — connecteur officiel (MoMo Open API)

Code : `backend/app/Domain/Providers/Connectors/MtnMoMo/`. Tests : `tests/Feature/Providers/MtnMoMoConnectorTest.php`.

Sources : pages de momodeveloper.mtn.com transmises par l'équipe le 2026-10-14 — « API User and API Key
Management », « Use Cases », « Sandbox Use Cases », « Callback », « Common Error Codes ».
Le Swagger (onglet API) n'a pas encore été fourni.

## Usage dans Cashop

| Besoin Cashop | Produit MTN | Opération |
|---|---|---|
| Débiter le client (il valide sur son téléphone : Cashop ne voit jamais son PIN) | Collection | Request to Pay |
| Rembourser le client si le transfert international échoue | Disbursement | Transfer |
| Vérifier qu'un numéro est actif (à venir) | Collection | Validate Account Holder |
| Surveiller le compte de remboursement (à venir) | Disbursement | Get Balance |

Non utilisés : Pre-Approval (prélèvement sans validation à chaque envoi — contraire au principe Cashop),
Remittance, opérations « avec consentement », Aggregator (`transferType`, seulement si le contrat le prévoit).

## Confirmé (OFFICIEL)

- Deux identifiants : **clé d'abonnement** par produit (`Ocp-Apim-Subscription-Key`) et **API User + API Key**
  par pays (sandbox : API de provisioning ; production : Partner / Account Portal).
- Jeton OAuth 2.0 client credentials (RFC 6749), API User / API Key en Basic ; jeton réutilisé jusqu'à expiration.
- En-têtes : `Authorization: Bearer`, `X-Reference-Id` (UUID v4), `X-Target-Environment`, `X-Callback-Url`.
- `X-Target-Environment` : `sandbox` en test, **`mtncongo`** pour le Congo.
- Sandbox : `https://sandbox.momodeveloper.mtn.com`, devise **EUR** uniquement.
- `POST /collection/v1_0/requesttopay` et `POST /disbursement/v1_0/transfer` : corps `amount` (chaîne),
  `currency`, `externalId`, `payer` / `payee` `{partyIdType: MSISDN, partyId}`, `payerMessage`, `payeeNote`.
- Réponse `202`, statut `PENDING` puis `SUCCESSFUL` ou `FAILED`. `GET /collection/v1_0/requesttopay/{id}`.
- `409 RESOURCE_ALREADY_EXIST` : référence déjà utilisée. `404` au GET : opération inexistante.
- Messages : 160 caractères maximum, pas d'apostrophe. Numéro avec indicatif pays.
- Le client a **5 minutes** pour valider (`COULD_NOT_PERFORM_TRANSACTION`).
- Disbursement : l'IP d'origine doit être déclarée à MTN (`403 FORBIDDEN IP`).
- Notification : POST (ou PUT), HTTPS, sur le domaine `providerCallbackHost`, **une seule fois, sans nouvel
  essai, sans signature décrite**. MTN recommande de relire le statut par GET.
- Certificat du serveur de notification : autorité de la liste « Approved Intermediate CA ».

## NOT CONFIRMED — PROVIDER DOCUMENTATION REQUIRED

| Point | Traitement actuel |
|---|---|
| Chemin de l'opération de jeton (CreateAccessToken) | variable `MTN_MOMO_TOKEN_PATH`, **sans valeur par défaut** |
| URL de base de production | variable `MTN_MOMO_BASE_URL` |
| `GET /disbursement/v1_0/transfer/{id}` | déduit par analogie avec Request to Pay |
| Champs de la réponse de statut (`status`, `reason`, `financialTransactionId`) | tout statut absent ou inattendu = `UNKNOWN`, jamais un succès |
| Format du corps d'erreur (champ `code`) | sans code reconnu, une erreur 500 est traitée comme un résultat inconnu |
| Contenu de la notification | seul `externalId` (= référence Cashop) est lu ; le statut est toujours relu par GET |
| Numéro sans « + », devise XAF pour `mtncongo` | à confirmer avec MTN Congo |

## Correspondance des réponses

| Réponse MTN | Cashop |
|---|---|
| 202 | demande en attente de validation par le client |
| 409 | jamais de seconde demande : relecture du statut |
| 400, 401, 403 ; 500 `PAYER_NOT_FOUND`, `PAYEE_NOT_FOUND`, `NOT_ALLOWED`, `NOT_ALLOWED_TARGET_ENVIRONMENT`, `INVALID_CURRENCY`, `INVALID_CALLBACK_URL_HOST` | refus définitif : aucun débit |
| 500 `INTERNAL_PROCESSING_ERROR`, autre 500, 503, coupure réseau | résultat inconnu : relecture, puis rapprochement |
| statut `FAILED` | échec avec motif traduit pour le client |

## Tester sur la sandbox

1. Sur momodeveloper.mtn.com : s'abonner à **Collection** et **Disbursement** (une clé d'abonnement chacun).
2. Avec **Sandbox User Provisioning** (pour chaque produit, avec sa clé) : créer un API User
   (`POST {baseURL}/apiuser`, en-tête `X-Reference-Id` = un UUID, corps `{"providerCallbackHost": "<domaine>"}`)
   puis son API Key (`POST {baseURL}/apiuser/{uuid}/apikey`) ; `{baseURL}` exact : voir le Swagger de provisioning.
3. Remplir `backend/.env` (jamais commité) : `MTN_MOMO_*`, dont `MTN_MOMO_TOKEN_PATH` lu dans le Swagger.
4. Lancer :
   ```bash
   php artisan cashop:mtn:sandbox-check                 # succès attendu
   php artisan cashop:mtn:sandbox-check +46733123451    # refus du client
   php artisan cashop:mtn:sandbox-check +46733123453    # reste en attente
   php artisan cashop:mtn:sandbox-check --disburse      # débit + remboursement
   ```
   Aucun secret n'est affiché. Montant en EUR de test : aucun fonds réel.

Numéros de test utiles (tout autre numéro = succès) :

| Numéro | Request to Pay | Transfer (remboursement) |
|---|---|---|
| 46733123450 | Failed | Failed |
| 46733123451 | Rejected | Rejected |
| 46733123452 | Expired | Expired |
| 46733123453 | Ongoing | Ongoing |
| 46733123454 | Delayed | Delayed |
| 46733123455 | NotFound | **NotEnoughFunds** |
| 46733123461 | InternalProcessingError | InvalidCurrency |
| 46733123462 | ServiceUnavailable | InternalProcessingError |

Un envoi complet en sandbox (débit MTN réel de test + transfert réseau) n'est pas encore possible : aucun
réseau (WU, MoneyGram, Ria) n'a de connecteur sandbox, et Cashop refuse de mélanger les environnements.

## Avant la production (MTN Congo)

- [ ] Contrat et onboarding avec **MTN Congo** ; identifiants sur l'Account Portal ; `X-Target-Environment=mtncongo`.
- [ ] Confirmer devise (XAF), format du numéro, URL de base, plafonds, frais, délais de reversement.
- [ ] **IP sortante fixe** déclarée pour Disbursement.
- [ ] Notifications : domaine déclaré, HTTPS avec certificat d'une autorité de la liste MTN (attention aux
      intermédiaires Let's Encrypt : seul E7 y figure) ; URL contenant `MTN_MOMO_CALLBACK_SECRET`.
- [ ] Alerte sur le solde du compte Disbursement (sinon remboursements impossibles).
- [ ] Validation juridique : Cashop reçoit les fonds sur son compte marchand avant de les remettre au réseau
      ou de les rembourser (voir ADR 0004).
- [ ] `CASHOP_PRODUCTION_ENABLED=true` seulement après ces points.
