import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../shared/widgets/status_badge.dart';

class NotificationsScreen extends StatelessWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    const items = [
      (Icons.check_circle_outline, 'Transfert livré', 'Aminata Kouassi a reçu 65 595 FCFA.', 'Il y a 2 h'),
      (Icons.schedule, 'Retrait disponible bientôt', 'Le transfert vers Paul Mbarga est en cours.', 'Hier'),
      (Icons.error_outline, 'Transfert échoué · remboursé', 'Numéro Airtel introuvable. Fonds restitués.', '8 oct.'),
    ];
    return Scaffold(
      appBar: AppBar(title: const Text('Notifications'), actions: const [Padding(padding: EdgeInsets.only(right: 16), child: DemoBanner())]),
      body: ListView.separated(
        padding: const EdgeInsets.all(12),
        itemCount: items.length,
        separatorBuilder: (_, _) => const Divider(height: 1),
        itemBuilder: (_, i) {
          final (icon, title, body, time) = items[i];
          return ListTile(
            leading: Icon(icon, color: AppColors.brand),
            title: Text(title),
            subtitle: Text(body, style: const TextStyle(color: AppColors.muted)),
            trailing: Text(time, style: const TextStyle(color: AppColors.faint, fontSize: 12)),
          );
        },
      ),
    );
  }
}
