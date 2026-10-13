import 'package:cashop/core/network/api_client.dart';
import 'package:cashop/core/storage/secure_store.dart';
import 'package:cashop/features/auth/data/auth_models.dart';
import 'package:cashop/features/auth/data/auth_repository.dart';
import 'package:cashop/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';

class FakeStore implements SecureStore {
  String? token;

  @override
  Future<String?> readToken() async => token;
  @override
  Future<void> writeToken(String t) async => token = t;
  @override
  Future<void> deleteToken() async => token = null;
  @override
  Future<String> deviceId() async => 'test-device';
  @override
  Future<bool> hasOnboarded() async => true;
  @override
  Future<void> markOnboarded() async {}
}

/// Remplace l'API : même contrat que le backend (mot de passe, puis code OTP).
class FakeAuthRepository implements AuthRepository {
  FakeAuthRepository(this.store);

  final FakeStore store;
  static const demoProfile = Profile(id: 'u1', email: 'client@cashop.test', firstName: 'Amina', lastName: 'Nkoulou', kycLevel: 'LEVEL_1', kycStatus: 'VERIFIED');

  @override
  Future<OtpChallenge> login(String login, String password) async {
    if (password != 'bon-mot-de-passe') throw ApiException(401, 'INVALID_CREDENTIALS', 'Identifiants incorrects.');
    return const OtpChallenge(challengeId: 'c1', channel: 'sms', destinationHint: '+237•••••••01');
  }

  @override
  Future<Profile> verifyOtp(String challengeId, String code) async {
    if (code != '123456') throw ApiException(401, 'INVALID_OTP', 'Code invalide ou expiré.');
    await store.writeToken('jeton');
    return demoProfile;
  }

  @override
  Future<Profile> profile() async => demoProfile;
  @override
  Future<bool> hasToken() async => store.token != null;
  @override
  Future<void> logout() async => store.token = null;

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

Future<void> enterCode(WidgetTester tester, String code) async {
  for (final d in code.split('')) {
    await tester.tap(find.byKey(ValueKey('pin-key-$d')));
  }
  await tester.pumpAndSettle();
}

void main() {
  setUpAll(() => initializeDateFormatting('fr_FR'));

  Future<void> launch(WidgetTester tester) async {
    tester.view.physicalSize = const Size(1170, 2532);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);
    final store = FakeStore();
    await tester.pumpWidget(ProviderScope(
      overrides: [
        secureStoreProvider.overrideWithValue(store),
        authRepositoryProvider.overrideWithValue(FakeAuthRepository(store)),
      ],
      child: const CashopApp(),
    ));
    await tester.pumpAndSettle();
  }

  testWidgets('connexion, code OTP puis accueil personnalisé', (tester) async {
    await launch(tester);
    expect(find.text('Bon retour'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('login-field')), 'client@cashop.test');
    await tester.enterText(find.byKey(const Key('password-field')), 'bon-mot-de-passe');
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();

    expect(find.text('Vérification en deux étapes'), findsOneWidget);
    expect(find.textContaining('+237•••••••01'), findsOneWidget);

    await enterCode(tester, '123456');

    expect(find.text('Bonjour Amina'), findsOneWidget);
    expect(find.text('Identité vérifiée · LEVEL_1'), findsOneWidget);
    expect(find.text('Transactions récentes'), findsOneWidget);
  });

  testWidgets('un mauvais mot de passe affiche l’erreur de l’API', (tester) async {
    await launch(tester);
    await tester.enterText(find.byKey(const Key('login-field')), 'client@cashop.test');
    await tester.enterText(find.byKey(const Key('password-field')), 'faux');
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();

    expect(find.text('Identifiants incorrects.'), findsOneWidget);
    expect(find.text('Bon retour'), findsOneWidget);
  });

  testWidgets('un mauvais code reste sur la vérification', (tester) async {
    await launch(tester);
    await tester.enterText(find.byKey(const Key('login-field')), 'client@cashop.test');
    await tester.enterText(find.byKey(const Key('password-field')), 'bon-mot-de-passe');
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();

    await enterCode(tester, '000000');

    expect(find.text('Code invalide ou expiré.'), findsOneWidget);
    expect(find.text('Vérification en deux étapes'), findsOneWidget);
  });
}
