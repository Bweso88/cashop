import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../shared/widgets/cashop_logo.dart';
import '../../auth/application/session_controller.dart';

/// Écran de démarrage : restaure la session (jeton du stockage sécurisé) puis le routeur redirige.
class SplashScreen extends ConsumerStatefulWidget {
  const SplashScreen({super.key});

  @override
  ConsumerState<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends ConsumerState<SplashScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() => ref.read(sessionProvider.notifier).restore());
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
        CashopLogo(size: 72, showName: false),
        SizedBox(height: 20),
        Text('Cashop', style: TextStyle(fontSize: 28, fontWeight: FontWeight.w600)),
        SizedBox(height: 32),
        SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2)),
      ])),
    );
  }
}
