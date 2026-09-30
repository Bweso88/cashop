# ADR 0001 — Monolithe modulaire Laravel

- Statut : **Acceptée**
- Date : 2026-09-30

## Contexte
Cashop manipule de l'argent : la cohérence entre transferts, ledger et soldes est critique. L'équipe
démarre petite et maîtrise PHP.

## Décision
Un seul backend Laravel, découpé en modules (`app/Domain/*`) aux frontières explicites, vérifiées
automatiquement en CI. Une seule base PostgreSQL.

## Conséquences
- (+) Transactions ACID entre transfert et écritures comptables, sans saga distribuée.
- (+) Déploiement, observabilité et tests simples.
- (−) Montée en charge verticale d'abord ; les workers Horizon (une file par provider) absorbent la charge I/O.
- Un module pourra être extrait (ex. Webhooks) si nécessaire, grâce aux frontières imposées.
