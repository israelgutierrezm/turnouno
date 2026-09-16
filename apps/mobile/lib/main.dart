import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'features/auth/application/sesion_controller.dart';
import 'features/auth/presentation/login_screen.dart';
import 'features/cuenta/presentation/cuenta_screen.dart';

void main() {
  runApp(const ProviderScope(child: TurnoUnoApp()));
}

class TurnoUnoApp extends ConsumerWidget {
  const TurnoUnoApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // Sin login global: si hay una sesion tenant-local, se muestra Mi cuenta; si
    // no, el acceso por estudio.
    final autenticado = ref.watch(sesionProvider) != null;

    return MaterialApp(
      title: 'TurnoUno',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(colorSchemeSeed: Colors.indigo, useMaterial3: true),
      home: autenticado ? const CuentaScreen() : const LoginScreen(),
    );
  }
}
