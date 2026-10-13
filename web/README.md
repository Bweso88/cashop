# Cashop — application web (clients)

Next.js 16 (App Router, Cache Components), React 19, TypeScript, Tailwind CSS 4.

```bash
npm install
cp .env.example .env.local     # CASHOP_API_URL=http://localhost:8000/api/v1
npm run dev                    # http://localhost:3000
```

Compte de démonstration (backend local seedé) : `client@cashop.test` / `Cashop-Demo-2026!`.
Le code OTP s'affiche dans `backend/storage/logs/laravel.log`.

## Architecture

- **BFF** : le navigateur ne parle qu'à Next.js. Les Server Actions (`src/lib/auth-actions.ts`,
  `src/lib/security-actions.ts`) appellent l'API Laravel ; le jeton est gardé dans un cookie httpOnly
  et n'est jamais lisible par le JavaScript du navigateur.
- **Types** : `src/lib/api/schema.d.ts` est généré depuis `../docs/openapi.yaml` (`npm run gen:api`).
- **Données réelles** : connexion, inscription, OTP, profil, PIN, double authentification.
- **Données de démonstration** (`src/lib/demo-data.ts`, badge « Données de démonstration » à l'écran) :
  portefeuilles, transactions, offres, bénéficiaires, KYC. Elles seront remplacées par l'API au fil des
  phases 4 à 8. Aucun tarif ni taux affiché n'est réel.
