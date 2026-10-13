import '../../../core/util/money.dart';

/// DONNÉES DE DÉMONSTRATION — remplacées par l'API (phases 4 à 8). Aucun montant, frais ou
/// taux n'est réel ; la couverture des providers reste NOT CONFIRMED (docs/PROVIDERS.md).

enum Provider {
  mtnMomo('MTN Mobile Money'),
  airtelMoney('Airtel Money'),
  visaDirect('Visa Direct'),
  moneygram('MoneyGram'),
  westernUnion('Western Union');

  const Provider(this.label);
  final String label;
}

enum PayoutMethod {
  mobileMoney('Mobile Money'),
  cashPickup('Retrait en agence'),
  card('Carte bancaire');

  const PayoutMethod(this.label);
  final String label;
}

enum TransferStatus {
  completed('Livré'),
  processing('En cours'),
  failed('Échoué · remboursé');

  const TransferStatus(this.label);
  final String label;
}

class Wallet {
  const Wallet(this.label, this.currency, this.available, this.masked, {this.reserved = 0});
  final String label;
  final Currency currency;
  final int available;
  final int reserved;
  final String masked;
}

class Country {
  const Country(this.code, this.name, this.currency);
  final String code;
  final String name;
  final Currency currency;
}

class TimelineEvent {
  const TimelineEvent(this.status, this.label, this.at);
  final String status;
  final String label;
  final DateTime at;
}

class Transfer {
  const Transfer({
    required this.id,
    required this.outgoing,
    required this.counterparty,
    required this.country,
    required this.provider,
    required this.method,
    required this.sendAmount,
    required this.sendCurrency,
    required this.receiveAmount,
    required this.receiveCurrency,
    required this.fees,
    required this.status,
    required this.createdAt,
    required this.reference,
    required this.timeline,
  });

  final String id;
  final bool outgoing;
  final String counterparty;
  final String country;
  final Provider provider;
  final PayoutMethod method;
  final int sendAmount;
  final Currency sendCurrency;
  final int receiveAmount;
  final Currency receiveCurrency;
  final int fees;
  final TransferStatus status;
  final DateTime createdAt;
  final String reference;
  final List<TimelineEvent> timeline;
}

class Quote {
  const Quote(this.provider, this.method, this.sendAmount, this.fees, this.receiveAmount, this.receiveCurrency, this.rate, this.delay);
  final Provider provider;
  final PayoutMethod method;
  final int sendAmount;
  final int fees;
  final int receiveAmount;
  final Currency receiveCurrency;
  final double rate;
  final String delay;

  int get totalDebit => sendAmount + fees;
}

const demoWallets = [
  Wallet('Portefeuille XAF', Currency.xaf, 485000, '•••• 2606'),
  Wallet('Portefeuille EUR', Currency.eur, 260700, '•••• 3876', reserved: 10200),
  Wallet('Portefeuille USD', Currency.usd, 54020, '•••• 9367'),
];

const countries = [
  Country('CM', 'Cameroun', Currency.xaf),
  Country('CG', 'Congo', Currency.xaf),
  Country('GA', 'Gabon', Currency.xaf),
  Country('TD', 'Tchad', Currency.xaf),
  Country('FR', 'France', Currency.eur),
  Country('GB', 'Royaume-Uni', Currency.gbp),
  Country('US', 'États-Unis', Currency.usd),
];

Country countryByCode(String code) => countries.firstWhere((c) => c.code == code, orElse: () => countries.first);

final demoTransfers = <Transfer>[
  Transfer(
    id: '0199a1c2-7d10-7a3e-9f00-000000000001', outgoing: true, counterparty: 'Aminata Kouassi', country: 'CM',
    provider: Provider.mtnMomo, method: PayoutMethod.mobileMoney, sendAmount: 10000, sendCurrency: Currency.eur,
    receiveAmount: 65595, receiveCurrency: Currency.xaf, fees: 249, status: TransferStatus.completed,
    createdAt: DateTime.utc(2026, 10, 12, 9, 41), reference: 'CSH-7K2M-91QA',
    timeline: [
      TimelineEvent('AUTHORIZED', 'Confirmé par PIN', DateTime.utc(2026, 10, 12, 9, 41)),
      TimelineEvent('SUBMITTED', "Transmis à l'opérateur", DateTime.utc(2026, 10, 12, 9, 41, 3)),
      TimelineEvent('COMPLETED', 'Crédité sur le compte Mobile Money', DateTime.utc(2026, 10, 12, 9, 41, 41)),
    ],
  ),
  Transfer(
    id: '0199a1c2-7d10-7a3e-9f00-000000000002', outgoing: true, counterparty: 'Paul Mbarga', country: 'CM',
    provider: Provider.westernUnion, method: PayoutMethod.cashPickup, sendAmount: 25000, sendCurrency: Currency.eur,
    receiveAmount: 163989, receiveCurrency: Currency.xaf, fees: 474, status: TransferStatus.processing,
    createdAt: DateTime.utc(2026, 10, 11, 16, 5), reference: 'CSH-3PZ8-44LD',
    timeline: [
      TimelineEvent('AUTHORIZED', 'Confirmé par biométrie', DateTime.utc(2026, 10, 11, 16, 5)),
      TimelineEvent('SUBMITTED', 'Transmis au réseau de retrait', DateTime.utc(2026, 10, 11, 16, 5, 5)),
      TimelineEvent('PROCESSING', 'Disponible prochainement en agence', DateTime.utc(2026, 10, 11, 16, 6)),
    ],
  ),
  Transfer(
    id: '0199a1c2-7d10-7a3e-9f00-000000000003', outgoing: false, counterparty: 'Owen Miller', country: 'GB',
    provider: Provider.visaDirect, method: PayoutMethod.card, sendAmount: 15000, sendCurrency: Currency.eur,
    receiveAmount: 15000, receiveCurrency: Currency.eur, fees: 0, status: TransferStatus.completed,
    createdAt: DateTime.utc(2026, 10, 10, 11, 20), reference: 'CSH-9VQ1-07HX',
    timeline: [TimelineEvent('COMPLETED', 'Reçu sur votre portefeuille', DateTime.utc(2026, 10, 10, 11, 20))],
  ),
  Transfer(
    id: '0199a1c2-7d10-7a3e-9f00-000000000004', outgoing: true, counterparty: 'Grâce Okemba', country: 'CG',
    provider: Provider.airtelMoney, method: PayoutMethod.mobileMoney, sendAmount: 5000, sendCurrency: Currency.eur,
    receiveAmount: 32797, receiveCurrency: Currency.xaf, fees: 174, status: TransferStatus.failed,
    createdAt: DateTime.utc(2026, 10, 8, 8, 12), reference: 'CSH-1BX4-62NW',
    timeline: [
      TimelineEvent('SUBMITTED', "Transmis à l'opérateur", DateTime.utc(2026, 10, 8, 8, 12, 2)),
      TimelineEvent('FAILED', 'Numéro bénéficiaire introuvable', DateTime.utc(2026, 10, 8, 8, 12, 30)),
      TimelineEvent('REVERSED', 'Fonds restitués sur votre portefeuille', DateTime.utc(2026, 10, 8, 8, 12, 31)),
    ],
  ),
];

/// Corridors simulés : mêmes fixtures que les Mock providers du backend.
const _corridors = [
  (Provider.mtnMomo, 'CM', PayoutMethod.mobileMoney, 150, 99, 'Quelques minutes'),
  (Provider.airtelMoney, 'CG', PayoutMethod.mobileMoney, 150, 99, 'Quelques minutes'),
  (Provider.moneygram, 'CM', PayoutMethod.cashPickup, 150, 299, 'Sous 1 heure'),
  (Provider.moneygram, 'GA', PayoutMethod.cashPickup, 150, 299, 'Sous 1 heure'),
  (Provider.westernUnion, 'CM', PayoutMethod.cashPickup, 175, 249, 'Sous 1 heure'),
  (Provider.westernUnion, 'TD', PayoutMethod.cashPickup, 175, 249, 'Sous 1 heure'),
  (Provider.visaDirect, 'FR', PayoutMethod.card, 100, 50, "Jusqu'à 30 minutes"),
  (Provider.visaDirect, 'GB', PayoutMethod.card, 100, 50, "Jusqu'à 30 minutes"),
  (Provider.visaDirect, 'US', PayoutMethod.card, 100, 50, "Jusqu'à 30 minutes"),
];

/// Taux simulés EUR → devise. EUR/XAF est la parité fixe officielle ; les autres sont illustratifs.
const _rates = {Currency.eur: 1.0, Currency.xaf: 655.957, Currency.usd: 1.08, Currency.gbp: 0.85};

List<PayoutMethod> methodsFor(String country) => _corridors.where((c) => c.$2 == country).map((c) => c.$3).toSet().toList();

/// Montant en centimes d'euro -> offres simulées, la moins chère en premier.
List<Quote> demoQuotes(int sendCents, String country, PayoutMethod method) {
  final target = countryByCode(country).currency;
  final rate = _rates[target]!;
  return _corridors.where((c) => c.$2 == country && c.$3 == method).map((c) {
    final fees = c.$5 + (sendCents * c.$4 / 10000).round();
    final receiveMajor = sendCents / 100 * rate;
    final receive = target.minorUnits == 0 ? receiveMajor.floor() : (receiveMajor * 100).floor();
    return Quote(c.$1, method, sendCents, fees, receive, target, rate, c.$6);
  }).toList()
    ..sort((a, b) => a.fees.compareTo(b.fees));
}

/// Solde total converti en centimes d'euro.
int demoTotalInEur() => demoWallets.fold(0, (sum, w) {
      final major = w.available / (w.currency.minorUnits == 0 ? 1 : 100);
      return sum + (major / _rates[w.currency]! * 100).round();
    });
