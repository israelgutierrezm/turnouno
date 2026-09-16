import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../auth/application/sesion_controller.dart';
import '../application/cuenta_controller.dart';
import '../data/cuenta_models.dart';

/// Autoservicio del miembro: creditos, reservas y proximas clases.
class CuentaScreen extends ConsumerWidget {
  const CuentaScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final sesion = ref.watch(sesionProvider);
    final estado = ref.watch(cuentaProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(sesion?.nombre ?? 'Mi cuenta'),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            tooltip: 'Cerrar sesion',
            onPressed: () => ref.read(sesionProvider.notifier).cerrar(),
          ),
        ],
      ),
      body: estado.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text('No se pudo cargar: $e')),
        data: (cuenta) => RefreshIndicator(
          onRefresh: () => ref.refresh(cuentaProvider.future),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              _seccion(context, 'Mis creditos'),
              _creditos(context, cuenta.derechos),
              const SizedBox(height: 20),
              _seccion(context, 'Mis reservas'),
              if (cuenta.reservas.isEmpty)
                const _Vacio('No tienes reservas proximas.')
              else
                ...cuenta.reservas.map((r) => _reserva(context, ref, r)),
              const SizedBox(height: 20),
              _seccion(context, 'Proximas clases'),
              if (cuenta.clases.isEmpty)
                const _Vacio('No hay clases programadas.')
              else
                ...cuenta.clases.map((c) => _clase(context, ref, c)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _seccion(BuildContext context, String titulo) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Text(titulo, style: Theme.of(context).textTheme.titleMedium),
      );

  Widget _creditos(BuildContext context, List<DerechoMiembro> derechos) {
    if (derechos.isEmpty) {
      return const _Vacio('Aun no tienes creditos.');
    }
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: derechos
              .map((d) => Text(
                    d.ilimitado ? 'Ilimitado' : 'Disponible: ${d.creditosDisponibles} creditos',
                    style: Theme.of(context).textTheme.titleLarge,
                  ))
              .toList(),
        ),
      ),
    );
  }

  Widget _reserva(BuildContext context, WidgetRef ref, ReservaMiembro r) => Card(
        child: ListTile(
          title: Text(r.oferta ?? '—'),
          subtitle: Text('${_hora(r.iniciaEn)} · ${r.estado}'),
          trailing: TextButton(
            onPressed: () => _accion(context, () => ref.read(cuentaProvider.notifier).cancelar(r.id)),
            child: const Text('Cancelar'),
          ),
        ),
      );

  Widget _clase(BuildContext context, WidgetRef ref, ClaseMiembro c) => Card(
        child: ListTile(
          title: Text(c.oferta ?? '—'),
          subtitle: Text(_hora(c.iniciaEn)),
          trailing: FilledButton(
            onPressed: () => _accion(context, () => ref.read(cuentaProvider.notifier).reservar(c.id)),
            child: const Text('Reservar'),
          ),
        ),
      );

  Future<void> _accion(BuildContext context, Future<void> Function() accion) async {
    final messenger = ScaffoldMessenger.of(context);
    try {
      await accion();
    } on DioException catch (e) {
      final data = e.response?.data;
      final msg = (data is Map && data['message'] is String)
          ? data['message'] as String
          : 'No se pudo completar la accion.';
      messenger.showSnackBar(SnackBar(content: Text(msg)));
    }
  }

  /// Hora local del dispositivo (formato corto). La zona del estudio se refinara
  /// con `intl`/`timezone` mas adelante.
  static String _hora(String? iso) {
    if (iso == null) {
      return '—';
    }
    final f = DateTime.tryParse(iso)?.toLocal();
    if (f == null) {
      return iso;
    }
    String dos(int n) => n.toString().padLeft(2, '0');

    return '${dos(f.day)}/${dos(f.month)} ${dos(f.hour)}:${dos(f.minute)}';
  }
}

class _Vacio extends StatelessWidget {
  const _Vacio(this.texto);

  final String texto;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Text(texto, style: Theme.of(context).textTheme.bodyMedium),
      );
}
