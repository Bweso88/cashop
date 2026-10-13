import 'package:cashop/core/util/money.dart';
import 'package:cashop/features/home/data/demo_data.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('les offres sont triées par frais et le total inclut les frais', () {
    final quotes = demoQuotes(10000, 'CM', PayoutMethod.cashPickup);
    expect(quotes.map((q) => q.provider), [Provider.westernUnion, Provider.moneygram]);
    for (final q in quotes) {
      expect(q.totalDebit, q.sendAmount + q.fees);
      expect(q.receiveCurrency, Currency.xaf);
    }
  });

  test('EUR → XAF utilise la parité fixe', () {
    final q = demoQuotes(10000, 'CM', PayoutMethod.mobileMoney).single;
    expect(q.receiveAmount, 65595); // 100 € × 655,957
  });

  test('un corridor non couvert ne renvoie aucune offre', () {
    expect(demoQuotes(10000, 'FR', PayoutMethod.mobileMoney), isEmpty);
  });
}
