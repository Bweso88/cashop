import 'package:cashop/core/util/money.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';

void main() {
  setUpAll(() => initializeDateFormatting('fr_FR'));

  test('formate selon les unités mineures de chaque devise', () {
    expect(formatMoney(485000, Currency.xaf), '485 000 FCFA');
    expect(formatMoney(260700, Currency.eur), '2 607,00 €');
    expect(formatMoney(-10000, Currency.eur, signed: true), '−100,00 €');
    expect(formatMoney(15000, Currency.eur, signed: true), '+150,00 €');
  });

  test('convertit une saisie en unités mineures sans flottant', () {
    expect(parseAmount('100', Currency.eur), 10000);
    expect(parseAmount('1 250,5', Currency.eur), 125050);
    expect(parseAmount('0,01', Currency.eur), 1);
    expect(parseAmount('5000', Currency.xaf), 5000);
  });

  test('refuse les saisies invalides ou trop précises', () {
    expect(parseAmount('abc', Currency.eur), isNull);
    expect(parseAmount('10,005', Currency.eur), isNull);
    expect(parseAmount('10,5', Currency.xaf), isNull);
    expect(parseAmount('-5', Currency.eur), isNull);
  });
}
