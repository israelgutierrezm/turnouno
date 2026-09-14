import 'package:flutter_test/flutter_test.dart';
import 'package:turnouno_mobile/features/health/data/health_report.dart';

void main() {
  group('HealthReport.fromJson', () {
    test('parses a complete payload', () {
      final report = HealthReport.fromJson(const {
        'status': 'ok',
        'app': 'TurnoUno',
        'environment': 'testing',
        'version': '0.1.0',
      });

      expect(report.status, 'ok');
      expect(report.app, 'TurnoUno');
      expect(report.environment, 'testing');
      expect(report.version, '0.1.0');
    });

    test('falls back to safe defaults on missing keys', () {
      final report = HealthReport.fromJson(const {});

      expect(report.status, 'unknown');
      expect(report.app, '');
      expect(report.environment, '');
      expect(report.version, '');
    });
  });
}
