# ADR 0002 — Deux applications Next.js séparées (web / admin)

- Statut : **Acceptée**
- Date : 2026-09-30

## Décision
`web/` (clients) et `admin/` (back-office) sont deux applications distinctes, déployées sur des domaines
différents.

## Raisons
- Surface d'attaque : le code et les routes admin ne sont jamais servis au public.
- Politiques différentes : MFA obligatoire, restriction IP/VPN et sessions courtes pour l'admin.
- Cycles de livraison indépendants.

## Conséquences
- Un peu de duplication (design system, client API). À mutualiser si besoin via un paquet partagé
  (`packages/ui`) — décision reportée tant que la duplication reste faible.
