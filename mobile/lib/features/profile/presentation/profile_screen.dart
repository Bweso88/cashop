import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_colors.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/status_badge.dart';
import '../../auth/application/session_controller.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionProvider);
    if (session is! SessionSignedIn) return const SizedBox.shrink();
    final p = session.profile;

    Widget item(IconData icon, String title, {String? subtitle, Widget? trailing, VoidCallback? onTap}) => ListTile(
          leading: Icon(icon, color: AppColors.brand),
          title: Text(title),
          subtitle: subtitle == null ? null : Text(subtitle, style: const TextStyle(color: AppColors.muted)),
          trailing: trailing ?? const Icon(Icons.chevron_right, color: AppColors.muted),
          onTap: onTap,
        );

    return Scaffold(
      appBar: AppBar(title: const Text('Profil')),
      body: ListView(padding: const EdgeInsets.all(20), children: [
        AppCard(
          child: Row(children: [
            Container(
              width: 56,
              height: 56,
              alignment: Alignment.center,
              decoration: const BoxDecoration(shape: BoxShape.circle, gradient: AppColors.brandGradient),
              child: Text(p.initials, style: const TextStyle(color: AppColors.ink, fontWeight: FontWeight.w700, fontSize: 18)),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(p.displayName, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600)),
                Text(p.email, style: const TextStyle(color: AppColors.muted)),
                if (p.phone != null) Text(p.phone!, style: const TextStyle(color: AppColors.muted)),
              ]),
            ),
          ]),
        ),
        const SizedBox(height: 20),
        const SectionTitle('Sécurité'),
        AppCard(
          padding: EdgeInsets.zero,
          child: Column(children: [
            item(Icons.pin_outlined, 'PIN de transaction',
                trailing: StatusBadge(p.pinSet ? 'Défini' : 'À définir', color: p.pinSet ? AppColors.brand : AppColors.warning), onTap: () => context.push('/profil/pin')),
            item(Icons.shield_outlined, 'Double authentification',
                trailing: StatusBadge(p.mfaEnabled ? 'Activée' : 'Désactivée', color: p.mfaEnabled ? AppColors.brand : AppColors.warning)),
            item(Icons.fingerprint, 'Biométrie', subtitle: 'Face ID / empreinte pour confirmer les envois'),
          ]),
        ),
        const SizedBox(height: 20),
        const SectionTitle('Compte'),
        AppCard(
          padding: EdgeInsets.zero,
          child: Column(children: [
            item(Icons.badge_outlined, "Vérification d'identité",
                trailing: StatusBadge('${p.kycLevel ?? '—'} · ${p.kycStatus ?? ''}', color: p.identityVerified ? AppColors.brand : AppColors.warning), onTap: () => context.push('/kyc')),
            item(Icons.notifications_none, 'Notifications', onTap: () => context.push('/notifications')),
            item(Icons.settings_outlined, 'Paramètres', subtitle: 'Langue, devise préférée'),
          ]),
        ),
        const SizedBox(height: 24),
        OutlinedButton.icon(
          onPressed: () => ref.read(sessionProvider.notifier).logout(),
          icon: const Icon(Icons.logout, color: AppColors.danger),
          label: const Text('Se déconnecter', style: TextStyle(color: AppColors.danger)),
        ),
      ]),
    );
  }
}
