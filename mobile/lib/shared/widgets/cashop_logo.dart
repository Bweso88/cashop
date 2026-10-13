import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';

/// Monogramme Cashop : un « C » ouvert et un point (la pièce qui circule).
class CashopLogo extends StatelessWidget {
  const CashopLogo({super.key, this.size = 36, this.showName = true});

  final double size;
  final bool showName;

  @override
  Widget build(BuildContext context) {
    final mark = Container(
      width: size,
      height: size,
      decoration: BoxDecoration(gradient: AppColors.brandGradient, borderRadius: BorderRadius.circular(size * 0.3)),
      child: CustomPaint(painter: _MarkPainter()),
    );
    if (!showName) return mark;
    return Row(mainAxisSize: MainAxisSize.min, children: [
      mark,
      SizedBox(width: size * 0.3),
      Text('Cashop', style: TextStyle(fontSize: size * 0.55, fontWeight: FontWeight.w600, letterSpacing: -0.3)),
    ]);
  }
}

class _MarkPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final s = size.width / 40;
    final stroke = Paint()
      ..color = AppColors.ink
      ..style = PaintingStyle.stroke
      ..strokeWidth = 4.2 * s
      ..strokeCap = StrokeCap.round;
    // Arc du « C » : centre (20,20), rayon 9.5, ouverture à droite.
    canvas.drawArc(Rect.fromCircle(center: Offset(20 * s, 20 * s), radius: 9.5 * s), 0.62, 5.04, false, stroke);
    canvas.drawCircle(Offset(29.5 * s, 20 * s), 2.6 * s, Paint()..color = AppColors.ink);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
