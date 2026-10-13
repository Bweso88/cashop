import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/util/money.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/status_badge.dart';
import '../../auth/application/session_controller.dart';
import '../../transactions/presentation/transfer_tile.dart';
import '../data/demo_data.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionProvider);
    final profile = session is SessionSignedIn ? session.profile : null;

    return Scaffold(
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () => ref.read(sessionProvider.notifier).refreshProfile(),
          child: ListView(padding: const EdgeInsets.fromLTRB(20, 16, 20, 24), children: [
            Row(children: [
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('Bonjour ${profile?.firstName ?? ''}'.trim(), style: const TextStyle(fontSize: 26, fontWeight: FontWeight.w600)),
                  const SizedBox(height: 6),
                  if (profile != null)
                    StatusBadge(
                      profile.identityVerified ? 'Identité vérifiée · ${profile.kycLevel}' : 'Identité à vérifier',
                      color: profile.identityVerified ? AppColors.brand : AppColors.warning,
                    ),
                ]),
              ),
              IconButton(
                onPressed: () => context.push('/notifications'),
                icon: const Icon(Icons.notifications_none),
                style: IconButton.styleFrom(backgroundColor: AppColors.surface2),
              ),
            ]),
            const SizedBox(height: 20),
            const Align(alignment: Alignment.centerLeft, child: DemoBanner()),
            const SizedBox(height: 12),
            AppCard(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text('Solde total estimé', style: TextStyle(color: AppColors.muted)),
                const SizedBox(height: 4),
                Text(formatMoney(demoTotalInEur(), Currency.eur), style: const TextStyle(fontSize: 34, fontWeight: FontWeight.w600, letterSpacing: -0.5)),
                const SizedBox(height: 16),
                SizedBox(
                  height: 128,
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    itemCount: demoWallets.length,
                    separatorBuilder: (_, _) => const SizedBox(width: 12),
                    itemBuilder: (_, i) => _WalletCard(demoWallets[i], AppColors.walletGradients[i % 3]),
                  ),
                ),
              ]),
            ),
            const SizedBox(height: 20),
            const _QuickActions(),
            const SizedBox(height: 24),
            SectionTitle('Transactions récentes', action: TextButton(onPressed: () => context.go('/transactions'), child: const Text('Tout voir'))),
            for (final t in demoTransfers.take(4)) TransferTile(t),
          ]),
        ),
      ),
    );
  }
}

class _WalletCard extends StatelessWidget {
  const _WalletCard(this.wallet, this.gradient);

  final Wallet wallet;
  final Gradient gradient;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 220,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(gradient: gradient, borderRadius: BorderRadius.circular(18)),
      // Une ligne par texte, montant réduit si besoin : la carte reste lisible même avec
      // une grande taille de police (réglages d'accessibilité du téléphone).
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Expanded(child: Text(wallet.label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white70, fontSize: 13))),
          const SizedBox(width: 6),
          Text(wallet.masked, maxLines: 1, style: const TextStyle(color: Colors.white70, fontSize: 12)),
        ]),
        const Spacer(),
        FittedBox(
          fit: BoxFit.scaleDown,
          alignment: Alignment.centerLeft,
          child: Text(formatMoney(wallet.available, wallet.currency), maxLines: 1, style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w600)),
        ),
        const SizedBox(height: 2),
        Text(
          wallet.reserved > 0 ? "${formatMoney(wallet.reserved, wallet.currency)} en cours d'envoi" : 'Disponible',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(color: Colors.white70, fontSize: 12),
        ),
      ]),
    );
  }
}

class _QuickActions extends StatelessWidget {
  const _QuickActions();

  @override
  Widget build(BuildContext context) {
    final actions = [
      (Icons.send, 'Envoyer', '/envoyer', true),
      (Icons.south_west, 'Recevoir', '/recevoir', false),
      (Icons.phone_iphone, 'Mobile Money', '/envoyer?mode=mobileMoney', false),
      (Icons.public, 'International', '/envoyer?mode=card', false),
      (Icons.payments_outlined, 'Retrait cash', '/envoyer?mode=cashPickup', false),
    ];
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        for (final (icon, label, route, primary) in actions)
          Expanded(
            child: InkWell(
              borderRadius: BorderRadius.circular(16),
              onTap: () => route.startsWith('/envoyer') ? context.go(route) : context.push(route),
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 6),
                child: Column(children: [
                  Container(
                    width: 52,
                    height: 52,
                    decoration: BoxDecoration(color: primary ? AppColors.brand : AppColors.surface2, borderRadius: BorderRadius.circular(18), border: primary ? null : Border.all(color: AppColors.line)),
                    child: Icon(icon, color: primary ? AppColors.ink : AppColors.text, size: 22),
                  ),
                  const SizedBox(height: 8),
                  // Un mot seul ne doit jamais être coupé : il est réduit pour tenir sur une ligne.
                  label.contains(' ')
                      ? Text(label, textAlign: TextAlign.center, maxLines: 2, style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w500))
                      : FittedBox(fit: BoxFit.scaleDown, child: Text(label, maxLines: 1, style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w500))),
                ]),
              ),
            ),
          ),
      ],
    );
  }
}
