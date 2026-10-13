import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/util/money.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/status_badge.dart';
import '../../home/data/demo_data.dart';
import 'transfer_tile.dart';

class TransactionDetailScreen extends StatelessWidget {
  const TransactionDetailScreen({super.key, required this.id});

  final String id;

  @override
  Widget build(BuildContext context) {
    final t = demoTransfers.where((x) => x.id == id).firstOrNull;
    if (t == null) return Scaffold(appBar: AppBar(), body: const Center(child: Text('Transaction introuvable')));
    final dateFormat = DateFormat("d MMMM y 'à' HH:mm", 'fr_FR');
    final rows = [
      ('Partenaire', t.provider.label),
      ('Mode de réception', t.method.label),
      ('Pays', countryByCode(t.country).name),
      ('Frais', formatMoney(t.fees, t.sendCurrency)),
      ('Référence', t.reference),
    ];

    return Scaffold(
      appBar: AppBar(title: const Text('Détail du transfert')),
      body: ListView(padding: const EdgeInsets.all(20), children: [
        AppCard(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Expanded(child: Text(t.outgoing ? 'Envoyé à' : 'Reçu de', style: const TextStyle(color: AppColors.muted))),
              StatusBadge(t.status.label, color: statusColor(t.status)),
            ]),
            const SizedBox(height: 4),
            Text(t.counterparty, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w600)),
            const SizedBox(height: 16),
            Text(
              t.outgoing ? formatMoney(-t.sendAmount, t.sendCurrency, signed: true) : formatMoney(t.receiveAmount, t.receiveCurrency, signed: true),
              style: const TextStyle(fontSize: 32, fontWeight: FontWeight.w600),
            ),
            if (t.outgoing) Text('Reçu : ${formatMoney(t.receiveAmount, t.receiveCurrency)}', style: const TextStyle(color: AppColors.muted)),
            const SizedBox(height: 12),
            for (final (k, v) in rows)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 10),
                child: Row(children: [
                  Expanded(child: Text(k, style: const TextStyle(color: AppColors.muted))),
                  Text(v, style: const TextStyle(fontWeight: FontWeight.w500)),
                ]),
              ),
          ]),
        ),
        const SizedBox(height: 16),
        AppCard(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const SectionTitle('Suivi'),
            for (final (i, e) in t.timeline.indexed)
              Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Column(children: [
                  Container(width: 14, height: 14, margin: const EdgeInsets.only(top: 3), decoration: BoxDecoration(shape: BoxShape.circle, color: e.status == 'FAILED' ? AppColors.danger : AppColors.brand)),
                  if (i < t.timeline.length - 1) Container(width: 2, height: 40, color: AppColors.line),
                ]),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(e.label, style: const TextStyle(fontWeight: FontWeight.w500)),
                    Text('${dateFormat.format(e.at.toLocal())} · ${e.status}', style: const TextStyle(color: AppColors.muted, fontSize: 12)),
                  ]),
                ),
              ]),
          ]),
        ),
      ]),
    );
  }
}
