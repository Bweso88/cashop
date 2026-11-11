# Visa Direct — paiement d'un envoi par carte

**Usage retenu (2026-11-11)** : le client paie un envoi avec sa carte Visa, comme alternative à MTN
MoMo et Airtel Money. Il n'y a pas de « recharge » : Cashop ne tient aucun solde ([ADR 0004](../adr/0004-sans-portefeuille-client.md)),
l'argent de la carte finance directement le transfert, et un transfert refusé est remboursé sur la carte.

## Ce qui est en place (simulation uniquement)

- `SimulatedCardConnector` (contrat `WalletConnector` : débit, statut, remboursement).
- Parcours : choix « Carte Visa » → devis → PIN Cashop → **page de paiement du prestataire** (simulée :
  `/paiement-carte/{référence}`, trois issues : accepté, refusé par la banque, échec 3-D Secure) → suivi.
- **Aucune donnée de carte n'entre dans Cashop** : l'API refuse explicitement `card_number`, `pan`, `cvv`,
  `cvc`, `expiry` (422). Cashop ne connaît que le résultat et les 4 derniers chiffres renvoyés par le
  prestataire.
- Le remboursement automatique (transfert refusé par le réseau) se fait sur la carte d'origine.

## NOT CONFIRMED — PROVIDER DOCUMENTATION REQUIRED

- Le produit exact pour débiter une carte : Visa Direct sert surtout à **envoyer** vers une carte (push) ;
  le débit d'une carte (« Account Funding Transaction ») passe en général par un **acquéreur** ou une
  passerelle de paiement agréée. À confirmer avec Visa : qui est l'acquéreur, quelle page de paiement
  hébergée, quel 3-D Secure.
- API (authentification, création de paiement, statut, remboursement, notifications et leur signature).
- Frais de carte, devises acceptées, plafonds, disponibilité pour des cartes émises hors CEMAC.

## Exigences avant la production

- **PCI DSS** : garder le périmètre minimal en utilisant exclusivement la page hébergée / la tokenisation du
  prestataire (jamais de champ de carte dans Cashop, jamais de PAN ni de CVV en base ou dans les journaux).
- 3-D Secure obligatoire, gestion des rétrofacturations (chargebacks) et des litiges.
- Réglementation : accepter des paiements par carte au Congo-Brazzaville passe par une banque acquéreuse
  locale ou régionale ; obligations BEAC / COBAC à faire confirmer par un juriste.
