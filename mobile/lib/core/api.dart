import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class ApiFailure implements Exception {
  final String message;
  final int? status;
  const ApiFailure(this.message, [this.status]);
  @override
  String toString() => message;
}

class CareApi {
  static const baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8088/api/v1',
  );
  final Dio dio;
  final FlutterSecureStorage storage;
  String? token;
  void Function()? onUnauthorized;
  CareApi({Dio? client, FlutterSecureStorage? secureStorage})
    : dio =
          client ??
          Dio(
            BaseOptions(
              baseUrl: baseUrl,
              connectTimeout: const Duration(seconds: 12),
              receiveTimeout: const Duration(seconds: 15),
              headers: {'Accept': 'application/json'},
            ),
          ),
      storage = secureStorage ?? const FlutterSecureStorage() {
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          if (token != null) options.headers['Authorization'] = 'Bearer $token';
          handler.next(options);
        },
        onError: (error, handler) {
          if (error.response?.statusCode == 401 && token != null) {
            token = null;
            storage.delete(key: 'carecircle_token');
            onUnauthorized?.call();
          }
          handler.next(error);
        },
      ),
    );
  }
  Future<dynamic> call(
    String path, {
    String method = 'GET',
    Map<String, dynamic>? data,
  }) async {
    try {
      final response = await dio.request<dynamic>(
        path,
        data: data,
        options: Options(method: method),
      );
      return response.data;
    } on DioException catch (e) {
      final body = e.response?.data;
      String message;
      if (body is Map && body['errors'] is Map) {
        final values = (body['errors'] as Map).values;
        message = values.expand((v) => v is List ? v : [v]).join('\n');
      } else if (e.response?.statusCode == 429) {
        message = 'Too many attempts. Please wait a minute and try again.';
      } else if (body is Map &&
          body['message'] != null &&
          e.response!.statusCode! < 500) {
        message = body['message'].toString();
      } else if (e.response?.statusCode != null) {
        message =
            'The community service is temporarily unavailable. Please retry.';
      } else {
        message =
            'Could not connect. Check your connection and try again. Your action has not been confirmed.';
      }
      throw ApiFailure(message, e.response?.statusCode);
    }
  }

  Future<void> saveToken(String value) async {
    token = value;
    await storage.write(key: 'carecircle_token', value: value);
  }

  Future<void> clear() async {
    token = null;
    await storage.delete(key: 'carecircle_token');
  }
}
