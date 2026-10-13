import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/theme/app_colors.dart';
import '../../../shared/widgets/pin_pad.dart';
import '../application/session_controller.dart';

class OtpScreen extends ConsumerStatefulWidget {
  const OtpScreen({super.key});

  @override
  ConsumerState<OtpScreen> createState() => _OtpScreenState();
}

class _OtpScreenState extends ConsumerState<OtpScreen> {
  final _pad = GlobalKey<PinPadState>();
  bool _loading = false;
  String? _error;

  Future<void> _verify(String code) async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await ref.read(sessionProvider.notifier).verifyOtp(code);
    } on ApiException catch (e) {
      _pad.currentState?.clear();
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(sessionProvider);
    final challenge = session is SessionSignedOut ? session.pendingChallenge : null;

    return Scaffold(
      appBar: AppBar(leading: BackButton(onPressed: () => ref.read(sessionProvider.notifier).cancelChallenge())),
      body: SafeArea(
        child: ListView(padding: const EdgeInsets.symmetric(horizontal: 24), children: [
          const Text('Vérification en deux étapes', style: TextStyle(fontSize: 26, fontWeight: FontWeight.w600)),
          const SizedBox(height: 8),
          Text(challenge?.instructions ?? '', style: const TextStyle(color: AppColors.muted, fontSize: 16)),
          const SizedBox(height: 40),
          if (_error != null) Padding(padding: const EdgeInsets.only(bottom: 16), child: Text(_error!, textAlign: TextAlign.center, style: const TextStyle(color: AppColors.danger))),
          if (_loading) const Padding(padding: EdgeInsets.only(bottom: 16), child: Center(child: CircularProgressIndicator())),
          PinPad(key: _pad, obscure: false, onCompleted: _verify),
        ]),
      ),
    );
  }
}
