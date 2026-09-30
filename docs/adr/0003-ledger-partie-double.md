# ADR 0003 — Ledger en partie double comme source de vérité

- Statut : **Acceptée**
- Date : 2026-09-30

## Décision
Tous les mouvements de fonds sont des `ledger_transactions` équilibrées (Σdébits = Σcrédits), immuables.
Les soldes des wallets (`wallet_balances`) sont une projection mise à jour dans la même transaction SQL
et réconciliée chaque nuit avec le ledger.

## Conséquences
- Toute correction passe par une contre-écriture, jamais par un UPDATE/DELETE.
- Montants en `BIGINT` (unités mineures ISO 4217) ; aucun FLOAT.
