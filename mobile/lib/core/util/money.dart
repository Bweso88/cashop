import 'package:intl/intl.dart';

/// Montants en unités mineures (entiers), comme dans l'API. Aucun calcul en virgule flottante :
/// la conversion décimale ne sert qu'à l'affichage.
enum Currency {
  xaf('XAF', 0, 'FCFA'),
  eur('EUR', 2, '€'),
  usd('USD', 2, r'$US'),
  gbp('GBP', 2, '£');

  const Currency(this.code, this.minorUnits, this.symbol);
  final String code;
  final int minorUnits;
  final String symbol;

  static Currency fromCode(String code) => values.firstWhere((c) => c.code == code);
}

String formatMoney(int amountMinor, Currency currency, {bool signed = false}) {
  final format = NumberFormat.currency(locale: 'fr_FR', symbol: currency.symbol, decimalDigits: currency.minorUnits);
  final factor = _pow10(currency.minorUnits);
  final formatted = format.format(amountMinor.abs() / factor);
  if (signed) return '${amountMinor < 0 ? '−' : '+'}$formatted';
  return amountMinor < 0 ? '−$formatted' : formatted;
}

/// "1 250,5" + EUR -> 125050 ; null si la saisie est invalide ou trop précise.
int? parseAmount(String input, Currency currency) {
  final normalized = input.replaceAll(RegExp(r'\s'), '').replaceAll(',', '.');
  if (!RegExp(r'^\d+(\.\d+)?$').hasMatch(normalized)) return null;
  final parts = normalized.split('.');
  final fraction = parts.length > 1 ? parts[1] : '';
  if (fraction.length > currency.minorUnits) return null;
  return int.parse(parts[0]) * _pow10(currency.minorUnits) + (fraction.isEmpty ? 0 : int.parse(fraction.padRight(currency.minorUnits, '0')));
}

int _pow10(int n) {
  var result = 1;
  for (var i = 0; i < n; i++) {
    result *= 10;
  }
  return result;
}
