import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/application/session_controller.dart';
import '../../features/auth/presentation/login_screen.dart';
import '../../features/auth/presentation/otp_screen.dart';
import '../../features/auth/presentation/register_screen.dart';
import '../../features/home/presentation/home_screen.dart';
import '../../features/home/presentation/home_shell.dart';
import '../../features/home/presentation/receive_screen.dart';
import '../../features/kyc/presentation/kyc_screen.dart';
import '../../features/onboarding/presentation/onboarding_screen.dart';
import '../../features/onboarding/presentation/splash_screen.dart';
import '../../features/profile/presentation/notifications_screen.dart';
import '../../features/profile/presentation/pin_setup_screen.dart';
import '../../features/profile/presentation/profile_screen.dart';
import '../../features/send/presentation/confirm_screen.dart';
import '../../features/send/presentation/quotes_screen.dart';
import '../../features/send/presentation/result_screen.dart';
import '../../features/send/presentation/send_screen.dart';
import '../../features/transactions/presentation/history_screen.dart';
import '../../features/transactions/presentation/transaction_detail_screen.dart';

/// Redirection selon l'état de session : un seul endroit décide de l'écran autorisé.
String? redirectFor(SessionState session, String location) {
  const publicRoutes = ['/connexion', '/inscription'];
  return switch (session) {
    SessionLoading() => location == '/' ? null : '/',
    SessionSignedOut(onboarded: false) => location == '/bienvenue' ? null : '/bienvenue',
    SessionSignedOut(pendingChallenge: != null) => location == '/verification' ? null : '/verification',
    SessionSignedOut() => publicRoutes.contains(location) ? null : '/connexion',
    SessionSignedIn() => (location == '/' || location == '/bienvenue' || location == '/verification' || publicRoutes.contains(location)) ? '/accueil' : null,
  };
}

final routerProvider = Provider<GoRouter>((ref) {
  // Le routeur réévalue la redirection à chaque changement de session.
  final refresh = ValueNotifier<SessionState>(ref.read(sessionProvider));
  ref.listen(sessionProvider, (_, next) => refresh.value = next);
  ref.onDispose(refresh.dispose);

  return GoRouter(
    initialLocation: '/',
    refreshListenable: refresh,
    redirect: (context, state) => redirectFor(ref.read(sessionProvider), state.matchedLocation),
    routes: [
      GoRoute(path: '/', builder: (_, _) => const SplashScreen()),
      GoRoute(path: '/bienvenue', builder: (_, _) => const OnboardingScreen()),
      GoRoute(path: '/connexion', builder: (_, _) => const LoginScreen()),
      GoRoute(path: '/inscription', builder: (_, _) => const RegisterScreen()),
      GoRoute(path: '/verification', builder: (_, _) => const OtpScreen()),
      StatefulShellRoute.indexedStack(
        builder: (_, _, shell) => HomeShell(shell: shell),
        branches: [
          StatefulShellBranch(routes: [GoRoute(path: '/accueil', builder: (_, _) => const HomeScreen())]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/envoyer',
              builder: (_, state) => SendScreen(mode: state.uri.queryParameters['mode']),
              routes: [
                GoRoute(path: 'offres', builder: (_, _) => const QuotesScreen()),
                GoRoute(path: 'confirmation', builder: (_, _) => const ConfirmScreen()),
              ],
            ),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/transactions',
              builder: (_, _) => const HistoryScreen(),
              routes: [GoRoute(path: ':id', builder: (_, state) => TransactionDetailScreen(id: state.pathParameters['id']!))],
            ),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(path: '/profil', builder: (_, _) => const ProfileScreen(), routes: [GoRoute(path: 'pin', builder: (_, _) => const PinSetupScreen())]),
          ]),
        ],
      ),
      GoRoute(path: '/envoyer/resultat', builder: (_, _) => const ResultScreen()),
      GoRoute(path: '/recevoir', builder: (_, _) => const ReceiveScreen()),
      GoRoute(path: '/kyc', builder: (_, _) => const KycScreen()),
      GoRoute(path: '/notifications', builder: (_, _) => const NotificationsScreen()),
    ],
  );
});
