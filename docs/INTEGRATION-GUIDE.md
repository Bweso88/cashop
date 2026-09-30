# Guide d'intégration d'un provider réel (phase 14)

Un provider ne passe de `mock` à `sandbox`, puis à `production`, qu'après avoir franchi **toutes** les étapes.
Chaque étape est cochée dans une fiche par provider (`docs/providers/<code>.md`, créée en phase 14).

| # | Étape | Livrable |
|---|---|---|
| 1 | Lire la documentation officielle | Liens et versions consultées |
| 2 | Modèle d'authentification | OAuth2 / Basic / mTLS / MLE / signature — confirmé |
| 3 | Endpoints | Liste exacte (méthode, chemin, schéma) |
| 4 | Environnements | URL sandbox, certification, production |
| 5 | Identifiants | Liste et procédure d'obtention |
| 6 | Certificats | Génération CSR, installation, rotation |
| 7 | Webhooks | Format, signature, retries, IP |
| 8 | Limites | Plafonds, débit (rate limits) |
| 9 | Pays | Couverture confirmée |
| 10 | Devises | Couverture confirmée |
| 11 | Modes de réception | Couverture confirmée |
| 12 | Certification | Exigences et cas de test imposés |
| 13 | Implémenter l'adapter | Client, Authenticator, Mapper, Provider, WebhookHandler |
| 14 | Tester en sandbox | Rapport de tests |
| 15 | Tests d'intégration | Suite automatisée (désactivable sans identifiants) |
| 16 | Documenter | `PROVIDERS.md` mis à jour : plus aucun NOT CONFIRMED sur les capacités utilisées |
| 17 | Préparer la certification | Dossier transmis au provider |
| 18 | Préparer la production | Revue sécurité, runbook, activation pays par pays, 4-eyes |

## Règles

- Tant qu'une information manque : **NOT CONFIRMED — PROVIDER DOCUMENTATION REQUIRED**, et le Mock reste actif.
- Mettre à jour `provider_capabilities.confirmation_status` et `source_reference` à chaque confirmation.
- Les tests unitaires de l'adapter utilisent des réponses enregistrées **issues de la sandbox réelle**, jamais
  des réponses imaginées.
- Aucune activation en production sans double validation dans l'admin.

## Squelette d'un adapter

```
app/Providers/Payment/<Nom>/
├── <Nom>Authenticator.php   # obtention/cache des jetons, certificats
├── <Nom>Client.php          # HTTP (timeouts, mTLS, retries sûrs), journalisation masquée
├── <Nom>Mapper.php          # DTO Cashop ⇄ payload provider
├── <Nom>Provider.php        # implémente PaymentProviderInterface
└── <Nom>WebhookHandler.php  # signature, horodatage, parsing → WebhookEvent
```
