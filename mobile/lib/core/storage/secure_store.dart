import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';

/// Stockage sécurisé : Keychain (iOS) / Keystore (Android). Le jeton n'est jamais écrit en clair.
class SecureStore {
  SecureStore([FlutterSecureStorage? storage]) : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  static const _tokenKey = 'cashop.token';
  static const _deviceKey = 'cashop.device_id';
  static const _onboardedKey = 'cashop.onboarded';

  Future<String?> readToken() => _storage.read(key: _tokenKey);
  Future<void> writeToken(String token) => _storage.write(key: _tokenKey, value: token);
  Future<void> deleteToken() => _storage.delete(key: _tokenKey);

  /// Identifiant stable de l'appareil (un jeton d'API par appareil côté serveur).
  Future<String> deviceId() async {
    final existing = await _storage.read(key: _deviceKey);
    if (existing != null) return existing;
    final id = 'mobile-${const Uuid().v4()}';
    await _storage.write(key: _deviceKey, value: id);
    return id;
  }

  Future<bool> hasOnboarded() async => await _storage.read(key: _onboardedKey) == '1';
  Future<void> markOnboarded() => _storage.write(key: _onboardedKey, value: '1');
}

final secureStoreProvider = Provider<SecureStore>((ref) => SecureStore());
