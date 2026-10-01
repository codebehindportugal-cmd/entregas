<?php

namespace App\Support;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Chaves da API por utilizador (29/09/2026).
 *
 * Ate aqui a API (/api/v1 e /api/claude) so aceitava o CLAUDE_API_TOKEN do
 * .env, igual para todos e so mudavel no servidor. Agora cada administrador
 * gera a sua no perfil. As duas valem: a do .env para o que ja a usa, as do
 * perfil para o chat de cada pessoa.
 *
 * So administradores: os colaboradores sao quem faz as entregas e nao tem
 * nada a fazer na API. Uma conta inactiva ou que deixe de ser admin perde a
 * chave na hora, sem ser preciso revoga-la.
 */
class ChaveApi
{
    public const NOME = 'api';

    public const PREFIXO = 'hm_';

    public static function podeTer(User $utilizador): bool
    {
        return $utilizador->isAdmin() && (bool) $utilizador->ativo;
    }

    /** @return array{token: string, revogados: int} */
    public static function emitir(User $utilizador): array
    {
        if (! self::podeTer($utilizador)) {
            throw new InvalidArgumentException('So um administrador activo pode ter chave da API.');
        }

        $revogados = self::revogar($utilizador);
        $token = self::PREFIXO . Str::random(48);

        ApiToken::create([
            'user_id' => $utilizador->id,
            'nome' => self::NOME,
            'token_hash' => hash('sha256', $token),
        ]);

        return ['token' => $token, 'revogados' => $revogados];
    }

    public static function revogar(User $utilizador): int
    {
        return (int) ApiToken::query()
            ->where('user_id', $utilizador->id)
            ->where('nome', self::NOME)
            ->delete();
    }

    public static function actual(User $utilizador): ?ApiToken
    {
        return ApiToken::query()
            ->where('user_id', $utilizador->id)
            ->where('nome', self::NOME)
            ->latest('id')
            ->first();
    }

    /**
     * A chave apresentada vale? Aceita o CLAUDE_API_TOKEN do .env e as chaves
     * do perfil. Devolve false quando nenhuma bate.
     */
    public static function valida(?string $token): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        $doEnv = (string) config('services.claude.api_token');

        if ($doEnv !== '' && hash_equals($doEnv, $token)) {
            return true;
        }

        if (! str_starts_with($token, self::PREFIXO)) {
            return false;
        }

        try {
            $chave = ApiToken::query()
                ->with('user')
                ->where('token_hash', hash('sha256', $token))
                ->first();
        } catch (\Illuminate\Database\QueryException) {
            // Tabela ainda por migrar: so vale o token do .env.
            return false;
        }

        if (! $chave || ! $chave->user || ! self::podeTer($chave->user)) {
            return false;
        }

        // So grava o uso de minuto a minuto: um lote de 25 faturas nao precisa
        // de 25 escritas na mesma linha.
        if (! $chave->ultimo_uso_em || $chave->ultimo_uso_em->lt(now()->subMinute())) {
            $chave->forceFill(['ultimo_uso_em' => now()])->save();
        }

        return true;
    }

    /** Ha alguma forma de entrar? (Para distinguir 503 "nada configurado" de 401.) */
    public static function haAlgumaConfigurada(): bool
    {
        if (filled(config('services.claude.api_token'))) {
            return true;
        }

        try {
            return ApiToken::query()->exists();
        } catch (\Illuminate\Database\QueryException) {
            return false;
        }
    }
}
