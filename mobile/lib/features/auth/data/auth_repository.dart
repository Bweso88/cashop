import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/storage/secure_store.dart';
import 'auth_models.dart';

/// Accès à l'API d'authentification (/api/v1/auth/*, /profile).
class AuthRepository {
  AuthRepository(this._dio, this._store);

  final Dio _dio;
  final SecureStore _store;

  Future<Map<String, dynamic>> _device() async => {
        'device_id': await _store.deviceId(),
        'platform': kIsWeb ? 'web' : (defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android'),
        'name': 'Cashop mobile',
      };

  Future<OtpChallenge> login(String login, String password) async {
    final res = await _call(() async => _dio.post<Map<String, dynamic>>('/auth/login', data: {'login': login, 'password': password, 'device': await _device()}));
    return OtpChallenge.fromJson(res.data!);
  }

  Future<OtpChallenge> register({
    required String email,
    required String phone,
    required String password,
    required String firstName,
    required String lastName,
    required String country,
  }) async {
    final res = await _call(() async => _dio.post<Map<String, dynamic>>('/auth/register', data: {
          'email': email,
          'phone': phone,
          'password': password,
          'first_name': firstName,
          'last_name': lastName,
          'country': country,
          'device': await _device(),
        }));
    return OtpChallenge.fromJson(res.data!);
  }

  /// Valide le code et enregistre le jeton dans le stockage sécurisé.
  Future<Profile> verifyOtp(String challengeId, String code) async {
    final res = await _call(() => _dio.post<Map<String, dynamic>>('/auth/verify-otp', data: {'challenge_id': challengeId, 'code': code}));
    await _store.writeToken(res.data!['token'] as String);
    return Profile.fromJson(res.data!['user'] as Map<String, dynamic>);
  }

  Future<Profile> profile() async {
    final res = await _call(() => _dio.get<Map<String, dynamic>>('/profile'));
    return Profile.fromJson(res.data!);
  }

  Future<void> setPin(String pin, {String? currentPin}) =>
      _call(() => _dio.put<void>('/auth/pin', data: {'pin': pin, 'current_pin': ?currentPin}));

  Future<void> logout() async {
    try {
      await _dio.post<void>('/auth/logout');
    } catch (_) {
      // Jeton déjà expiré : la session locale est effacée quand même.
    }
    await _store.deleteToken();
  }

  Future<bool> hasToken() async => await _store.readToken() != null;

  Future<T> _call<T>(Future<T> Function() request) async {
    try {
      return await request();
    } catch (error) {
      throw asApiException(error);
    }
  }
}

final authRepositoryProvider = Provider<AuthRepository>((ref) => AuthRepository(ref.watch(dioProvider), ref.watch(secureStoreProvider)));
