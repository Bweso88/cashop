# Cycle de vie d'un transfert

Source de vérité : `backend/app/Domain/Payments/Enums/TransferStatus.php` (transitions autorisées) et
`TransferOrchestrator` (qui les déclenche). Chaque transition est enregistrée dans `transfer_events`
(append-only) ; les statuts sensibles sont aussi écrits dans le journal d'audit chaîné.

## Statuts

| Statut | Libellé client | Fonds du client |
|---|---|---|
| `INITIATED` | Envoi initié | non débités |
| `QUOTE_PENDING` | Devis en cours | non débités |
| `QUOTE_CONFIRMED` | Devis confirmé | non débités |
| `PAYMENT_PENDING` | En attente de validation Mobile Money | débit demandé, non confirmé |
| `PAYMENT_CONFIRMED` | Paiement Mobile Money confirmé | **débités, en transit** |
| `TRANSFER_PENDING` | Transfert en cours auprès du réseau | en transit |
| `TRANSFER_CONFIRMED` | Transfert confirmé par le réseau | en transit |
| `TRANSFER_AVAILABLE_FOR_PICKUP` | Disponible au retrait | remis au réseau |
| `PAYOUT_PENDING` | Versement en cours | en transit |
| `PAYOUT_CONFIRMED` | Versé au bénéficiaire | remis au réseau |
| `CANCEL_PENDING` | Annulation demandée | en transit |
| `REFUND_PENDING` | Remboursement en cours | en cours de restitution |
| `REFUNDED` | Remboursé | restitués |
| `FAILED` | Échec | jamais débités |
| `CANCELLED` | Annulé | jamais débités |
| `REQUIRES_RECONCILIATION` | Vérification en cours | **inconnu — à établir** |

**Règle d'affichage** : un envoi n'est présenté comme réussi que dans `TRANSFER_AVAILABLE_FOR_PICKUP` ou
`PAYOUT_CONFIRMED` (`TransferStatus::isDelivered()`). La confirmation du paiement Mobile Money ne suffit jamais.

## Diagramme

```
INITIATED → QUOTE_CONFIRMED → PAYMENT_PENDING ─┬─ refus opérateur ─────────────→ FAILED
                                               ├─ délai / réponse inconnue ────→ REQUIRES_RECONCILIATION
                                               └─ confirmé → PAYMENT_CONFIRMED
PAYMENT_CONFIRMED ─┬─ réseau : refus définitif ─→ REFUND_PENDING → REFUNDED
                   ├─ réseau : sans réponse ────→ REQUIRES_RECONCILIATION
                   ├─ réseau : en cours ────────→ TRANSFER_PENDING
                   ├─ réseau : confirmé ────────→ TRANSFER_CONFIRMED → PAYOUT_CONFIRMED (mobile money)
                   └─ réseau : retrait agence ──→ TRANSFER_AVAILABLE_FOR_PICKUP
REQUIRES_RECONCILIATION ─ relecture du statut chez le prestataire, par la référence Cashop :
                   ├─ transfert trouvé ─────────→ statut correspondant (sans nouvelle soumission)
                   ├─ transfert inexistant ─────→ REFUND_PENDING → REFUNDED
                   └─ toujours inconnu ─────────→ reste en vérification (escalade après 24 h)
```

## Garanties

| Risque | Garantie | Test |
|---|---|---|
| Double débit (double clic, rejeu réseau) | `Idempotency-Key` + `(user_id, idempotency_key)` unique + un seul `COLLECTION` par transfert | `double_soumission_un_seul_transfert_un_seul_debit` |
| Devis réutilisé | devis marqué `USED` sous verrou | `un_devis_ne_sert_qu_une_fois` |
| Double transfert après un délai | `submitted_at` posé avant l'appel ; si déjà posé → rapprochement, jamais renvoi | `delai_a_la_creation_du_transfert_retrouve_par_rapprochement_sans_doublon` |
| Paiement confirmé, transfert refusé | remboursement intégral automatique (frais compris), reçu de remboursement | `scenario_critique_paiement_confirme_puis_transfert_refuse` |
| Réponse opérateur ambiguë | jamais traitée comme un succès | `reponse_operateur_inconnue_jamais_traitee_comme_un_succes` |
| Webhook rejoué ou falsifié | signature HMAC + horodatage ; doublon ignoré ; un rejet ne bloque pas l'original | `WebhookTest` |
| Retour en arrière d'un statut | `applyTransferResult` refuse toute régression | `TransferStatusTest` |
| Bascule simulation → réel | l'environnement est figé sur le transfert ; le registre refuse tout autre connecteur | `isolation_des_environnements_aucune_bascule_automatique` |
| Intervention arbitraire d'un admin | aucune route admin ne modifie un statut ; seul le rapprochement, fondé sur la réponse du prestataire, fait avancer | — |

## Avancement

- Requête client `POST /transfers/{id}/refresh` (bouton « Actualiser »).
- Webhook du prestataire (signal seulement : le statut est relu).
- Tâche planifiée `cashop:transfers:advance` chaque minute pour les transferts dont `next_status_poll_at` est échu.
