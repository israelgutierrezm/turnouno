import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/cuenta_models.dart';
import '../data/cuenta_repository.dart';

/// Carga y acciones de Mi cuenta. Al reservar/cancelar recarga el estado para
/// reflejar creditos y reservas actualizados.
class CuentaController extends AsyncNotifier<MiCuenta> {
  @override
  Future<MiCuenta> build() async {
    final repo = ref.watch(cuentaRepositoryProvider);
    if (repo == null) {
      return const MiCuenta(derechos: [], reservas: [], clases: []);
    }

    return repo.cargar();
  }

  Future<void> reservar(String sesionId) async {
    final repo = ref.read(cuentaRepositoryProvider);
    if (repo == null) {
      return;
    }
    await repo.reservar(sesionId);
    await _recargar();
  }

  Future<void> cancelar(String reservaId) async {
    final repo = ref.read(cuentaRepositoryProvider);
    if (repo == null) {
      return;
    }
    await repo.cancelar(reservaId);
    await _recargar();
  }

  Future<void> _recargar() async {
    final repo = ref.read(cuentaRepositoryProvider);
    if (repo == null) {
      return;
    }
    state = await AsyncValue.guard(repo.cargar);
  }
}

final cuentaProvider = AsyncNotifierProvider<CuentaController, MiCuenta>(CuentaController.new);
