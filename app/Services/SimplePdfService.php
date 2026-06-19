<?php

namespace App\Services;

use App\Models\Corporate;
use App\Models\CorporateHistorico;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SimplePdfService
{
    public function monthlyCorporateMap(array $mapa): string
    {
        /** @var Corporate $corporate */
        $corporate = $mapa['corporate'];
        /** @var Collection<int, array<string, mixed>> $linhas */
        $linhas = $mapa['linhas'];
        /** @var Collection<int, CorporateHistorico> $historicos */
        $historicos = $mapa['historicos'] ?? collect();
        $labelsKg = $mapa['labelsKg'] ?? [];
        $labelsPastelaria = $mapa['labelsPastelaria'] ?? [];

        $rows = [];
        $rows[] = 'Mapa mensal de entregas';
        $rows[] = 'Mes: '.$mapa['inicio']->format('m/Y');
        $rows[] = 'Gerado em: '.now()->format('d/m/Y H:i');
        $rows[] = '';
        $rows[] = 'DADOS DA EMPRESA';
        $rows[] = 'Empresa: '.$corporate->empresa.($corporate->sucursal ? ' - '.$corporate->sucursal : '');
        $rows[] = 'Estado: '.($corporate->ativo ? 'Ativa' : 'Inativa');
        $rows[] = 'Morada de entrega: '.($corporate->morada_entrega ?: 'Nao definida');
        $rows[] = 'Morada usada para entrega: '.($corporate->moradaParaEntrega() ?: 'Nao definida');
        $rows[] = 'Horario de entrega: '.($corporate->horario_entrega ?: 'Nao definido');
        $rows[] = 'Responsavel: '.collect([$corporate->responsavel_nome, $corporate->responsavel_telefone])->filter()->implode(' | ');
        $rows[] = 'Faturacao: '.collect([
            $corporate->fatura_nome ? 'Nome '.$corporate->fatura_nome : null,
            $corporate->fatura_nif ? 'NIF '.$corporate->fatura_nif : null,
            $corporate->fatura_email ? 'Email '.$corporate->fatura_email : null,
            $corporate->fatura_morada ? 'Morada '.$corporate->fatura_morada : null,
        ])->filter()->implode(' | ');
        $rows[] = 'Caixas: '.(int) $corporate->numero_caixas;
        $rows[] = 'Preco por peca: '.$this->money($corporate->preco_venda_peca);
        $rows[] = 'Cabaz tipo: '.($corporate->cabaz_tipo ?: 'Nao definido').($corporate->cabaz_quantidade ? ' x '.(int) $corporate->cabaz_quantidade : '');
        $rows[] = 'Notas da empresa: '.($corporate->notas ?: 'Sem notas');
        $rows[] = '';
        $rows[] = 'CONFIGURACAO DE ENTREGAS';
        $rows[] = 'Dias de entrega: '.$this->listValue($corporate->dias_entrega);
        $rows[] = 'Periodicidade: '.($corporate->periodicidade_entrega ?: 'semanal');
        $rows[] = 'Referencia quinzenal: '.($corporate->quinzenal_referencia?->format('d/m/Y') ?: 'Nao aplicavel');
        $rows[] = 'Pecas por dia: '.$this->mapValue($corporate->pecasPorDiaEntrega());
        $rows[] = 'Produtos kg por dia: '.$this->nestedMapValue($corporate->produtosKgPorDiaEntrega(), $labelsKg, ' kg');
        $rows[] = 'Pastelaria por dia: '.$this->nestedMapValue($corporate->pastelariaPorDiaEntrega(), $labelsPastelaria);
        $rows[] = 'Produtos mensais: '.$this->listValue($corporate->produtos_mensais, 'Nenhum');
        $rows[] = '';
        $rows[] = 'MAPA DO MES';
        $rows[] = 'Data | Dia | Pecas | Kg | Padaria | Observacao';
        $rows[] = str_repeat('-', 112);

        if ($linhas->isEmpty()) {
            $rows[] = 'Nao existem dias de entrega previstos neste mes.';
        }

        foreach ($linhas as $linha) {
            $produtosKg = collect($linha['produtos_kg'] ?? [])
                ->map(fn ($quantidade, $produto) => ($labelsKg[$produto] ?? $produto).': '.number_format((float) $quantidade, 2, ',', ' ').' kg')
                ->implode('; ');
            $pastelaria = collect($linha['pastelaria'] ?? [])
                ->map(fn ($quantidade, $produto) => ($labelsPastelaria[$produto] ?? $produto).': '.(int) $quantidade)
                ->implode('; ');
            $observacao = collect([
                $this->statusLabel((string) ($linha['status'] ?? '')),
                $linha['nota'] ?? null,
                $produtosKg ? 'Produtos kg: '.$produtosKg : null,
                $pastelaria ? 'Padaria: '.$pastelaria : null,
            ])->filter()->implode(' | ');

            $rows[] = sprintf(
                '%s | %s | %d | %s kg | %d | %s',
                $linha['data']->format('d/m/Y'),
                $linha['dia_semana'],
                (int) $linha['pecas'],
                number_format((float) ($linha['total_kg'] ?? 0), 2, ',', ' '),
                (int) ($linha['total_pastelaria'] ?? 0),
                $observacao
            );
        }

        $rows[] = '';
        $rows[] = 'Total de dias entregues: '.$mapa['totalDiasEntregues'];
        $rows[] = 'Totais entregues no mes: '.$mapa['totalPecasEntregues'].' pecas, '
            .number_format((float) $mapa['totalKgEntregues'], 2, ',', ' ')
            .' kg, '.$mapa['totalPastelariaEntregue'].' padaria';
        $rows[] = '';
        $rows[] = 'HISTORICO DO MES';

        if ($historicos->isEmpty()) {
            $rows[] = 'Sem historico registado neste mes.';
        }

        foreach ($historicos as $historico) {
            $rows[] = collect([
                $historico->data?->format('d/m/Y'),
                $this->historicoTipoLabel((string) $historico->tipo),
                $historico->pecas_entregues !== null ? 'Pecas '.$historico->pecas_entregues : null,
                $historico->user?->name ? 'Utilizador '.$historico->user->name : null,
                $historico->texto,
            ])->filter()->implode(' | ');
        }

        return $this->document($rows);
    }

    private function listValue(mixed $values, string $empty = 'Nao definido'): string
    {
        $values = collect(is_array($values) ? $values : [])
            ->filter(fn ($value): bool => filled($value))
            ->values();

        return $values->isEmpty() ? $empty : $values->implode(', ');
    }

    private function mapValue(array $values): string
    {
        $text = collect($values)
            ->map(fn ($value, $key): string => "{$key}: {$value}")
            ->implode('; ');

        return $text ?: 'Nao definido';
    }

    private function nestedMapValue(array $values, array $labels = [], string $suffix = ''): string
    {
        $text = collect($values)
            ->map(function (array $items, string $dia) use ($labels, $suffix): string {
                $itemsText = collect($items)
                    ->filter(fn ($value): bool => (float) $value > 0)
                    ->map(fn ($value, string $key): string => ($labels[$key] ?? $key).': '.$value.$suffix)
                    ->implode(', ');

                return $itemsText ? "{$dia}: {$itemsText}" : '';
            })
            ->filter()
            ->implode('; ');

        return $text ?: 'Nao definido';
    }

    private function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Nao definido';
        }

        return number_format((float) $value, 4, ',', ' ').' EUR';
    }

    private function document(array $rows): string
    {
        $pages = array_chunk($this->wrapRows($rows), 42);
        $objects = [
            1 => "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
        ];
        $pageRefs = [];
        $nextObject = 3;

        foreach ($pages as $pageRows) {
            $pageObject = $nextObject++;
            $contentObject = $nextObject++;
            $pageRefs[] = "{$pageObject} 0 R";
            $content = $this->pageContent($pageRows);
            $objects[$pageObject] = "{$pageObject} 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 {$this->fontObjectNumber($pages)} 0 R >> >> /Contents {$contentObject} 0 R >>\nendobj\n";
            $objects[$contentObject] = "{$contentObject} 0 obj\n<< /Length ".strlen($content)." >>\nstream\n{$content}\nendstream\nendobj\n";
        }

        $fontObject = $this->fontObjectNumber($pages);
        $objects[2] = "2 0 obj\n<< /Type /Pages /Kids [".implode(' ', $pageRefs).'] /Count '.count($pageRefs)." >>\nendobj\n";
        $objects[$fontObject] = "{$fontObject} 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];

        foreach ($objects as $objectNumber => $object) {
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefOffset = strlen($pdf);
        $size = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 {$size}\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i < $size; $i++) {
            $offset = $offsets[$i] ?? 0;
            $inUse = $offset > 0 ? 'n' : 'f';
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 {$inUse} \n";
        }

        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }

    private function pageContent(array $rows): string
    {
        $content = "BT\n/F1 9 Tf\n50 550 Td\n12 TL\n";

        foreach ($rows as $row) {
            $content .= '('.$this->pdfText($row).") Tj\nT*\n";
        }

        return $content.'ET';
    }

    private function wrapRows(array $rows): array
    {
        return collect($rows)
            ->flatMap(function (string $row): array {
                $text = trim((string) Str::of($row)->replace(["\r", "\n"], ' '));

                if ($text === '') {
                    return [''];
                }

                return explode("\n", wordwrap($text, 135, "\n", true));
            })
            ->all();
    }

    private function pdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], Str::ascii($text));
    }

    private function statusLabel(string $status): ?string
    {
        return match ($status) {
            'nao_entregamos' => 'Nao entregue',
            'entrega_parcial' => 'Entrega parcial',
            'falhou' => 'Falhou',
            default => null,
        };
    }

    private function historicoTipoLabel(string $tipo): string
    {
        return match ($tipo) {
            'nao_entregamos' => 'Nao entregamos',
            'entrega_parcial' => 'Entrega parcial',
            'entrega_extra' => 'Entrega extra',
            'nota', '' => 'Nota',
            default => $tipo,
        };
    }

    private function fontObjectNumber(array $pages): int
    {
        return 3 + (count($pages) * 2);
    }
}
