# Providers de paiement

> **Règle :** aucune URL, aucun endpoint, paramètre, tarif, pays ou règle n'est codé en dur sans
> documentation officielle. Toute information absente est marquée
> **NOT CONFIRMED — PROVIDER DOCUMENTATION REQUIRED** et remplacée par un `Mock<Provider>`.
>
> Tous les providers sont en mode `mock` jusqu'à la phase 14 (voir [INTEGRATION-GUIDE.md](INTEGRATION-GUIDE.md)).

## Niveaux de confiance utilisés

| Niveau | Signification |
|---|---|
| **OFFICIEL** | Vu sur une page de la documentation officielle du provider (lien fourni). À relire en phase 14. |
| **PUBLIC-SECONDAIRE** | Rapporté par des sources publiques non officielles (SDK, articles). **Ne pas coder avant confirmation.** |
| **NOT CONFIRMED** | Information inconnue : à obtenir auprès du provider. |

Recherche effectuée le 2026-09-30. Les portails officiels n'ont pas pu être consultés directement depuis
l'environnement de développement ; les points ci-dessous viennent de résultats de recherche et doivent être
**reconfirmés sur les portails** avant toute implémentation.

## Matrice des capacités (état actuel)

| Capacité | Visa Direct | MoneyGram | Western Union | MTN MoMo | Airtel Money |
|---|---|---|---|---|---|
| Quote | NOT CONFIRMED | OFFICIEL | NOT CONFIRMED¹ | N/A (pas de quote documentée) — NOT CONFIRMED | NOT CONFIRMED |
| Create / Update | OFFICIEL (push funds) | OFFICIEL (update) | NOT CONFIRMED¹ | PUBLIC-SECONDAIRE | PUBLIC-SECONDAIRE |
| Commit | N/A | OFFICIEL | NOT CONFIRMED | N/A | N/A |
| Status | NOT CONFIRMED | NOT CONFIRMED | NOT CONFIRMED | PUBLIC-SECONDAIRE | PUBLIC-SECONDAIRE |
| Cancel | NOT CONFIRMED | NOT CONFIRMED | NOT CONFIRMED | NOT CONFIRMED | NOT CONFIRMED |
| Refund | NOT CONFIRMED | NOT CONFIRMED | NOT CONFIRMED | NOT CONFIRMED | NOT CONFIRMED |
| Webhooks / callbacks | NOT CONFIRMED | NOT CONFIRMED | NOT CONFIRMED | PUBLIC-SECONDAIRE (callbacks) | PUBLIC-SECONDAIRE (callbacks) |
| Signature des webhooks | NOT CONFIRMED | NOT CONFIRMED | NOT CONFIRMED | NOT CONFIRMED | NOT CONFIRMED |
| Collection (encaissement) | NOT CONFIRMED (AFT) | N/A | N/A | PUBLIC-SECONDAIRE | PUBLIC-SECONDAIRE |
| Pays / devises / plafonds / tarifs | NOT CONFIRMED | NOT CONFIRMED | NOT CONFIRMED | NOT CONFIRMED | NOT CONFIRMED |

¹ Western Union décrit une « Partner Money Transfer API » (estimation de frais, validation, création de
transaction) mais le contrat technique n'est fourni qu'après onboarding partenaire.

Cette matrice est reproduite en base (`provider_capabilities.confirmation_status`) et affichée dans l'admin.

---

## Visa Direct

| Élément | Information | Niveau |
|---|---|---|
| Portail | https://developer.visa.com/capabilities/visa_direct | OFFICIEL |
| Authentification | Two-Way SSL (mTLS) avec certificat X.509 émis par Visa + identifiant/mot de passe | OFFICIEL ([doc](https://developer.visa.com/capabilities/visa_direct/docs-authentication)) |
| Message Level Encryption | Requis pour les API Visa Direct en certification et production ; JWE avec Key ID et clés RSA | OFFICIEL ([doc](https://developer.visa.com/capabilities/visa_direct/docs-authentication)) |
| Push funds (OCT) | Ressource `pushfundstransactions` mentionnée | PUBLIC-SECONDAIRE |
| Champs requis, codes réponse, statut asynchrone | — | NOT CONFIRMED |
| Webhooks | — | NOT CONFIRMED |
| Onboarding : acquéreur sponsor, BIN, Business Application IDs, corridors | — | NOT CONFIRMED |

Classes prévues : `VisaDirectClient` (mTLS + MLE), `VisaDirectProvider`, `VisaDirectWebhookHandler`,
`MockVisaProvider`. Les certificats et clés MLE sont lus depuis le gestionnaire de secrets, jamais exposés.

**À obtenir auprès de Visa :** acquéreur/sponsor et BIN d'origination ; programme Visa Direct applicable
(cartes, comptes, wallets) ; Business Application IDs autorisés ; corridors et devises ; API d'éligibilité
des cartes ; contrat exact des endpoints et codes de réponse ; traitement asynchrone et statut ;
annulation/retour de fonds ; webhooks ; MLE (clés, rotation) ; certification ; règlement et rapports ;
plafonds ; obligations AML partagées.

## MoneyGram

| Élément | Information | Niveau |
|---|---|---|
| Portail | https://developer.moneygram.com | OFFICIEL |
| Authentification | OAuth 2.0 (jeton d'accès) ; environnements sandbox et production distincts | OFFICIEL ([doc](https://developer.moneygram.com/moneygram-developer/docs/transfer-api)) |
| Flux | Quote → Update → Commit | OFFICIEL ([doc](https://developer.moneygram.com/moneygram-developer/docs/update-a-transaction)) |
| Quote | `POST /transfer/v1/transactions/quote` | PUBLIC-SECONDAIRE (à relire sur le portail) |
| Commit | `PUT /transfer/v1/transactions/{transactionId}/commit`, possible quand l'update renvoie `readyForCommit: true` | PUBLIC-SECONDAIRE (à relire sur le portail) |
| Autres API | Payout API, Business Disbursement API | OFFICIEL ([payout](https://developer.moneygram.com/moneygram-developer/docs/payout-api-overview), [disbursement](https://developer.moneygram.com/moneygram-developer/docs/disbursement)) |
| URL de base, endpoint OAuth, schémas, codes de service | — | NOT CONFIRMED |
| Status, Refund, Cancel, Webhooks | — | NOT CONFIRMED |

Classes prévues : `MoneyGramAuthenticator` (OAuth2, cache du jeton dans Redis), `MoneyGramClient`,
`MoneyGramProvider`, `MoneyGramWebhookHandler`, `MockMoneyGramProvider`.

**À obtenir auprès de MoneyGram :** contrat partenaire et identifiants (dont identifiant partenaire) ;
URL et endpoint OAuth par environnement ; champs obligatoires par corridor (données de référence) ; options
de service (cash, compte, wallet) ; statut, annulation, remboursement ; webhooks et signature ; préfinancement
et règlement ; plafonds ; certification.

## Western Union

| Élément | Information | Niveau |
|---|---|---|
| Portail | https://developer.westernunion.com | OFFICIEL |
| Offre | « Partner Money Transfer API » : estimation de frais, validation, création de transaction ; paiement en agence ou sur compte | OFFICIEL ([doc](https://developer.westernunion.com/api-money-transfer.html)) |
| Accès | Candidature, contrat, contrôles de conformité, puis identifiants de production | PUBLIC-SECONDAIRE |
| Endpoints, authentification, certificats, FX, statut, annulation, règlement | — | NOT CONFIRMED |

Classes prévues : `WesternUnionClient`, `WesternUnionProvider`, `WesternUnionWebhookHandler`,
`MockWesternUnionProvider`. **Tant que le contrat n'est pas signé : Mock uniquement.**

**À obtenir auprès de Western Union :** éligibilité de Cashop au programme partenaire ; documentation
technique ; modèle d'authentification et certificats (mTLS) ; verrouillage du taux FX et durée de validité ;
création, statut, annulation ; gestion du MTCN ; webhooks ; règlement ; corridors ; plafonds ; certification.

## MTN Mobile Money

| Élément | Information | Niveau |
|---|---|---|
| Portail | https://momodeveloper.mtn.com | PUBLIC-SECONDAIRE |
| Produits | Collections, Disbursements, Remittances | PUBLIC-SECONDAIRE |
| Sandbox | `https://sandbox.momodeveloper.mtn.com`, création d'un API user en libre-service | PUBLIC-SECONDAIRE |
| Production | Onboarding **par pays** (filiale MTN locale) ; URL, identifiants et environnements par pays | NOT CONFIRMED |
| Signature des callbacks | — | NOT CONFIRMED |
| Pays, devises, plafonds, frais | — | NOT CONFIRMED |

Classes prévues : `MtnMoMoClient` (un jeu d'identifiants par pays et par produit), `MtnMoMoProvider`,
`MockMtnProvider`. Callbacks traités comme un **signal** : le statut est toujours relu via l'API.

**À obtenir auprès de MTN (pour chaque pays) :** disponibilité des produits ; processus et documents
d'onboarding ; URL de production et environnement cible ; identifiants par produit ; format et
authentification des callbacks (liste blanche d'IP ?) ; vérification du titulaire de compte ; plafonds et
frais ; devises ; conditions pour le transfrontalier (Remittances) ; règlement et relevés.

## Airtel Money

| Élément | Information | Niveau |
|---|---|---|
| Portail | https://developers.airtel.africa | PUBLIC-SECONDAIRE |
| Authentification | OAuth 2.0 client credentials | PUBLIC-SECONDAIRE |
| Environnements | `openapiuat.airtel.africa` (recette), `openapi.airtel.africa` (production) | PUBLIC-SECONDAIRE |
| Capacités | Collections, Disbursements | PUBLIC-SECONDAIRE |
| Différences par pays (versions, en-têtes, chiffrement du PIN, signature) | — | NOT CONFIRMED |
| Callbacks et leur authentification | — | NOT CONFIRMED |

Classes prévues : `AirtelMoneyClient` (configuration **par pays**), `AirtelMoneyProvider`, `MockAirtelProvider`.

**À obtenir auprès d'Airtel (pour chaque pays) :** activation du pays ; version d'API ; chiffrement du PIN
et/ou signature des messages ; liste blanche d'IP ; callbacks ; plafonds, frais, devises ; KYC exigé pour
la production ; règlement.

## Ajouter un provider (Orange Money, Wave, banques…)

1. Créer `app/Providers/Payment/<Nom>/` avec Client, Authenticator, Mapper, Provider, WebhookHandler.
2. Créer `Mock<Nom>Provider`.
3. Ajouter les lignes `providers`, `provider_capabilities` (avec `confirmation_status`), `provider_country_configs`.
4. Aucune modification de l'orchestrateur, des controllers ou des apps clientes.

## Sources

- [Visa Direct — Authentication and Encryption](https://developer.visa.com/capabilities/visa_direct/docs-authentication)
- [MoneyGram — Transfer API](https://developer.moneygram.com/moneygram-developer/docs/transfer-api)
- [MoneyGram — Update a transaction](https://developer.moneygram.com/moneygram-developer/docs/update-a-transaction)
- [MoneyGram — Payout API](https://developer.moneygram.com/moneygram-developer/docs/payout-api-overview)
- [MoneyGram — Business Disbursement API](https://developer.moneygram.com/moneygram-developer/docs/disbursement)
- [Western Union — Partner Money Transfer API](https://developer.westernunion.com/api-money-transfer.html)
- MTN MoMo : sources secondaires (SDK publics, p. ex. [lepresk/momo-api](https://github.com/lepresk/momo-api)) — à confirmer sur momodeveloper.mtn.com
- Airtel Money : sources secondaires ([communiqué Airtel Africa](https://www.airtel.africa/assets/pdf/press-release/Airtel-Africa-Developer-Portal_ENGLISH.pdf)) — à confirmer sur developers.airtel.africa
