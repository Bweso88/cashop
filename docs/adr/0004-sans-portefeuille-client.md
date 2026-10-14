# ADR 0004 — Pas de portefeuille client : Cashop orchestre, il ne détient pas de fonds

- Statut : accepté
- Date : 2026-10-13
- Remplace : la partie « Wallet » de l'ARCHITECTURE initiale et de l'ADR 0003 (exemples de comptes `wallet:*`)

## Contexte

Le prompt maître du 2026-10 fixe le positionnement : au Congo-Brazzaville, Cashop est un **fournisseur de
technologie**, pas une banque ni un établissement de monnaie électronique. Cashop ne doit pas stocker de fonds
clients (« Ne construis pas de portefeuille Cashop stockant des fonds clients »). Le client paie par Mobile
Money (MTN MoMo, Airtel Money) et le transfert international est exécuté par un réseau agréé (Western Union,
MoneyGram, Ria ; Visa plus tard).

Détenir un solde client exigerait un agrément (monnaie électronique / établissement de paiement, cadre COBAC /
BEAC) que Cashop n'a pas — **vérification juridique indépendante requise**.

## Décision

1. Suppression du module `Wallet` (tables `wallets`, `wallet_balances`, `wallet_transactions`) avant tout
   déploiement : les migrations ont été réécrites, aucune migration de suppression n'est nécessaire.
2. Un envoi = **un débit Mobile Money unique, immédiatement affecté** à un transfert réseau. Il n'existe pas
   de « solde Cashop » consultable, rechargeable ou retirable.
3. Le ledger en partie double est conservé pour **tracer** les flux et les fonds en transit :

   | Événement | Débit | Crédit |
   |---|---|---|
   | Paiement Mobile Money confirmé | `collection_clearing:{portefeuille}:XAF` (actif) | `in_flight:XAF` (passif envers le client) |
   | Transfert livré / disponible | `in_flight:XAF` | `remittance_payable:{réseau}:XAF` + `fee_revenue:XAF` |
   | Remboursement effectué | `in_flight:XAF` | `collection_clearing:{portefeuille}:XAF` |

   Un test vérifie qu'aucune table `wallet*` n'existe (`SchemaTest::aucun_portefeuille_client_n_existe`).
4. Le paiement et le transfert ne sont pas atomiques : l'orchestrateur garantit **au plus un débit par
   transfert** (index unique partiel `payment_attempts_one_collection`), **au plus une soumission** au réseau
   (`submitted_at`), et un **remboursement intégral automatique** quand le réseau refuse définitivement ou
   n'a jamais créé le transfert. Toute réponse incertaine mène à `REQUIRES_RECONCILIATION`, jamais à un succès.

## Conséquences

- Les écrans web et mobile ne montrent plus de solde ; l'accueil présente les envois et leur statut.
- Les flux réels de règlement (préfinancement du réseau, compte de collecte chez l'opérateur, délais de
  reversement) dépendent des contrats partenaires : **NOT CONFIRMED — PROVIDER DOCUMENTATION REQUIRED**.
- Avec MTN MoMo (Request to Pay), le débit du client arrive sur le **compte marchand de Cashop** chez
  l'opérateur, et les remboursements partent du **compte Disbursement de Cashop**. Cashop détient donc
  brièvement des fonds en transit : ce schéma doit être validé par MTN Congo et un juriste.
- La qualification juridique de l'activité (intermédiaire technique, agent, sous-agent) doit être validée par
  un juriste au Congo (lois n° 29-2019 et n° 26-2020, réglementation CEMAC) avant la production.
