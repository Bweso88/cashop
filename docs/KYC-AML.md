# KYC et lutte contre le blanchiment (LCB-FT)

> **Cadre réglementaire.** Cashop ne peut exercer le transfert d'argent ou l'émission de monnaie électronique
> qu'en tant qu'établissement agréé ou en partenariat avec un établissement agréé dans chaque pays
> (par exemple BEAC/COBAC en zone CEMAC, BCEAO en zone UEMOA, autorité nationale ailleurs).
> Les seuils, pièces exigées et durées de conservation dépendent de ce cadre :
> **NOT CONFIRMED — à fournir par le responsable conformité**. Aucune valeur réglementaire n'est codée en dur.

## 1. Niveaux KYC (configurables)

| Niveau | Exigences (exemple de structure, à valider) | Plafonds |
|---|---|---|
| `LEVEL_0` | Compte créé, téléphone vérifié (OTP) | `limit_rules` — à définir |
| `LEVEL_1` | Pièce d'identité + selfie + contrôle du vivant | `limit_rules` — à définir |
| `LEVEL_2` | `LEVEL_1` + justificatif de domicile (+ source des fonds si requis) | `limit_rules` — à définir |

Les exigences sont stockées dans `kyc_levels.requirements`, les plafonds dans `limit_rules` (par niveau,
période, devise, pays). En développement, les seeders fournissent des valeurs **fictives clairement nommées**
(`DEV_ONLY_*`).

## 2. Statuts

```
PENDING ─► IN_REVIEW ─► VERIFIED ─► EXPIRED (document expiré ou revue périodique)
                    └─► REJECTED ─► (nouvelle soumission) PENDING
```

## 3. Parcours de vérification

1. L'utilisateur envoie son document (recto/verso) et son selfie depuis l'app.
2. `KycVendorInterface` (implémentation au choix — **fournisseur à sélectionner**, p. ex. Smile ID, Onfido,
   Sumsub ; `ManualKycVendor` par défaut) : lecture du document, contrôle du vivant, comparaison faciale.
3. Filtrage sanctions/PEP via `SanctionsScreeningInterface` (**fournisseur à sélectionner**).
4. Résultats dans `kyc_verifications` ; décision automatique possible seulement si toutes les vérifications
   sont `PASS` et si la configuration l'autorise, sinon `IN_REVIEW` (file de l'équipe conformité dans l'admin).

## 4. Moteur de risque

Évalué au moment de la quote (pré-contrôle) et de la confirmation (contrôle complet). Chaque règle
(`risk_rules`) a des paramètres, un poids et une action.

| Règle | Exemple de paramètres (à calibrer) | Action possible |
|---|---|---|
| Vélocité | N transferts / X minutes ; montant cumulé / 24 h | `REVIEW`, `STEP_UP_AUTH` |
| Seuil de montant | montant ≥ seuil par devise | `REVIEW` |
| Transaction dupliquée | même bénéficiaire + montant en < N minutes | `STEP_UP_AUTH` |
| Nouvel appareil / changement récent d'identifiants | fenêtre de N heures | `STEP_UP_AUTH`, `BLOCK` |
| Incohérence géographique | pays IP ≠ pays de résidence | `REVIEW` |
| Alerte sanctions | correspondance fournisseur | `BLOCK` + dossier `SANCTIONS` |
| Structuration | plusieurs montants juste sous un seuil | `REVIEW` |

Score agrégé 0–100 (`risk_scores`) ; actions `ALLOW`, `STEP_UP_AUTH`, `REVIEW` (transfert en attente),
`BLOCK`. Toute décision crée un `risk_event` ; `REVIEW`/`BLOCK` ouvrent un `compliance_case`.

## 5. Dossiers de conformité

Types : `AML_ALERT`, `SANCTIONS`, `KYC_REVIEW`, `FRAUD`, `SAR` (déclaration de soupçon).
La déclaration aux autorités (cellule de renseignement financier du pays) suit une procédure
**NOT CONFIRMED — à définir avec la conformité** ; l'outil fournit l'export du dossier.

## 6. Partage des responsabilités avec les providers

Chaque provider applique ses propres contrôles. La répartition (qui filtre quoi, quelles données transmettre)
est **NOT CONFIRMED** et doit être obtenue lors de l'onboarding de chaque provider.
