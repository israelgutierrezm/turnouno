import 'dart:math';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/app_config.dart';

/// Shared Dio client for the app.
///
/// Every request carries an `X-Correlation-ID` header so a mobile action can be
/// traced across the API and the server logs (see docs/ARCHITECTURE.md).
final dioProvider = Provider<Dio>((ref) {
  final dio = Dio(
    BaseOptions(
      baseUrl: AppConfig.apiBaseUrl,
      headers: const {'Accept': 'application/json'},
      connectTimeout: const Duration(seconds: 10),
      receiveTimeout: const Duration(seconds: 10),
    ),
  );

  dio.interceptors.add(
    InterceptorsWrapper(
      onRequest: (options, handler) {
        options.headers['X-Correlation-ID'] = _correlationId();
        handler.next(options);
      },
    ),
  );

  return dio;
});

String _correlationId() {
  final random = Random();
  final suffix = List<String>.generate(
    8,
    (_) => random.nextInt(16).toRadixString(16),
  ).join();

  return 'cid-${DateTime.now().millisecondsSinceEpoch}-$suffix';
}
