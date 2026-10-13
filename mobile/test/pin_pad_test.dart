import 'package:cashop/shared/widgets/pin_pad.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('le code est transmis une fois les 6 chiffres saisis, la correction fonctionne', (tester) async {
    String? result;
    await tester.pumpWidget(MaterialApp(home: Scaffold(body: SingleChildScrollView(child: PinPad(onCompleted: (v) => result = v)))));

    for (final d in ['4', '8', '9']) {
      await tester.tap(find.byKey(ValueKey('pin-key-$d')));
    }
    await tester.tap(find.byKey(const ValueKey('pin-key-⌫')));
    for (final d in ['2', '9', '1', '5']) {
      await tester.tap(find.byKey(ValueKey('pin-key-$d')));
    }
    await tester.pump();

    expect(result, '482915');
  });
}
