<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Definicoes do Moloni editaveis na aplicacao (Gestao -> Definicoes Moloni),
 * guardadas num Setting em JSON e aplicadas por cima do config/moloni.php.
 *
 * Prioridade: valor guardado aqui > .env / config. Campo vazio = usa o .env.
 *
 * As CREDENCIAIS (developer_id, client_secret, username, password,
 * access_token, company_id) NAO passam por aqui de proposito: ficam so no .env,
 * fora da base de dados e fora do browser.
 */
class MoloniSettings
{
    public const SETTING_KEY = 'moloni_config';

    private const CACHE_KEY = 'moloni.settings';

    /**
     * Campos editaveis, por grupo. tipo: texto | numero | decimal | booleano | textarea
     *
     * @return array<string,array{titulo:string,campos:array<string,array{label:string,tipo:string,ajuda?:string}>}>
     */
    public static function esquema(): array
    {
        return [
            'series' => [
                'titulo' => 'Series de documentos',
                'campos' => [
                    'document_set_id_fatura' => ['label' => 'Serie da Fatura (B2B)', 'tipo' => 'numero', 'ajuda' => 'document_set_id da serie FT. Ve os ids com "php artisan moloni:document-sets".'],
                    'document_set_id_fatura_recibo' => ['label' => 'Serie da Fatura-Recibo (B2C)', 'tipo' => 'numero'],
                    'document_set_id_guia' => ['label' => 'Serie da Guia de Transporte', 'tipo' => 'numero', 'ajuda' => 'Tem de ser a serie GT, diferente da serie das faturas.'],
                ],
            ],
            'artigos' => [
                'titulo' => 'Artigos do Moloni',
                'campos' => [
                    'cabaz_composto_referencia' => ['label' => 'Artigo composto do cabaz', 'tipo' => 'texto', 'ajuda' => 'Referencia do artigo composto usado na fatura (ex.: HM5069-0). Cada empresa pode ter o seu na ficha.'],
                    'guia_referencia' => ['label' => 'Artigo da guia de transporte', 'tipo' => 'texto', 'ajuda' => 'Vazio = usa o artigo composto da fatura.'],
                    'portes_referencia' => ['label' => 'Artigo do transporte/portes', 'tipo' => 'texto', 'ajuda' => 'Ex.: HM2222. Usado na linha do custo de envio.'],
                ],
            ],
            'fiscal' => [
                'titulo' => 'IVA e catalogo',
                'campos' => [
                    'tax_id' => ['label' => 'tax_id do IVA da fruta', 'tipo' => 'numero', 'ajuda' => 'Vazio = resolvido automaticamente pela taxa abaixo.'],
                    'default_tax_value' => ['label' => 'Taxa de IVA da fruta (%)', 'tipo' => 'decimal', 'ajuda' => 'Normalmente 6.'],
                    'portes_tax_value' => ['label' => 'Taxa de IVA do transporte (%)', 'tipo' => 'decimal', 'ajuda' => 'Normalmente 23.'],
                    'portes_tax_id' => ['label' => 'tax_id do IVA do transporte', 'tipo' => 'numero', 'ajuda' => 'Vazio = resolvido pela taxa acima.'],
                    'exemption_reason' => ['label' => 'Motivo de isencao', 'tipo' => 'texto', 'ajuda' => 'So usado quando a linha nao leva IVA (ex.: M99).'],
                    'unit_id' => ['label' => 'Unidade de medida (unit_id)', 'tipo' => 'numero'],
                    'category_id' => ['label' => 'Categoria de artigos (category_id)', 'tipo' => 'numero'],
                    'warehouse_id' => ['label' => 'Armazem (warehouse_id)', 'tipo' => 'numero', 'ajuda' => 'Obrigatorio no Moloni quando os artigos gerem stock. Vazio = nao e enviado.'],
                ],
            ],
            'faturacao' => [
                'titulo' => 'Regras de faturacao',
                'campos' => [
                    'fatura_semanas' => ['label' => 'Semanas do ciclo', 'tipo' => 'decimal', 'ajuda' => 'Quantas semanas conta um ciclo de faturacao. Normalmente 4.'],
                    'fatura_qtd_pai' => ['label' => 'Quantidade da linha do cabaz', 'tipo' => 'decimal', 'ajuda' => 'Quantidade que sai na linha do artigo composto na fatura mensal. Normalmente 4.'],
                    'fatura_dias_vencimento' => ['label' => 'Dias de vencimento por defeito', 'tipo' => 'numero', 'ajuda' => 'Cada empresa pode ter o seu na ficha.'],
                    'custo_envio_padrao' => ['label' => 'Custo de envio por entrega (por defeito)', 'tipo' => 'decimal', 'ajuda' => 'Usado nas empresas que nao tenham valor proprio na ficha. Vazio = sem portes. Na ficha, escrever 0 isenta essa empresa.'],
                    'payment_method_id' => ['label' => 'Metodo de pagamento (B2C)', 'tipo' => 'numero'],
                    'fechar_documentos' => ['label' => 'Emitir documentos fechados', 'tipo' => 'booleano', 'ajuda' => 'Desligado = os documentos ficam em rascunho no Moloni.'],
                    'precos_incluem_iva' => ['label' => 'Valores acordados incluem IVA', 'tipo' => 'booleano', 'ajuda' => 'Aplica-se ao preco do cabaz e ao valor de ciclo. O preco por peca e sempre liquido.'],
                ],
            ],
            'guia' => [
                'titulo' => 'Guia de transporte',
                'campos' => [
                    'guia_morada_carga' => ['label' => 'Morada de carga', 'tipo' => 'texto'],
                    'guia_cp_carga' => ['label' => 'Codigo postal de carga', 'tipo' => 'texto'],
                    'guia_cidade_carga' => ['label' => 'Localidade de carga', 'tipo' => 'texto'],
                    'guia_hora_transporte' => ['label' => 'Hora de inicio do transporte', 'tipo' => 'texto', 'ajuda' => 'Formato HH:MM (ex.: 08:00).'],
                    'guia_delivery_method_id' => ['label' => 'Metodo de expedicao', 'tipo' => 'numero', 'ajuda' => 'Ve os ids com "php artisan moloni:delivery-methods".'],
                    'guia_observacoes' => ['label' => 'Observacoes da guia', 'tipo' => 'textarea'],
                ],
            ],
        ];
    }

    /** @return array<int,string> todas as chaves editaveis */
    public static function chaves(): array
    {
        $chaves = [];

        foreach (self::esquema() as $grupo) {
            foreach (array_keys($grupo['campos']) as $chave) {
                $chaves[] = $chave;
            }
        }

        return $chaves;
    }

    /** Valores guardados na base de dados (so os que foram definidos). */
    public static function guardados(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(10), function (): array {
            try {
                $raw = Setting::query()->where('key', self::SETTING_KEY)->value('value');
            } catch (Throwable) {
                return [];
            }

            $valores = filled($raw) ? json_decode((string) $raw, true) : [];

            return is_array($valores) ? $valores : [];
        });
    }

    public static function guardar(array $valores): void
    {
        $limpo = [];

        foreach (self::chaves() as $chave) {
            if (array_key_exists($chave, $valores) && $valores[$chave] !== null && $valores[$chave] !== '') {
                $limpo[$chave] = $valores[$chave];
            }
        }

        Setting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => json_encode($limpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        );

        Cache::forget(self::CACHE_KEY);
        self::aplicar();
    }

    /**
     * Poe os valores guardados por cima do config/moloni.php. Chamado no boot
     * da aplicacao, para que tudo o que le config('moloni.*') os apanhe.
     */
    public static function aplicar(): void
    {
        try {
            $valores = self::guardados();
        } catch (Throwable) {
            return; // base de dados indisponivel (ex.: durante as migracoes)
        }

        foreach ($valores as $chave => $valor) {
            if (! in_array($chave, self::chaves(), true)) {
                continue;
            }

            config(['moloni.'.$chave => $valor]);
        }
    }
}
