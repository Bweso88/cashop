# Cashop — backend (Laravel)

API REST de Cashop. Architecture : [../docs/ARCHITECTURE.md](../docs/ARCHITECTURE.md).

```
app/
├── Domain/<Module>/Enums   # États et types (source des contraintes CHECK)
├── Support/                # Outils transverses (Database\Check, Enums\HasValues)
└── Models/
database/
├── migrations/             # Schéma PostgreSQL (docs/DATABASE.md)
└── seeders/{Reference,Development}/
tests/
├── Unit/                   # Logique pure (machine à états…)
└── Feature/Database/       # Invariants garantis par PostgreSQL
```

Les tests s'exécutent sur PostgreSQL (`cashop_test`), jamais sur SQLite : les triggers et contraintes
font partie du comportement testé.
