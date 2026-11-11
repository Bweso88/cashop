# Visa Pay (plateforme Pismo) — notes d'intégration

Source : page « Basic Authentication » de la documentation Visa Pay transmise par l'équipe le 2026-11-11.
Aucun code n'est écrit tant que l'adéquation au modèle Cashop n'est pas confirmée (voir plus bas).

## Confirmé (OFFICIEL, page fournie)

- Sandbox : clés d'accès créées dans le **Control Center** (Users & permissions → Access Keys → Create Key) :
  **Server Key** et **Server Secret**, à conserver hors de Git.
- Jeton : `POST https://api.bra01.sandbox.visapay.visa.com/passport/v2/s2s/access-token`,
  corps JSON `{"server_key": "…", "server_secret": "…"}`.
- Réponse : `server_key`, `tenant`, `program_ids`, `roles` (ex. `account-server`, `onboarding-server`),
  `token` (Bearer à mettre dans l'en-tête `Authorization` de chaque appel), `refresh`.
- Le jeton est un JWT de durée limitée (en général **10 minutes**, champ `exp`) ; un appel avec un jeton
  expiré renvoie Unauthorized : il faut alors en redemander un.
- Production : un **tenant Pismo** par client Visa Pay, puis
  - mTLS ;
  - URL de webhooks d'autorisation enregistrées chez Pismo, **signature à vérifier** avant traitement ;
  - identifiants OpenID pour l'accès Visa Pay ;
  - destinations de flux d'événements (gestion des délais d'autorisation, etc.) ;
  - SFTP optionnel pour les rapports de règlement.

## NOT CONFIRMED — PROVIDER DOCUMENTATION REQUIRED

- Les opérations métier (création de compte, enrôlement d'un portefeuille, paiement, statut) et leurs schémas.
- L'usage de `refresh` (endpoint et durée de validité).
- Le format et l'algorithme de signature des webhooks.
- La disponibilité au Congo-Brazzaville / zone CEMAC (« bra01 » désigne vraisemblablement une région
  d'hébergement, à confirmer).

## Point bloquant : adéquation au modèle Cashop

D'après la page, Pismo assure le **traitement émetteur** (issuer processing), l'enrôlement de portefeuilles
et le cycle de vie des identifiants Visa pour le client Visa Pay. Cela correspond à un acteur qui **émet un
moyen de paiement Visa adossé à un portefeuille**, donc qui détient des fonds clients.

Cashop ne détient pas de fonds clients ([ADR 0004](../adr/0004-sans-portefeuille-client.md)). Avant tout
développement, il faut confirmer avec Visa :
1. quel produit Visa est visé (Visa Pay, Visa Direct pour envoyer vers une carte, autre) ;
2. si Cashop peut l'utiliser comme fournisseur de technologie, sans être émetteur ni détenir de fonds ;
3. les exigences réglementaires associées (agrément d'émission de monnaie électronique dans la CEMAC).
