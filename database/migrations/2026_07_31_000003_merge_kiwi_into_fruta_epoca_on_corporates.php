<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Funde as quantidades de kiwi na fruta da epoca em todas as empresas
     * (frutas e frutas_por_dia) e remove a chave kiwi.
     */
    public function up(): void
    {
        foreach (DB::table('corporates')->get() as $row) {
            $frutas = $this->fundir($this->decode($row->frutas ?? null));
            $frutasPorDia = $this->decode($row->frutas_por_dia ?? null);

            if (is_array($frutasPorDia)) {
                foreach ($frutasPorDia as $dia => $valores) {
                    $frutasPorDia[$dia] = is_array($valores) ? $this->fundir($valores) : $valores;
                }
            }

            DB::table('corporates')->where('id', $row->id)->update([
                'frutas' => $frutas !== null ? json_encode($frutas) : $row->frutas,
                'frutas_por_dia' => is_array($frutasPorDia) ? json_encode($frutasPorDia) : $row->frutas_por_dia,
            ]);
        }
    }

    public function down(): void
    {
        // Sem reversao: os valores de kiwi ja foram somados a fruta da epoca.
    }

    private function decode(?string $valor): ?array
    {
        if (blank($valor)) {
            return null;
        }

        $decoded = json_decode($valor, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function fundir(?array $valores): ?array
    {
        if (! is_array($valores) || ! array_key_exists('kiwi', $valores)) {
            return $valores;
        }

        $kiwi = (float) ($valores['kiwi'] ?? 0);

        if ($kiwi > 0) {
            $valores['fruta_epoca'] = (int) round((float) ($valores['fruta_epoca'] ?? 0) + $kiwi);
        }

        unset($valores['kiwi']);

        return $valores;
    }
};
