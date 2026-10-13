import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_client.dart';
import '../../../core/theme/app_colors.dart';
import '../../../shared/widgets/cashop_logo.dart';
import '../application/session_controller.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _login = TextEditingController();
  final _password = TextEditingController();
  bool _loading = false;
  bool _obscure = true;
  String? _error;

  @override
  void dispose() {
    _login.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await ref.read(sessionProvider.notifier).login(_login.text.trim(), _password.text);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: ListView(padding: const EdgeInsets.all(24), children: [
          const SizedBox(height: 24),
          const CashopLogo(size: 40),
          const SizedBox(height: 48),
          const Text('Bon retour', style: TextStyle(fontSize: 28, fontWeight: FontWeight.w600)),
          const SizedBox(height: 6),
          const Text('Connectez-vous pour gérer votre argent.', style: TextStyle(color: AppColors.muted, fontSize: 16)),
          const SizedBox(height: 32),
          if (_error != null) ...[
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(color: AppColors.danger.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(12), border: Border.all(color: AppColors.danger.withValues(alpha: 0.3))),
              child: Text(_error!, style: const TextStyle(color: AppColors.danger)),
            ),
            const SizedBox(height: 16),
          ],
          TextField(
            key: const Key('login-field'),
            controller: _login,
            keyboardType: TextInputType.emailAddress,
            autofillHints: const [AutofillHints.username],
            decoration: const InputDecoration(labelText: 'E-mail ou téléphone', hintText: 'vous@exemple.com ou +237…'),
          ),
          const SizedBox(height: 16),
          TextField(
            key: const Key('password-field'),
            controller: _password,
            obscureText: _obscure,
            autofillHints: const [AutofillHints.password],
            onSubmitted: (_) => _submit(),
            decoration: InputDecoration(
              labelText: 'Mot de passe',
              suffixIcon: IconButton(icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined), onPressed: () => setState(() => _obscure = !_obscure)),
            ),
          ),
          const SizedBox(height: 28),
          FilledButton(
            key: const Key('login-submit'),
            onPressed: _loading ? null : _submit,
            child: _loading ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Se connecter'),
          ),
          const SizedBox(height: 16),
          OutlinedButton(onPressed: () => context.push('/inscription'), child: const Text('Créer un compte')),
          const SizedBox(height: 24),
          const Text('Un code de vérification vous sera demandé à chaque connexion.', textAlign: TextAlign.center, style: TextStyle(color: AppColors.faint, fontSize: 12)),
        ]),
      ),
    );
  }
}
