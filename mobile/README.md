# Cashop — application mobile (Flutter)

Flutter 3.47 / Dart 3.13 · Riverpod 3 · go_router 18 · Dio · flutter_secure_storage · local_auth.

```bash
flutter pub get
flutter analyze && flutter test
# Émulateur Android (10.0.2.2 = machine hôte) :
flutter run --dart-define=CASHOP_API_URL=http://10.0.2.2:8000/api/v1
```

Compte de démonstration (backend local seedé) : `client@cashop.test` / `Cashop-Demo-2026!` ;
le code OTP s'affiche dans `backend/storage/logs/laravel.log`.

- **API réelle** : connexion, inscription, code OTP, profil, PIN de transaction (`lib/features/auth`).
- **Démonstration** (`lib/features/home/data/demo_data.dart`, badge à l'écran) : soldes, offres,
  transferts, notifications, KYC — remplacés par l'API au fil des phases suivantes.
- Jeton stocké dans le Keychain / Keystore ; PIN et biométrie (`local_auth`) pour confirmer un envoi.
- Police Inter embarquée (licence OFL : `assets/fonts/Inter-LICENSE.txt`).
