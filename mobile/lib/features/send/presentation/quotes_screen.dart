import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/util/money.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/status_badge.dart';
import '../../home/data/demo_data.dart';
import '../application/send_draft.dart';

/// Étape 2 : comparaison des offres des partenaires.
class QuotesScreen extends ConsumerWidget {
  const QuotesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final draft = ref.watch(sendDraftProvider);
    final quotes = demoQuotes(draft.amountCents, draft.country, draft.method);
    final country = countryByCode(draft.country);

    return Scaffold(
      appBar: AppBar(title: const Text('Choisissez une offre')),
      body: ListView(padding: const EdgeInsets.all(20), children: [
        Text('${quotes.length} offre${quotes.length > 1 ? 's' : ''} pour ${formatMoney(draft.amountCents, Currency.eur)} vers ${country.name}', style: const TextStyle(color: AppColors.muted)),
        const SizedBox(height: 16),
        for (final (i, q) in quotes.indexed)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: AppCard(
              onTap: () {
                ref.read(sendDraftProvider.notifier).update((d) => d.copyWith(quote: q));
                context.push('/envoyer/confirmation');
              },
              child: Row(children: [
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Wrap(spacing: 8, crossAxisAlignment: WrapCrossAlignment.center, children: [
                      Text(q.provider.label, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 16)),
                      if (i == 0) const StatusBadge('Moins cher', color: AppColors.brand),
                    ]),
                    const SizedBox(height: 6),
                    Text('Frais ${formatMoney(q.fees, Currency.eur)} · ${q.delay}', style: const TextStyle(color: AppColors.muted, fontSize: 13)),
                  ]),
                ),
                Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                  const Text('Reçu', style: TextStyle(color: AppColors.muted, fontSize: 12)),
                  Text(formatMoney(q.receiveAmount, q.receiveCurrency), style: const TextStyle(color: AppColors.brand, fontWeight: FontWeight.w600, fontSize: 17)),
                ]),
              ]),
            ),
          ),
        const SizedBox(height: 8),
        const Text('Montants, frais et taux simulés : les vrais tarifs viendront des API des partenaires.', style: TextStyle(color: AppColors.warning, fontSize: 12)),
      ]),
    );
  }
}
