import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/dio_client.dart';
import 'health_report.dart';

/// Source of truth for API health data (repository pattern, see docs/MOBILE.md).
class HealthRepository {
  const HealthRepository(this._dio);

  final Dio _dio;

  Future<HealthReport> fetch() async {
    final response = await _dio.get<Map<String, dynamic>>('/api/v1/health');

    return HealthReport.fromJson(response.data ?? const <String, dynamic>{});
  }
}

final healthRepositoryProvider = Provider<HealthRepository>(
  (ref) => HealthRepository(ref.watch(dioProvider)),
);

final healthProvider = FutureProvider<HealthReport>(
  (ref) => ref.watch(healthRepositoryProvider).fetch(),
);
