import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../storage/secure_store.dart';

/// URL de l'API, fournie au build : flutter run --dart-define=CASHOP_API_URL=https://…/api/v1
/// (10.0.2.2 = machine hôte depuis l'émulateur Android).
const apiBaseUrl = String.fromEnvironment('CASHOP_API_URL', defaultValue: 'http://10.0.2.2:8000/api/v1');

/// Erreur au format Cashop : { code, message, errors, correlation_id }.
class ApiException implements Exception {
  ApiException(this.status, this.code, this.message, {this.fieldErrors = const {}});

  final int status;
  final String code;
  final String message;
  final Map<String, String> fieldErrors;

  @override
  String toString() => 'ApiException($status, $code): $message';
}

/// Client HTTP : ajoute le jeton, un X-Correlation-Id par requête, et convertit les erreurs.
Dio createDio(SecureStore store, {String baseUrl = apiBaseUrl}) {
  final dio = Dio(BaseOptions(
    baseUrl: baseUrl,
    connectTimeout: const Duration(seconds: 10),
    receiveTimeout: const Duration(seconds: 20),
    headers: {'Accept': 'application/json'},
  ));

  dio.interceptors.add(InterceptorsWrapper(
    onRequest: (options, handler) async {
      options.headers['X-Correlation-Id'] = const Uuid().v7();
      final token = await store.readToken();
      if (token != null) options.headers['Authorization'] = 'Bearer $token';
      handler.next(options);
    },
    onError: (error, handler) {
      handler.reject(DioException(requestOptions: error.requestOptions, error: toApiException(error), response: error.response, type: error.type));
    },
  ));

  return dio;
}

ApiException toApiException(DioException error) {
  final data = error.response?.data;
  if (data is Map && data['code'] is String) {
    final errors = <String, String>{};
    final raw = data['errors'];
    if (raw is Map) {
      raw.forEach((key, value) {
        if (value is List && value.isNotEmpty) errors['$key'] = '${value.first}';
      });
    }
    return ApiException(error.response?.statusCode ?? 0, data['code'] as String, '${data['message']}', fieldErrors: errors);
  }
  return ApiException(0, 'API_UNREACHABLE', 'Le service Cashop est momentanément indisponible. Vérifiez votre connexion.');
}

/// Extrait l'ApiException d'une erreur Dio (ou la renvoie telle quelle).
ApiException asApiException(Object error) => switch (error) {
      ApiException e => e,
      DioException(error: final ApiException e) => e,
      DioException e => toApiException(e),
      _ => ApiException(0, 'UNKNOWN', 'Une erreur inattendue est survenue.'),
    };

final dioProvider = Provider<Dio>((ref) => createDio(ref.watch(secureStoreProvider)));
