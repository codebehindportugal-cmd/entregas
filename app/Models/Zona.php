<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Zona extends Model
{
    public const DIAS = [
        1 => 'Segunda',
        2 => 'Terca',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
        6 => 'Sabado',
    ];

    protected $fillable = ['nome', 'cor', 'descricao', 'codigos_postais', 'ordem', 'ativo'];

    protected $casts = [
        'ativo' => 'boolean',
        'ordem' => 'integer',
    ];

    /**
     * A zona que cobre este codigo postal (pelos 4 primeiros digitos), entre
     * as zonas dadas. Serve so para sugerir: quem decide e o admin.
     */
    public static function sugeridaPara(?string $codigoPostal, Collection $zonas): ?self
    {
        if (! preg_match('/(\d{4})/', (string) $codigoPostal, $m)) {
            return null;
        }

        return $zonas->first(fn (self $zona): bool => $zona->ativo && $zona->cobreCodigoPostal((int) $m[1]));
    }

    public function cobreCodigoPostal(int $prefixo): bool
    {
        foreach (preg_split('/[,;\s]+/', (string) $this->codigos_postais, -1, PREG_SPLIT_NO_EMPTY) as $intervalo) {
            if (preg_match('/^(\d{4})(?:-(\d{4}))?$/', $intervalo, $m)) {
                $de = (int) $m[1];
                $ate = isset($m[2]) ? (int) $m[2] : $de;

                if ($prefixo >= $de && $prefixo <= $ate) {
                    return true;
                }
            }
        }

        return false;
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(ZonaHorario::class);
    }

    public function substituicoes(): HasMany
    {
        return $this->hasMany(ZonaSubstituicao::class);
    }

    public function atribuicoes(): HasMany
    {
        return $this->hasMany(AtribuicaoEntrega::class);
    }

    /**
     * Quem faz a zona nesta data. Primeiro as substituicoes (ferias, faltas),
     * depois o horario desse dia da semana. Em semanas com feriado a entrega
     * pode cair noutro dia (segunda -> terca); se ninguem tiver a zona nesse
     * dia, fica quem a faz no dia original da entrega.
     */
    public function colaboradorEm(string|Carbon $data, ?string $diaOriginal = null): ?User
    {
        $id = $this->colaboradorIdEm($data, $diaOriginal);

        return $id === null ? null : $this->utilizador($id);
    }

    public function colaboradorIdEm(string|Carbon $data, ?string $diaOriginal = null, array $vistas = []): ?int
    {
        $data = Carbon::parse($data)->startOfDay();
        $dia = $data->toDateString();

        // Evita voltas sem fim (A vai com B e B vai com A).
        if (in_array($this->id, $vistas, true)) {
            return null;
        }
        $vistas[] = $this->id;

        $substituicao = $this->substituicoesCarregadas()
            ->first(fn (ZonaSubstituicao $s): bool => $s->inicio->toDateString() <= $dia && $s->fim->toDateString() >= $dia);

        if ($substituicao !== null) {
            return (int) $substituicao->user_id;
        }

        $horarios = $this->horariosCarregados()->keyBy('dia_semana');

        foreach (array_filter([self::DIAS[$data->dayOfWeek] ?? null, $diaOriginal]) as $diaSemana) {
            $horario = $horarios->get($diaSemana);

            if ($horario?->user_id !== null) {
                return (int) $horario->user_id;
            }

            // Neste dia a zona vai com outra: quem faz essa (com ferias e tudo).
            if ($horario?->acompanha_zona_id !== null) {
                $outra = self::with(['horarios', 'substituicoes'])->find($horario->acompanha_zona_id);
                $id = $outra?->colaboradorIdEm($data, $diaSemana, $vistas);

                if ($id !== null) {
                    return $id;
                }
            }
        }

        return null;
    }

    /** Substituicao que esta a valer nesta data, se houver. */
    public function substituicaoEm(string|Carbon $data): ?ZonaSubstituicao
    {
        $dia = Carbon::parse($data)->toDateString();

        return $this->substituicoesCarregadas()
            ->first(fn (ZonaSubstituicao $s): bool => $s->inicio->toDateString() <= $dia && $s->fim->toDateString() >= $dia);
    }

    public function colaboradorNoHorario(string $diaSemana): ?User
    {
        $id = $this->horariosCarregados()->firstWhere('dia_semana', $diaSemana)?->user_id;

        return $id === null ? null : $this->utilizador((int) $id);
    }

    private function horariosCarregados(): Collection
    {
        if (! $this->relationLoaded('horarios')) {
            $this->load('horarios');
        }

        return $this->horarios;
    }

    private function substituicoesCarregadas(): Collection
    {
        if (! $this->relationLoaded('substituicoes')) {
            $this->load('substituicoes');
        }

        return $this->substituicoes;
    }

    /** @var array<int, User|null> */
    private array $utilizadores = [];

    private function utilizador(int $id): ?User
    {
        if (! array_key_exists($id, $this->utilizadores)) {
            $this->utilizadores[$id] = User::find($id);
        }

        return $this->utilizadores[$id];
    }
}
