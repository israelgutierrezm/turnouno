import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/dio_client.dart';
import '../../auth/application/sesion_controller.dart';
import 'cuenta_models.dart';

/// Acceso a los datos del autoservicio del miembro (`/app/{slug}/mi/*`), sobre el
/// estudio de la sesion activa.
class CuentaRepository {
  CuentaRepository(this._dio, this._slug);

  final Dio _dio;
  final String _slug;

  String get _base => '/api/v1/app/$_slug';

  Future<MiCuenta> cargar() async {
    final perfil = await _dio.get<Map<String, dynamic>>('$_base/mi/perfil');
    final agenda = await _dio.get<Map<String, dynamic>>('$_base/mi/agenda');

    final data = (perfil.data?['data'] ?? {}) as Map<String, dynamic>;
    final derechos = ((data['derechos'] ?? []) as List)
        .map((e) => DerechoMiembro.desdeJson(e as Map<String, dynamic>))
        .toList();
    final reservas = ((data['reservas'] ?? []) as List)
        .map((e) => ReservaMiembro.desdeJson(e as Map<String, dynamic>))
        .toList();
    final clases = ((agenda.data?['data'] ?? []) as List)
        .map((e) => ClaseMiembro.desdeJson(e as Map<String, dynamic>))
        .toList();

    return MiCuenta(derechos: derechos, reservas: reservas, clases: clases);
  }

  Future<void> reservar(String sesionId) =>
      _dio.post<Map<String, dynamic>>('$_base/mi/reservas', data: {'sesion_id': sesionId});

  Future<void> cancelar(String reservaId) =>
      _dio.post<Map<String, dynamic>>('$_base/mi/reservas/$reservaId/cancelar');
}

/// Repositorio ligado a la sesion activa (null si no hay sesion).
final cuentaRepositoryProvider = Provider<CuentaRepository?>((ref) {
  final sesion = ref.watch(sesionProvider);
  if (sesion == null) {
    return null;
  }

  return CuentaRepository(ref.watch(dioProvider), sesion.slug);
});
