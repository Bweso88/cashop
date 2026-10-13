import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../shared/widgets/status_badge.dart';
import '../../home/data/demo_data.dart';
import 'transfer_tile.dart';

class HistoryScreen extends StatefulWidget {
  const HistoryScreen({super.key});

  @override
  State<HistoryScreen> createState() => _HistoryScreenState();
}

class _HistoryScreenState extends State<HistoryScreen> {
  String _filter = 'all';

  static const _filters = {'all': 'Toutes', 'out': 'Envoyées', 'in': 'Reçues', 'failed': 'Échouées'};

  bool _match(Transfer t) => switch (_filter) {
        'out' => t.outgoing,
        'in' => !t.outgoing,
        'failed' => t.status == TransferStatus.failed,
        _ => true,
      };

  @override
  Widget build(BuildContext context) {
    final items = demoTransfers.where(_match).toList();
    return Scaffold(
      appBar: AppBar(title: const Text('Historique'), actions: const [Padding(padding: EdgeInsets.only(right: 16), child: DemoBanner())]),
      body: ListView(padding: const EdgeInsets.symmetric(horizontal: 20), children: [
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(children: [
            for (final e in _filters.entries)
              Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ChoiceChip(
                  label: Text(e.value),
                  selected: _filter == e.key,
                  onSelected: (_) => setState(() => _filter = e.key),
                  selectedColor: AppColors.brand,
                  labelStyle: TextStyle(color: _filter == e.key ? AppColors.ink : AppColors.muted),
                  backgroundColor: AppColors.surface2,
                  side: BorderSide.none,
                  showCheckmark: false,
                ),
              ),
          ]),
        ),
        const SizedBox(height: 12),
        for (final t in items) TransferTile(t),
        if (items.isEmpty) const Padding(padding: EdgeInsets.all(40), child: Text('Aucune transaction.', textAlign: TextAlign.center, style: TextStyle(color: AppColors.muted))),
      ]),
    );
  }
}
