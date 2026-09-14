/// Snapshot of the API health endpoint (`GET /api/v1/health`).
class HealthReport {
  const HealthReport({
    required this.status,
    required this.app,
    required this.environment,
    required this.version,
  });

  factory HealthReport.fromJson(Map<String, dynamic> json) {
    return HealthReport(
      status: json['status'] as String? ?? 'unknown',
      app: json['app'] as String? ?? '',
      environment: json['environment'] as String? ?? '',
      version: json['version'] as String? ?? '',
    );
  }

  final String status;
  final String app;
  final String environment;
  final String version;
}
