import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:local_auth/local_auth.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/util/money.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/pin_pad.dart';
import '../../home/data/demo_data.dart';
import '../application/send_draft.dart';

/// Étape 3 : bénéficiaire, récapitulatif, puis authentification (PIN ou biométrie).
class ConfirmScreen extends ConsumerStatefulWidget {
  const ConfirmScreen({super.key});

  @override
  ConsumerState<ConfirmScreen> createState() => _ConfirmScreenState();
}

class _ConfirmScreenState extends ConsumerState<ConfirmScreen> {
  final _name = TextEditingController(text: 'Paul Mbarga');
  late final TextEditingController _detail;

  @override
  void initState() {
    super.initState();
    // Valeur d'exemple cohérente avec le mode de réception choisi.
    _detail = TextEditingController(
      text: switch (ref.read(sendDraftProvider).quote?.method) {
        PayoutMethod.mobileMoney => '+237 690 12 34 56',
        PayoutMethod.card => '4111 1111 1111 1111',
        _ => 'Douala',
      },
    );
  }

  @override
  void dispose() {
    _name.dispose();
    _detail.dispose();
    super.dispose();
  }

  Future<void> _authenticate() async {
    ref.read(sendDraftProvider.notifier).update((d) => d.copyWith(recipientName: _name.text, recipientDetail: _detail.text));
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.surface,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (sheet) => _AuthSheet(onConfirmed: () {
        Navigator.of(sheet).pop();
        context.go('/envoyer/resultat');
      }),
    );
  }

  @override
  Widget build(BuildContext context) {
    final draft = ref.watch(sendDraftProvider);
    final q = draft.quote;
    if (q == null) return const Scaffold(body: Center(child: Text('Aucune offre sélectionnée')));
    final detailLabel = switch (q.method) {
      PayoutMethod.mobileMoney => 'Numéro Mobile Money',
      PayoutMethod.card => 'Numéro de carte',
      PayoutMethod.cashPickup => 'Ville de retrait',
    };
    final rows = [
      ('Via', '${q.provider.label} · ${q.method.label}'),
      ('Montant envoyé', formatMoney(q.sendAmount, Currency.eur)),
      ('Frais', formatMoney(q.fees, Currency.eur)),
      ('Taux appliqué', '1 EUR = ${q.rate.toString().replaceAll('.', ',')} ${q.receiveCurrency.code}'),
      ('Total débité', formatMoney(q.totalDebit, Currency.eur)),
    ];

    return Scaffold(
      appBar: AppBar(title: const Text('Confirmation')),
      body: ListView(padding: const EdgeInsets.all(20), children: [
        TextField(controller: _name, decoration: const InputDecoration(labelText: 'Nom complet du bénéficiaire')),
        const SizedBox(height: 12),
        TextField(controller: _detail, decoration: InputDecoration(labelText: detailLabel)),
        const SizedBox(height: 20),
        AppCard(
          child: Column(children: [
            for (final (k, v) in rows)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(k, style: const TextStyle(color: AppColors.muted)),
                  const SizedBox(width: 16),
                  Expanded(child: Text(v, textAlign: TextAlign.end, style: const TextStyle(fontWeight: FontWeight.w500))),
                ]),
              ),
            const Divider(height: 24),
            Row(children: [
              const Expanded(child: Text('Le bénéficiaire reçoit', style: TextStyle(fontWeight: FontWeight.w600))),
              Text(formatMoney(q.receiveAmount, q.receiveCurrency), style: const TextStyle(color: AppColors.brand, fontSize: 20, fontWeight: FontWeight.w700)),
            ]),
          ]),
        ),
        const SizedBox(height: 24),
        FilledButton.icon(
          key: const Key('confirm-send'),
          onPressed: _authenticate,
          icon: const Icon(Icons.lock_outline),
          label: Text('Envoyer ${formatMoney(q.totalDebit, Currency.eur)}'),
        ),
      ]),
    );
  }
}

/// Authentification forte : biométrie si disponible, sinon PIN de transaction.
/// La biométrie déverrouille la clé de l'appareil, qui signera le challenge serveur (phase 8).
class _AuthSheet extends StatefulWidget {
  const _AuthSheet({required this.onConfirmed});

  final VoidCallback onConfirmed;

  @override
  State<_AuthSheet> createState() => _AuthSheetState();
}

class _AuthSheetState extends State<_AuthSheet> {
  final _auth = LocalAuthentication();
  bool _biometricAvailable = false;

  @override
  void initState() {
    super.initState();
    if (!kIsWeb) {
      _auth.isDeviceSupported().then((ok) {
        if (mounted) setState(() => _biometricAvailable = ok);
      });
    }
  }

  Future<void> _biometric() async {
    final ok = await _auth.authenticate(localizedReason: 'Confirmez votre transfert Cashop', biometricOnly: true);
    if (ok) widget.onConfirmed();
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 24, 20, 16),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const Text('Saisissez votre PIN', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w600)),
          const SizedBox(height: 6),
          const Text('Pour confirmer ce transfert', style: TextStyle(color: AppColors.muted)),
          const SizedBox(height: 24),
          PinPad(onCompleted: (_) => widget.onConfirmed()),
          if (_biometricAvailable)
            TextButton.icon(onPressed: _biometric, icon: const Icon(Icons.fingerprint), label: const Text('Utiliser la biométrie')),
        ]),
      ),
    );
  }
}
