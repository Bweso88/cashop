import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_client.dart';
import '../../../core/theme/app_colors.dart';
import '../../../shared/widgets/pin_pad.dart';
import '../../auth/application/session_controller.dart';
import '../../auth/data/auth_repository.dart';

/// Définition du PIN de transaction (API réelle : PUT /auth/pin). Saisie puis confirmation.
class PinSetupScreen extends ConsumerStatefulWidget {
  const PinSetupScreen({super.key});

  @override
  ConsumerState<PinSetupScreen> createState() => _PinSetupScreenState();
}

class _PinSetupScreenState extends ConsumerState<PinSetupScreen> {
  final _pad = GlobalKey<PinPadState>();
  String? _first;
  String? _error;

  Future<void> _onCompleted(String pin) async {
    if (_first == null) {
      setState(() {
        _first = pin;
        _error = null;
      });
      _pad.currentState?.clear();
      return;
    }
    if (pin != _first) {
      setState(() {
        _first = null;
        _error = 'Les deux PIN ne correspondent pas.';
      });
      _pad.currentState?.clear();
      return;
    }
    try {
      await ref.read(authRepositoryProvider).setPin(pin);
      await ref.read(sessionProvider.notifier).refreshProfile();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('PIN enregistré')));
        context.pop();
      }
    } on ApiException catch (e) {
      setState(() {
        _first = null;
        _error = e.fieldErrors['pin'] ?? e.fieldErrors['current_pin'] ?? e.message;
      });
      _pad.currentState?.clear();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('PIN de transaction')),
      body: ListView(padding: const EdgeInsets.all(24), children: [
        Text(_first == null ? 'Choisissez un PIN à 6 chiffres' : 'Confirmez votre PIN', textAlign: TextAlign.center, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w600)),
        const SizedBox(height: 8),
        const Text('Évitez 123456, 000000 et les suites.', textAlign: TextAlign.center, style: TextStyle(color: AppColors.muted)),
        if (_error != null) Padding(padding: const EdgeInsets.only(top: 12), child: Text(_error!, textAlign: TextAlign.center, style: const TextStyle(color: AppColors.danger))),
        const SizedBox(height: 32),
        PinPad(key: _pad, onCompleted: _onCompleted),
      ]),
    );
  }
}
