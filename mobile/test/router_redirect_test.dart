import 'package:cashop/core/router/app_router.dart';
import 'package:cashop/features/auth/application/session_controller.dart';
import 'package:cashop/features/auth/data/auth_models.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  const profile = Profile(id: 'u1', email: 'a@cashop.test');
  const challenge = OtpChallenge(challengeId: 'c1', channel: 'sms');

  test('au démarrage, seul le splash est accessible', () {
    expect(redirectFor(const SessionLoading(), '/accueil'), '/');
    expect(redirectFor(const SessionLoading(), '/'), isNull);
  });

  test('premier lancement : présentation obligatoire', () {
    expect(redirectFor(const SessionSignedOut(), '/connexion'), '/bienvenue');
  });

  test('déconnecté : aucun écran protégé', () {
    const out = SessionSignedOut(onboarded: true);
    expect(redirectFor(out, '/accueil'), '/connexion');
    expect(redirectFor(out, '/transactions/x'), '/connexion');
    expect(redirectFor(out, '/inscription'), isNull);
  });

  test('un challenge en attente impose la saisie du code', () {
    expect(redirectFor(const SessionSignedOut(onboarded: true, pendingChallenge: challenge), '/connexion'), '/verification');
  });

  test('connecté : les écrans publics renvoient à l’accueil', () {
    expect(redirectFor(const SessionSignedIn(profile), '/connexion'), '/accueil');
    expect(redirectFor(const SessionSignedIn(profile), '/verification'), '/accueil');
    expect(redirectFor(const SessionSignedIn(profile), '/envoyer/offres'), isNull);
  });
}
