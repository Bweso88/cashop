import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/util/money.dart';
import '../../../shared/widgets/status_badge.dart';
import '../../home/data/demo_data.dart';
import '../application/send_draft.dart';

/// Étape 1 : montant, pays de destination, mode de réception.
class SendScreen extends ConsumerStatefulWidget {
  const SendScreen({super.key, this.mode});

  final String? mode;

  @override
  ConsumerState<SendScreen> createState() => _SendScreenState();
}

class _SendScreenState extends ConsumerState<SendScreen> {
  late final TextEditingController _amount;

  @override
  void initState() {
    super.initState();
    final preset = PayoutMethod.values.where((m) => m.name == widget.mode).firstOrNull;
    if (preset != null) Future.microtask(() => ref.read(sendDraftProvider.notifier).preselect(preset));
    _amount = TextEditingController(text: (ref.read(sendDraftProvider).amountCents ~/ 100).toString());
  }

  @override
  void dispose() {
    _amount.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final draft = ref.watch(sendDraftProvider);
    final notifier = ref.read(sendDraftProvider.notifier);
    final wallet = demoWallets.firstWhere((w) => w.currency == Currency.eur);
    final cents = parseAmount(_amount.text, Currency.eur);
    final methods = methodsFor(draft.country);
    final method = methods.contains(draft.method) ? draft.method : methods.first;
    final error = cents == null ? 'Montant invalide' : cents < 1000 ? 'Minimum 10,00 €' : cents > wallet.available ? 'Solde insuffisant' : null;

    return Scaffold(
      appBar: AppBar(title: const Text("Envoyer de l'argent"), actions: const [Padding(padding: EdgeInsets.only(right: 16), child: DemoBanner())]),
      body: ListView(padding: const EdgeInsets.all(20), children: [
        const Text('Vous envoyez', style: TextStyle(color: AppColors.muted)),
        const SizedBox(height: 8),
        TextField(
          key: const Key('amount-field'),
          controller: _amount,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          style: const TextStyle(fontSize: 34, fontWeight: FontWeight.w600),
          onChanged: (_) => setState(() {}),
          decoration: InputDecoration(suffixText: 'EUR', errorText: _amount.text.isEmpty ? null : error, helperText: 'Disponible : ${formatMoney(wallet.available, Currency.eur)}'),
        ),
        const SizedBox(height: 24),
        DropdownButtonFormField<String>(
          initialValue: draft.country,
          decoration: const InputDecoration(labelText: 'Pays de destination'),
          items: [for (final c in countries) DropdownMenuItem(value: c.code, child: Text('${c.name} · ${c.currency.code}'))],
          onChanged: (v) => notifier.update((d) => d.copyWith(country: v, method: methodsFor(v!).first)),
        ),
        const SizedBox(height: 24),
        const Text('Mode de réception', style: TextStyle(color: AppColors.muted)),
        const SizedBox(height: 10),
        for (final m in methods)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: _MethodOption(method: m, selected: m == method, onTap: () => notifier.update((d) => d.copyWith(method: m))),
          ),
        const SizedBox(height: 20),
        FilledButton(
          key: const Key('compare-quotes'),
          onPressed: error != null
              ? null
              : () {
                  notifier.update((d) => d.copyWith(amountCents: cents, method: method));
                  context.push('/envoyer/offres');
                },
          child: const Text('Comparer les offres'),
        ),
      ]),
    );
  }
}

class _MethodOption extends StatelessWidget {
  const _MethodOption({required this.method, required this.selected, required this.onTap});

  final PayoutMethod method;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final icon = switch (method) {
      PayoutMethod.mobileMoney => Icons.phone_iphone,
      PayoutMethod.cashPickup => Icons.payments_outlined,
      PayoutMethod.card => Icons.credit_card,
    };
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: selected ? AppColors.brand.withValues(alpha: 0.1) : AppColors.surface2,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: selected ? AppColors.brand : AppColors.line),
        ),
        child: Row(children: [
          Icon(icon, color: selected ? AppColors.brand : AppColors.muted),
          const SizedBox(width: 12),
          Expanded(child: Text(method.label, style: const TextStyle(fontWeight: FontWeight.w500))),
          if (selected) const Icon(Icons.check_circle, color: AppColors.brand, size: 20),
        ]),
      ),
    );
  }
}
