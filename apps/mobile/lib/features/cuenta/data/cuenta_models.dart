/// Modelos del autoservicio del miembro (Mi cuenta).
class DerechoMiembro {
  const DerechoMiembro({required this.ilimitado, this.saldo, this.disponible});

  final bool ilimitado;
  final int? saldo;
  final int? disponible;

  /// Creditos disponibles (1 credito = 1000 unidades).
  int get creditosDisponibles => ((disponible ?? 0) / 1000).round();

  factory DerechoMiembro.desdeJson(Map<String, dynamic> j) => DerechoMiembro(
        ilimitado: (j['ilimitado'] ?? false) as bool,
        saldo: j['saldo'] as int?,
        disponible: j['disponible'] as int?,
      );
}

class ReservaMiembro {
  const ReservaMiembro({
    required this.id,
    required this.estado,
    this.oferta,
    this.iniciaEn,
    this.zonaHoraria,
  });

  final String id;
  final String estado;
  final String? oferta;
  final String? iniciaEn;
  final String? zonaHoraria;

  factory ReservaMiembro.desdeJson(Map<String, dynamic> j) => ReservaMiembro(
        id: (j['id'] ?? '') as String,
        estado: (j['estado'] ?? '') as String,
        oferta: j['oferta'] as String?,
        iniciaEn: j['inicia_en'] as String?,
        zonaHoraria: j['zona_horaria'] as String?,
      );
}

class ClaseMiembro {
  const ClaseMiembro({
    required this.id,
    this.oferta,
    this.iniciaEn,
    this.zonaHoraria,
    this.capacidad,
  });

  final String id;
  final String? oferta;
  final String? iniciaEn;
  final String? zonaHoraria;
  final int? capacidad;

  factory ClaseMiembro.desdeJson(Map<String, dynamic> j) => ClaseMiembro(
        id: (j['id'] ?? '') as String,
        oferta: j['oferta'] as String?,
        iniciaEn: j['inicia_en'] as String?,
        zonaHoraria: j['zona_horaria'] as String?,
        capacidad: j['capacidad'] as int?,
      );
}

/// Estado agregado de la pantalla Mi cuenta.
class MiCuenta {
  const MiCuenta({required this.derechos, required this.reservas, required this.clases});

  final List<DerechoMiembro> derechos;
  final List<ReservaMiembro> reservas;
  final List<ClaseMiembro> clases;
}
