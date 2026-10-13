import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/storage/secure_store.dart';
import '../data/auth_models.dart';
import '../data/auth_repository.dart';

sealed class SessionState {
  const SessionState();
}

/// Démarrage : on ne sait pas encore si un jeton valide existe.
class SessionLoading extends SessionState {
  const SessionLoading();
}

class SessionSignedOut extends SessionState {
  const SessionSignedOut({this.onboarded = false, this.pendingChallenge});
  final bool onboarded;
  /// Challenge OTP en attente de saisie (après login ou inscription).
  final OtpChallenge? pendingChallenge;
}

class SessionSignedIn extends SessionState {
  const SessionSignedIn(this.profile);
  final Profile profile;
}

class SessionController extends Notifier<SessionState> {
  AuthRepository get _repo => ref.read(authRepositoryProvider);
  SecureStore get _store => ref.read(secureStoreProvider);

  @override
  SessionState build() => const SessionLoading();

  /// Au lancement : un jeton enregistré et encore valide ouvre directement la session.
  Future<void> restore() async {
    final onboarded = await _store.hasOnboarded();
    if (!await _repo.hasToken()) {
      state = SessionSignedOut(onboarded: onboarded);
      return;
    }
    try {
      state = SessionSignedIn(await _repo.profile());
    } on ApiException catch (e) {
      if (e.status == 401) await _store.deleteToken();
      state = SessionSignedOut(onboarded: onboarded);
    }
  }

  Future<void> completeOnboarding() async {
    await _store.markOnboarded();
    state = const SessionSignedOut(onboarded: true);
  }

  Future<void> login(String login, String password) async {
    final challenge = await _repo.login(login, password);
    state = SessionSignedOut(onboarded: true, pendingChallenge: challenge);
  }

  Future<void> register({
    required String email,
    required String phone,
    required String password,
    required String firstName,
    required String lastName,
    required String country,
  }) async {
    final challenge = await _repo.register(email: email, phone: phone, password: password, firstName: firstName, lastName: lastName, country: country);
    state = SessionSignedOut(onboarded: true, pendingChallenge: challenge);
  }

  Future<void> verifyOtp(String code) async {
    final current = state;
    if (current is! SessionSignedOut || current.pendingChallenge == null) return;
    state = SessionSignedIn(await _repo.verifyOtp(current.pendingChallenge!.challengeId, code));
  }

  void cancelChallenge() => state = const SessionSignedOut(onboarded: true);

  Future<void> refreshProfile() async {
    if (state is SessionSignedIn) state = SessionSignedIn(await _repo.profile());
  }

  Future<void> logout() async {
    await _repo.logout();
    state = const SessionSignedOut(onboarded: true);
  }
}

final sessionProvider = NotifierProvider<SessionController, SessionState>(SessionController.new);
