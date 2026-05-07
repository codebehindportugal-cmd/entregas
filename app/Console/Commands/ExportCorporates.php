<?php

namespace App\Console\Commands;

use App\Models\Corporate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportCorporates extends Command
{
    protected $signature = 'corporates:export {path=storage/app/exports/corporates.json : Caminho do ficheiro JSON}';

    protected $description = 'Exporta empresas para JSON para importar em producao.';

    public function handle(): int
    {
        $path = $this->absolutePath((string) $this->argument('path'));

        File::ensureDirectoryExists(dirname($path));

        $corporates = Corporate::query()
            ->orderBy('empresa')
            ->orderBy('sucursal')
            ->get()
            ->map(fn (Corporate $corporate) => [
                'empresa' => $corporate->empresa,
                'sucursal' => $corporate->sucursal,
                'morada_entrega' => $corporate->morada_entrega,
                'dias_entrega' => $corporate->dias_entrega ?? [],
                'periodicidade_entrega' => $corporate->periodicidade_entrega ?? 'semanal',
                'quinzenal_referencia' => $corporate->quinzenal_referencia?->toDateString(),
                'horario_entrega' => $corporate->horario_entrega,
                'responsavel_nome' => $corporate->responsavel_nome,
                'responsavel_telefone' => $corporate->responsavel_telefone,
                'fatura_nome' => $corporate->fatura_nome,
                'fatura_nif' => $corporate->fatura_nif,
                'fatura_email' => $corporate->fatura_email,
                'fatura_morada' => $corporate->fatura_morada,
                'numero_caixas' => (int) $corporate->numero_caixas,
                'preco_venda_peca' => $corporate->preco_venda_peca !== null ? (float) $corporate->preco_venda_peca : null,
                'peso_total' => (float) $corporate->peso_total,
                'frutas' => $corporate->frutas ?? [],
                'frutas_por_dia' => $corporate->frutas_por_dia ?? [],
                'notas' => $corporate->notas,
                'ativo' => (bool) $corporate->ativo,
            ])
            ->values();

        File::put($path, json_encode([
            'exported_at' => now()->toIso8601String(),
            'count' => $corporates->count(),
            'corporates' => $corporates,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->info("Exportadas {$corporates->count()} empresas para {$path}");

        return self::SUCCESS;
    }

    private function absolutePath(string $path): string
    {
        return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, DIRECTORY_SEPARATOR)
            ? $path
            : base_path($path);
    }
}
