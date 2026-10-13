import 'package:flutter/material.dart';

/// Palette Cashop, identique à l'application web (web/src/app/globals.css).
abstract final class AppColors {
  static const ink = Color(0xFF0A0D14);
  static const surface = Color(0xFF111620);
  static const surface2 = Color(0xFF171D2A);
  static const surface3 = Color(0xFF1F2636);
  static const line = Color(0xFF262E40);
  static const text = Color(0xFFE9EDF5);
  static const muted = Color(0xFF8B94A8);
  static const faint = Color(0xFF5B6478);
  static const brand = Color(0xFF23D68A);
  static const brandStrong = Color(0xFF14B874);
  static const violet = Color(0xFF7C5CFF);
  static const action = Color(0xFF3D4C80);
  static const danger = Color(0xFFF0596B);
  static const warning = Color(0xFFF5B347);
  static const info = Color(0xFF5AA9FF);

  static const brandGradient = LinearGradient(colors: [brand, violet], begin: Alignment.topLeft, end: Alignment.bottomRight);
  static const walletGradients = [
    LinearGradient(colors: [Color(0xFF1A5F4A), Color(0xFF17847A), Color(0xFF2B6C8F)], begin: Alignment.topLeft, end: Alignment.bottomRight),
    LinearGradient(colors: [Color(0xFF3B2A6E), Color(0xFF5B2F6E), Color(0xFF2A3463)], begin: Alignment.topLeft, end: Alignment.bottomRight),
    LinearGradient(colors: [Color(0xFF1B2440), Color(0xFF1E3557), Color(0xFF1A2B3F)], begin: Alignment.topLeft, end: Alignment.bottomRight),
  ];
}
