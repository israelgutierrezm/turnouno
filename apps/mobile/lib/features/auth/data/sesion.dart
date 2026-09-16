/// Sesion tenant-local activa: el estudio (slug) y el usuario autenticado.
class Sesion {
  const Sesion({
    required this.slug,
    required this.bearer,
    required this.nombre,
    required this.rol,
  });

  final String slug;
  final String bearer;
  final String nombre;
  final String rol;

  factory Sesion.desdeJson(String slug, String bearer, Map<String, dynamic> usuario) {
    return Sesion(
      slug: slug,
      bearer: bearer,
      nombre: (usuario['nombre'] ?? '') as String,
      rol: (usuario['rol'] ?? '') as String,
    );
  }
}
