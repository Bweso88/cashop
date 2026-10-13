import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../shared/widgets/status_badge.dart';

/// Parcours KYC (maquette fonctionnelle) : pièce d'identité, selfie, vérification.
/// La capture caméra et l'envoi à POST /api/v1/kyc/documents viendront avec le prestataire KYC.
class KycScreen extends StatefulWidget {
  const KycScreen({super.key});

  @override
  State<KycScreen> createState() => _KycScreenState();
}

class _KycScreenState extends State<KycScreen> {
  int _step = 0;

  @override
  Widget build(BuildContext context) {
    final (title, body, icon, button) = switch (_step) {
      0 => ("Scannez votre pièce d'identité", 'Placez la carte dans le cadre, bien éclairée et sans reflet.', Icons.badge_outlined, 'Prendre la photo'),
      1 => ("Vérifions que c'est bien vous", 'Un selfie de face, sans lunettes ni chapeau.', Icons.face_retouching_natural, "M'identifier"),
      _ => ('Documents reçus', 'Votre identité est en cours de vérification. Vous serez notifié dès sa validation.', Icons.verified_outlined, 'Terminer'),
    };

    return Scaffold(
      appBar: AppBar(title: const Text("Vérification d'identité"), actions: const [Padding(padding: EdgeInsets.only(right: 16), child: DemoBanner())]),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(children: [
            LinearProgressIndicator(value: (_step + 1) / 3, minHeight: 6, borderRadius: BorderRadius.circular(6), backgroundColor: AppColors.surface3),
            const Spacer(),
            Container(
              width: _step == 0 ? 280 : 180,
              height: 180,
              decoration: BoxDecoration(
                color: AppColors.surface2,
                shape: _step == 1 ? BoxShape.circle : BoxShape.rectangle,
                borderRadius: _step == 1 ? null : BorderRadius.circular(20),
                border: Border.all(color: AppColors.brand, width: 2),
              ),
              child: Icon(icon, size: 72, color: AppColors.brand),
            ),
            const SizedBox(height: 32),
            Text(title, textAlign: TextAlign.center, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w600)),
            const SizedBox(height: 10),
            Text(body, textAlign: TextAlign.center, style: const TextStyle(color: AppColors.muted, fontSize: 15)),
            const Spacer(),
            FilledButton(onPressed: () => _step < 2 ? setState(() => _step++) : Navigator.of(context).maybePop(), child: Text(button)),
          ]),
        ),
      ),
    );
  }
}
