<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\Models\TokenAccesoTenant;
use App\Modules\Tenancy\Models\Usuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Emisión y verificación de tokens de acceso tenant-local. El token viaja como
 * `{id}|{secreto}`; en la BD del tenant solo se guarda su hash. La resolución
 * ocurre SIEMPRE sobre la conexión `tenant` ya activa, por lo que un token de un
 * estudio no autentica en otro.
 */
class AutenticacionTenant
{
    /**
     * Crea un token para el usuario y devuelve el valor en claro (solo una vez).
     */
    public function emitir(Usuario $usuario, string $nombre = 'app'): string
    {
        $secreto = Str::random(48);

        $token = TokenAccesoTenant::create([
            'tokenable_type' => Usuario::class,
            'tokenable_id' => $usuario->getKey(),
            'name' => $nombre,
            'token' => hash('sha256', $secreto),
        ]);

        return $token->getKey().'|'.$secreto;
    }

    /**
     * Resuelve el usuario tenant-local a partir de un token en claro, o null.
     */
    public function resolver(string $valor): ?Usuario
    {
        if (! str_contains($valor, '|')) {
            return null;
        }

        [$id, $secreto] = explode('|', $valor, 2);

        if (! ctype_digit($id) || $secreto === '') {
            return null;
        }

        $token = TokenAccesoTenant::query()->find((int) $id);

        if ($token === null || ! hash_equals((string) $token->token, hash('sha256', $secreto))) {
            return null;
        }

        if ($token->expires_at instanceof Carbon && $token->expires_at->isPast()) {
            return null;
        }

        $token->forceFill(['last_used_at' => now()])->save();

        return Usuario::query()->find($token->tokenable_id);
    }

    /**
     * Revoca todos los tokens del usuario (logout global).
     */
    public function revocarTodos(Usuario $usuario): void
    {
        TokenAccesoTenant::query()
            ->where('tokenable_type', Usuario::class)
            ->where('tokenable_id', $usuario->getKey())
            ->delete();
    }
}
