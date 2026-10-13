import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_colors.dart';
import '../../../shared/widgets/cashop_logo.dart';
import '../../auth/application/session_controller.dart';

class OnboardingScreen extends ConsumerStatefulWidget {
  const OnboardingScreen({super.key});

  @override
  ConsumerState<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends ConsumerState<OnboardingScreen> {
  final _controller = PageController();
  int _page = 0;

  static const _pages = [
    (Icons.public, 'Envoyez partout', 'Mobile Money, carte bancaire ou retrait en agence : choisissez comment votre proche reçoit.'),
    (Icons.compare_arrows, 'Comparez les offres', 'Frais, taux et délai de chaque partenaire, avant de confirmer. Pas de surprise.'),
    (Icons.verified_user_outlined, 'Protégé à chaque étape', 'Double authentification, PIN et biométrie. Vos données sont chiffrées.'),
  ];

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final last = _page == _pages.length - 1;
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(children: [
            Row(children: [
              const CashopLogo(size: 32),
              const Spacer(),
              TextButton(onPressed: () => ref.read(sessionProvider.notifier).completeOnboarding(), child: const Text('Passer')),
            ]),
            Expanded(
              child: PageView(
                controller: _controller,
                onPageChanged: (i) => setState(() => _page = i),
                children: [
                  for (final (icon, title, body) in _pages)
                    Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Container(
                        width: 140,
                        height: 140,
                        decoration: BoxDecoration(shape: BoxShape.circle, color: AppColors.brand.withValues(alpha: 0.12), border: Border.all(color: AppColors.brand.withValues(alpha: 0.3))),
                        child: Icon(icon, size: 64, color: AppColors.brand),
                      ),
                      const SizedBox(height: 40),
                      Text(title, textAlign: TextAlign.center, style: const TextStyle(fontSize: 26, fontWeight: FontWeight.w600)),
                      const SizedBox(height: 12),
                      Text(body, textAlign: TextAlign.center, style: const TextStyle(fontSize: 16, color: AppColors.muted, height: 1.4)),
                    ]),
                ],
              ),
            ),
            Row(mainAxisAlignment: MainAxisAlignment.center, children: [
              for (var i = 0; i < _pages.length; i++)
                AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  margin: const EdgeInsets.symmetric(horizontal: 4),
                  width: i == _page ? 24 : 8,
                  height: 8,
                  decoration: BoxDecoration(color: i == _page ? AppColors.brand : AppColors.surface3, borderRadius: BorderRadius.circular(8)),
                ),
            ]),
            const SizedBox(height: 28),
            FilledButton(
              onPressed: last
                  ? () => ref.read(sessionProvider.notifier).completeOnboarding()
                  : () => _controller.nextPage(duration: const Duration(milliseconds: 300), curve: Curves.easeOut),
              child: Text(last ? 'Commencer' : 'Suivant'),
            ),
          ]),
        ),
      ),
    );
  }
}
