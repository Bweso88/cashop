/// Modèles alignés sur docs/openapi.yaml (schémas Profile et OtpChallenge).
class Profile {
  const Profile({
    required this.id,
    required this.email,
    this.phone,
    this.firstName,
    this.lastName,
    this.country,
    this.kycLevel,
    this.kycStatus,
    this.mfaEnabled = false,
    this.pinSet = false,
  });

  factory Profile.fromJson(Map<String, dynamic> json) => Profile(
        id: json['id'] as String,
        email: json['email'] as String,
        phone: json['phone'] as String?,
        firstName: json['first_name'] as String?,
        lastName: json['last_name'] as String?,
        country: json['country'] as String?,
        kycLevel: json['kyc_level'] as String?,
        kycStatus: json['kyc_status'] as String?,
        mfaEnabled: json['mfa_enabled'] as bool? ?? false,
        pinSet: json['pin_set'] as bool? ?? false,
      );

  final String id;
  final String email;
  final String? phone;
  final String? firstName;
  final String? lastName;
  final String? country;
  final String? kycLevel;
  final String? kycStatus;
  final bool mfaEnabled;
  final bool pinSet;

  String get displayName {
    final name = [firstName, lastName].whereType<String>().where((p) => p.isNotEmpty).join(' ');
    return name.isEmpty ? email : name;
  }

  String get initials {
    String first(String? s) => (s == null || s.isEmpty) ? '' : s[0];
    final value = '${first(firstName)}${first(lastName)}'.toUpperCase();
    return value.isEmpty ? 'C' : value;
  }
  bool get identityVerified => kycStatus == 'VERIFIED' && kycLevel != null && kycLevel != 'LEVEL_0';
}

class OtpChallenge {
  const OtpChallenge({required this.challengeId, required this.channel, this.destinationHint});

  factory OtpChallenge.fromJson(Map<String, dynamic> json) => OtpChallenge(
        challengeId: json['challenge_id'] as String,
        channel: json['channel'] as String? ?? 'sms',
        destinationHint: json['destination_hint'] as String?,
      );

  final String challengeId;
  final String channel;
  final String? destinationHint;

  String get instructions => switch (channel) {
        'totp' => "Saisissez le code affiché dans votre application d'authentification.",
        'email' => 'Saisissez le code envoyé à ${destinationHint ?? 'votre adresse e-mail'}.',
        _ => 'Saisissez le code envoyé par SMS au ${destinationHint ?? 'numéro enregistré'}.',
      };
}
