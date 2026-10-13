import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';

class StatusBadge extends StatelessWidget {
  const StatusBadge(this.label, {super.key, this.color = AppColors.muted});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(99)),
      child: Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w500)),
    );
  }
}

/// Signale un écran alimenté par des données de démonstration (phases 4 à 8 à venir).
class DemoBanner extends StatelessWidget {
  const DemoBanner({super.key});

  @override
  Widget build(BuildContext context) => const StatusBadge('Données de démonstration', color: AppColors.warning);
}

/// Code pays (CM, GA…) : plus fiable que les drapeaux emoji, absents de certaines plateformes.
class CountryTag extends StatelessWidget {
  const CountryTag(this.code, {super.key});

  final String code;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(color: AppColors.surface3, borderRadius: BorderRadius.circular(6)),
      child: Text(code, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: AppColors.muted, letterSpacing: 0.5)),
    );
  }
}
