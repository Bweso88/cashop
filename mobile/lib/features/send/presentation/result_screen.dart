import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/util/money.dart';
import '../application/send_draft.dart';

/// Étapes "Traitement" puis "Succès" (en production : suivi via webhooks / polling).
class ResultScreen extends ConsumerStatefulWidget {
  const ResultScreen({super.key});

  @override
  ConsumerState<ResultScreen> createState() => _ResultScreenState();
}

class _ResultScreenState extends ConsumerState<ResultScreen> {
  bool _done = false;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _timer = Timer(const Duration(milliseconds: 1800), () => setState(() => _done = true));
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final draft = ref.watch(sendDraftProvider);
    final q = draft.quote;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(children: [
            const Spacer(),
            if (!_done) ...[
              const SizedBox(width: 72, height: 72, child: CircularProgressIndicator(strokeWidth: 4)),
              const SizedBox(height: 28),
              const Text('Transfert en cours…', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w600)),
              const SizedBox(height: 8),
              Text('Transmission à ${q?.provider.label ?? 'notre partenaire'}', style: const TextStyle(color: AppColors.muted)),
            ] else ...[
              Container(
                width: 110,
                height: 110,
                decoration: BoxDecoration(shape: BoxShape.circle, color: AppColors.brand.withValues(alpha: 0.12), border: Border.all(color: AppColors.brand.withValues(alpha: 0.4), width: 2)),
                child: const Icon(Icons.check_rounded, size: 64, color: AppColors.brand),
              ),
              const SizedBox(height: 28),
              const Text('Transfert envoyé', style: TextStyle(fontSize: 24, fontWeight: FontWeight.w600)),
              const SizedBox(height: 10),
              if (q != null)
                Text(
                  '${draft.recipientName} recevra ${formatMoney(q.receiveAmount, q.receiveCurrency)} via ${q.provider.label}.',
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: AppColors.muted, fontSize: 16),
                ),
              const SizedBox(height: 8),
              const Text("Simulation : aucun fonds n'a été déplacé.", style: TextStyle(color: AppColors.warning, fontSize: 12)),
            ],
            const Spacer(),
            if (_done) ...[
              FilledButton(
                onPressed: () {
                  ref.read(sendDraftProvider.notifier).reset();
                  context.go('/accueil');
                },
                child: const Text("Retour à l'accueil"),
              ),
              const SizedBox(height: 12),
              OutlinedButton(onPressed: () => context.go('/transactions'), child: const Text("Voir l'historique")),
            ],
          ]),
        ),
      ),
    );
  }
}
