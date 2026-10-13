import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/util/money.dart';
import '../../../shared/widgets/status_badge.dart';
import '../../home/data/demo_data.dart';

Color statusColor(TransferStatus s) => switch (s) {
      TransferStatus.completed => AppColors.brand,
      TransferStatus.processing => AppColors.info,
      TransferStatus.failed => AppColors.danger,
    };

class TransferTile extends StatelessWidget {
  const TransferTile(this.transfer, {super.key});

  final Transfer transfer;

  @override
  Widget build(BuildContext context) {
    final t = transfer;
    final amount = t.outgoing ? formatMoney(-t.sendAmount, t.sendCurrency, signed: true) : formatMoney(t.receiveAmount, t.receiveCurrency, signed: true);
    return InkWell(
      onTap: () => context.push('/transactions/${t.id}'),
      borderRadius: BorderRadius.circular(14),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 4),
        child: Row(children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(color: t.outgoing ? AppColors.surface3 : AppColors.brand.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(14)),
            child: Icon(t.outgoing ? Icons.north_east : Icons.south_west, size: 20, color: t.outgoing ? AppColors.text : AppColors.brand),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                CountryTag(t.country),
                const SizedBox(width: 6),
                Flexible(child: Text(t.counterparty, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w500))),
              ]),
              const SizedBox(height: 2),
              Text('${t.provider.label} · ${DateFormat('d MMM', 'fr_FR').format(t.createdAt)}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AppColors.muted, fontSize: 13)),
            ]),
          ),
          const SizedBox(width: 8),
          Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
            Text(amount, style: TextStyle(fontWeight: FontWeight.w600, color: t.outgoing ? AppColors.text : AppColors.brand)),
            const SizedBox(height: 4),
            StatusBadge(t.status.label, color: statusColor(t.status)),
          ]),
        ]),
      ),
    );
  }
}
