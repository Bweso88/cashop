import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';

/// Saisie d'un code (PIN, OTP) au pavé numérique, sans clavier système.
class PinPad extends StatefulWidget {
  const PinPad({super.key, this.length = 6, required this.onCompleted, this.obscure = true});

  final int length;
  final bool obscure;
  final ValueChanged<String> onCompleted;

  @override
  State<PinPad> createState() => PinPadState();
}

class PinPadState extends State<PinPad> {
  String _value = '';

  void clear() => setState(() => _value = '');

  void _press(String digit) {
    if (_value.length >= widget.length) return;
    setState(() => _value += digit);
    if (_value.length == widget.length) widget.onCompleted(_value);
  }

  void _erase() {
    if (_value.isNotEmpty) setState(() => _value = _value.substring(0, _value.length - 1));
  }

  @override
  Widget build(BuildContext context) {
    return Column(mainAxisSize: MainAxisSize.min, children: [
      Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: List.generate(widget.length, (i) {
          final filled = i < _value.length;
          return Container(
            key: ValueKey('pin-dot-$i'),
            margin: const EdgeInsets.symmetric(horizontal: 6),
            width: widget.obscure ? 16 : 40,
            height: widget.obscure ? 16 : 52,
            alignment: Alignment.center,
            decoration: widget.obscure
                ? BoxDecoration(shape: BoxShape.circle, color: filled ? AppColors.brand : AppColors.surface3)
                : BoxDecoration(color: AppColors.surface2, borderRadius: BorderRadius.circular(12), border: Border.all(color: filled ? AppColors.brand : AppColors.line)),
            child: widget.obscure || !filled ? null : Text(_value[i], style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w600)),
          );
        }),
      ),
      const SizedBox(height: 28),
      for (final row in const [['1', '2', '3'], ['4', '5', '6'], ['7', '8', '9'], ['', '0', '⌫']])
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: row.map((key) {
            if (key.isEmpty) return const SizedBox(width: 88, height: 64);
            return SizedBox(
              width: 88,
              height: 64,
              child: TextButton(
                key: ValueKey('pin-key-$key'),
                onPressed: key == '⌫' ? _erase : () => _press(key),
                style: TextButton.styleFrom(foregroundColor: AppColors.text, shape: const CircleBorder()),
                child: Text(key, style: const TextStyle(fontSize: 26, fontWeight: FontWeight.w500)),
              ),
            );
          }).toList(),
        ),
    ]);
  }
}
