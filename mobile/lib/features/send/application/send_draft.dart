import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../home/data/demo_data.dart';

/// Brouillon de transfert, partagé par les écrans du parcours d'envoi.
class SendDraft {
  const SendDraft({this.amountCents = 10000, this.country = 'CM', this.method = PayoutMethod.mobileMoney, this.quote, this.recipientName = '', this.recipientDetail = ''});

  final int amountCents;
  final String country;
  final PayoutMethod method;
  final Quote? quote;
  final String recipientName;
  final String recipientDetail;

  SendDraft copyWith({int? amountCents, String? country, PayoutMethod? method, Quote? quote, String? recipientName, String? recipientDetail}) => SendDraft(
        amountCents: amountCents ?? this.amountCents,
        country: country ?? this.country,
        method: method ?? this.method,
        quote: quote ?? this.quote,
        recipientName: recipientName ?? this.recipientName,
        recipientDetail: recipientDetail ?? this.recipientDetail,
      );
}

class SendDraftController extends Notifier<SendDraft> {
  @override
  SendDraft build() => const SendDraft();

  void update(SendDraft Function(SendDraft) change) => state = change(state);

  /// Présélection depuis les raccourcis de l'accueil (Mobile Money, International, Retrait cash).
  void preselect(PayoutMethod method) {
    final country = countries.firstWhere((c) => methodsFor(c.code).contains(method), orElse: () => countries.first);
    state = SendDraft(amountCents: state.amountCents, country: country.code, method: method);
  }

  void reset() => state = const SendDraft();
}

final sendDraftProvider = NotifierProvider<SendDraftController, SendDraft>(SendDraftController.new);
