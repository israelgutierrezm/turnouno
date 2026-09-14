import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'features/health/presentation/health_screen.dart';

void main() {
  runApp(const ProviderScope(child: TurnoUnoApp()));
}

class TurnoUnoApp extends StatelessWidget {
  const TurnoUnoApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'TurnoUno',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(colorSchemeSeed: Colors.indigo, useMaterial3: true),
      home: const HealthScreen(),
    );
  }
}
