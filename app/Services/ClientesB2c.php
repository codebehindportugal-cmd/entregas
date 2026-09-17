<?php

namespace App\Services;

use App\Models\WooOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Os clientes B2C, vistos pelo telefone.
 *
 * Nao ha tabela de clientes: cada encomenda do site traz os dados de quem a fez,
 * e o mesmo cliente aparece tantas vezes quantas encomendou — as vezes com o
 * nome escrito de outra maneira. O telefone e o que se mantem, por isso e ele
 * que diz quem e quem. Guarda-se em formatos diferentes ("+351 912 345 678",
 * "912345678"), e por isso compara-se sempre normalizado.
 */
class ClientesB2c
{
    /** Remove os separadores que aparecem nos telefones gravados. */
    private const COLUNA_TELEFONE_LIMPA = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(billing_phone, ' ', ''), '-', ''), '+', ''), '.', ''), '(', ''), ')', '')";

    /**
     * So os digitos, sem o indicativo de Portugal: "+351 912 345 678",
     * "00351912345678" e "912345678" dao todos "912345678". Numeros
     * estrangeiros ficam com o indicativo. Menos de 9 digitos nao e um
     * telefone e devolve null.
     */
    public static function normalizarTelefone(?string $telefone): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $telefone) ?? '';

        if (str_starts_with($digitos, '00')) {
            $digitos = substr($digitos, 2);
        }

        if (strlen($digitos) === 12 && str_starts_with($digitos, '351')) {
            $digitos = substr($digitos, 3);
        }

        return strlen($digitos) >= 9 ? $digitos : null;
    }

    /**
     * Todas as encomendas com este telefone, da mais recente para a mais antiga.
     *
     * @return Collection<int, WooOrder>
     */
    public function encomendasDoTelefone(?string $telefone): Collection
    {
        $normalizado = self::normalizarTelefone($telefone);

        if ($normalizado === null) {
            return collect();
        }

        return WooOrder::query()
            ->whereNotNull('billing_phone')
            ->whereRaw(self::COLUNA_TELEFONE_LIMPA.' LIKE ?', ['%'.substr($normalizado, -9)])
            ->orderByDesc('ordered_at')
            ->orderByDesc('id')
            ->get()
            // O LIKE so apanha candidatos; quem decide e a comparacao normalizada.
            ->filter(fn (WooOrder $order): bool => self::normalizarTelefone($order->billing_phone) === $normalizado)
            ->values();
    }

    /**
     * O cliente com este telefone, juntando o que as encomendas dele sabem.
     *
     * Cada campo vem da encomenda mais recente que o tenha preenchido. A morada
     * vem inteira da mesma encomenda (rua, codigo postal e cidade juntos), para
     * nao misturar a rua de uma casa com o codigo postal de outra.
     */
    public function perfil(?string $telefone): ?array
    {
        $encomendas = $this->encomendasDoTelefone($telefone);

        if ($encomendas->isEmpty()) {
            return null;
        }

        $maisRecente = $encomendas->first();
        $morada = $encomendas->map(fn (WooOrder $order): ?array => $this->moradaDe($order))->first(fn (?array $m): bool => $m !== null);

        return [
            'perfil_woo_order_id' => $maisRecente->id,
            'telefone_normalizado' => self::normalizarTelefone($telefone),
            'nome' => $this->primeiroPreenchido($encomendas, fn (WooOrder $o) => $o->billing_name),
            'telefone' => $maisRecente->billing_phone,
            'email' => $this->primeiroPreenchido($encomendas, fn (WooOrder $o) => $o->billing_email),
            'morada' => $morada['morada'] ?? null,
            'codigo_postal' => $morada['codigo_postal'] ?? null,
            'cidade' => $morada['cidade'] ?? null,
            'idioma' => $this->primeiroPreenchido($encomendas, fn (WooOrder $o) => $o->customer_language),
            'dia_entrega' => $this->primeiroPreenchido($encomendas, fn (WooOrder $o) => $o->dia_entrega),
            'nomes' => $this->nomesDistintos($encomendas),
            'total_encomendas' => $encomendas->count(),
            'encomendas' => $encomendas->map(fn (WooOrder $o): array => [
                'id' => $o->id,
                'woo_id' => $o->woo_id,
                'tipo' => $o->source_type,
                'estado' => $o->status,
                'data' => $o->ordered_at?->toDateString(),
                'total' => $o->total !== null ? (float) $o->total : null,
                'nome' => $o->billing_name,
            ])->all(),
        ];
    }

    /** Dois nomes sao o mesmo cliente se um contem o outro, sem acentos nem maiusculas ("Joana" e "Joana Costa"). */
    public static function mesmoNome(?string $a, ?string $b): bool
    {
        $a = self::nomeComparavel($a);
        $b = self::nomeComparavel($b);

        if ($a === '' || $b === '') {
            return true;
        }

        return str_contains($a, $b) || str_contains($b, $a);
    }

    private static function nomeComparavel(?string $nome): string
    {
        return trim((string) preg_replace('/\s+/', ' ', Str::lower(Str::ascii((string) $nome))));
    }

    /** @return array<int, string> */
    private function nomesDistintos(Collection $encomendas): array
    {
        $nomes = [];

        foreach ($encomendas as $order) {
            $nome = trim((string) $order->billing_name);

            if ($nome === '') {
                continue;
            }

            foreach ($nomes as $existente) {
                if (self::nomeComparavel($existente) === self::nomeComparavel($nome)) {
                    continue 2;
                }
            }

            $nomes[] = $nome;
        }

        return $nomes;
    }

    private function moradaDe(WooOrder $order): ?array
    {
        $payload = is_array($order->raw_payload) ? $order->raw_payload : [];

        foreach (['shipping', 'billing'] as $bloco) {
            $dados = is_array($payload[$bloco] ?? null) ? $payload[$bloco] : [];

            if (filled($dados['address_1'] ?? null)) {
                return [
                    'morada' => trim((string) $dados['address_1'].' '.($dados['address_2'] ?? '')),
                    'codigo_postal' => $dados['postcode'] ?? null,
                    'cidade' => $dados['city'] ?? null,
                ];
            }
        }

        return null;
    }

    private function primeiroPreenchido(Collection $encomendas, callable $campo): ?string
    {
        foreach ($encomendas as $order) {
            $valor = $campo($order);

            if (filled($valor)) {
                return (string) $valor;
            }
        }

        return null;
    }
}
