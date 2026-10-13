import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/theme/app_colors.dart';
import '../application/session_controller.dart';

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _form = GlobalKey<FormState>();
  final _first = TextEditingController();
  final _last = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController(text: '+237');
  final _password = TextEditingController();
  String _country = 'CM';
  bool _loading = false;
  ApiException? _error;

  static const _countries = {'CM': 'Cameroun', 'CG': 'Congo', 'GA': 'Gabon', 'TD': 'Tchad', 'CD': 'RD Congo', 'FR': 'France', 'BE': 'Belgique', 'GB': 'Royaume-Uni', 'US': 'États-Unis'};

  @override
  void dispose() {
    for (final c in [_first, _last, _email, _phone, _password]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await ref.read(sessionProvider.notifier).register(
            email: _email.text.trim(),
            phone: _phone.text.replaceAll(' ', ''),
            password: _password.text,
            firstName: _first.text.trim(),
            lastName: _last.text.trim(),
            country: _country,
          );
    } on ApiException catch (e) {
      setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    String? required(String? v) => (v == null || v.trim().isEmpty) ? 'Champ requis' : null;

    return Scaffold(
      appBar: AppBar(title: const Text('Créer un compte')),
      body: Form(
        key: _form,
        child: ListView(padding: const EdgeInsets.all(24), children: [
          const Text('Quelques informations, puis un code par SMS.', style: TextStyle(color: AppColors.muted)),
          const SizedBox(height: 24),
          if (_error != null) ...[
            Text(_error!.message, style: const TextStyle(color: AppColors.danger)),
            const SizedBox(height: 12),
          ],
          Row(children: [
            Expanded(child: TextFormField(controller: _first, validator: required, decoration: InputDecoration(labelText: 'Prénom', errorText: _error?.fieldErrors['first_name']))),
            const SizedBox(width: 12),
            Expanded(child: TextFormField(controller: _last, validator: required, decoration: InputDecoration(labelText: 'Nom', errorText: _error?.fieldErrors['last_name']))),
          ]),
          const SizedBox(height: 16),
          TextFormField(controller: _email, validator: required, keyboardType: TextInputType.emailAddress, decoration: InputDecoration(labelText: 'E-mail', errorText: _error?.fieldErrors['email'])),
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            initialValue: _country,
            decoration: const InputDecoration(labelText: 'Pays de résidence'),
            items: _countries.entries.map((e) => DropdownMenuItem(value: e.key, child: Text(e.value))).toList(),
            onChanged: (v) => setState(() => _country = v ?? _country),
          ),
          const SizedBox(height: 16),
          TextFormField(controller: _phone, validator: required, keyboardType: TextInputType.phone, decoration: InputDecoration(labelText: 'Téléphone (format international)', errorText: _error?.fieldErrors['phone'])),
          const SizedBox(height: 16),
          TextFormField(
            controller: _password,
            obscureText: true,
            validator: (v) => (v == null || v.length < 10) ? '10 caractères minimum' : null,
            decoration: InputDecoration(labelText: 'Mot de passe', helperText: '10 caractères minimum, avec lettres et chiffres.', errorText: _error?.fieldErrors['password']),
          ),
          const SizedBox(height: 28),
          FilledButton(onPressed: _loading ? null : _submit, child: Text(_loading ? 'Création…' : 'Créer mon compte')),
        ]),
      ),
    );
  }
}
