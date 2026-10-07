<?php

namespace App\Models;

use App\Services\ClientesB2c;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * As notas fixas de um cliente B2C, pelo telefone normalizado. Aparecem ao
 * criar encomendas (backoffice e API) e na preparacao dos cabazes.
 */
class NotaCliente extends Model
{
    protected $table = 'notas_clientes';

    protected $fillable = ['telefone', 'nome', 'notas'];

    public static function doTelefone(?string $telefone): ?self
    {
        $normalizado = ClientesB2c::normalizarTelefone($telefone);

        return $normalizado === null ? null : self::where('telefone', $normalizado)->first();
    }

    public static function textoDo(?string $telefone): ?string
    {
        return self::doTelefone($telefone)?->notas;
    }

    /**
     * Notas de varios telefones de uma vez (para listas), indexadas pelo
     * telefone normalizado.
     *
     * @param  iterable<int, string|null>  $telefones
     * @return Collection<string, string>
     */
    public static function porTelefones(iterable $telefones): Collection
    {
        $normalizados = collect($telefones)
            ->map(fn (?string $telefone): ?string => ClientesB2c::normalizarTelefone($telefone))
            ->filter()
            ->unique()
            ->values();

        if ($normalizados->isEmpty()) {
            return collect();
        }

        return self::whereIn('telefone', $normalizados)->pluck('notas', 'telefone');
    }
}
