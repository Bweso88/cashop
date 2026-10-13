import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_colors.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/status_badge.dart';
import '../../auth/application/session_controller.dart';

class ReceiveScreen extends ConsumerWidget {
  const ReceiveScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionProvider);
    final profile = session is SessionSignedIn ? session.profile : null;

    return Scaffold(
      appBar: AppBar(title: const Text("Recevoir de l'argent")),
      body: ListView(padding: const EdgeInsets.all(20), children: [
        AppCard(
          child: Column(children: [
            const Icon(Icons.qr_code_2, size: 160, color: AppColors.text),
            const SizedBox(height: 12),
            Text(profile?.displayName ?? '', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600)),
            Text(profile?.phone ?? '', style: const TextStyle(color: AppColors.muted)),
            const SizedBox(height: 12),
            const Text("Partagez votre numéro ou votre code Cashop pour recevoir de l'argent.", textAlign: TextAlign.center, style: TextStyle(color: AppColors.muted)),
          ]),
        ),
        const SizedBox(height: 16),
        const AppCard(
          child: Row(children: [
            Icon(Icons.phone_iphone, color: AppColors.brand),
            SizedBox(width: 12),
            Expanded(child: Text('Recharger depuis Mobile Money (MTN, Airtel)')),
            StatusBadge('Bientôt', color: AppColors.info),
          ]),
        ),
      ]),
    );
  }
}
