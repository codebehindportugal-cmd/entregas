<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Definicoes da caixa de pedidos (Clientes -> Definicoes de pedidos).
 *
 * Ao contrario do Moloni, aqui as chaves (WhatsApp Cloud API) ficam na base de
 * dados de proposito, para se poderem trocar no site sem mexer no servidor.
 * Os campos do tipo "segredo" sao guardados encriptados com a APP_KEY e nunca
 * voltam ao browser: a pagina so mostra se estao definidos.
 */
class PedidosSettings
{
    public const SETTING_KEY = 'pedidos_config';

    private const CACHE_KEY = 'pedidos.settings';

    /** @var array<string,mixed> */
    private const DEFAULTS = [
        'gmail_etiqueta_entrada' => 'Encomendas',
        'gmail_etiqueta_processado' => 'Encomendas/Processado',
        'avisar_ntfy' => '1',
        'whatsapp_ativo' => '0',
    ];

    /**
     * tipo: texto | booleano | segredo
     *
     * @return array<string,array{titulo:string,descricao?:string,campos:array<string,array{label:string,tipo:string,ajuda?:string}>}>
     */
    public static function esquema(): array
    {
        return [
            'email' => [
                'titulo' => 'Email (Gmail)',
                'descricao' => 'Os emails sao lidos pelo Claude atraves do conector do Gmail, por isso nao ha chave a guardar. Basta dizer que etiquetas usar.',
                'campos' => [
                    'gmail_etiqueta_entrada' => ['label' => 'Etiqueta dos emails de encomenda', 'tipo' => 'texto', 'ajuda' => 'Os emails com esta etiqueta sao lidos ao fim do dia. Ex.: Encomendas'],
                    'gmail_etiqueta_processado' => ['label' => 'Etiqueta depois de lido', 'tipo' => 'texto', 'ajuda' => 'O email passa para esta etiqueta para nao ser lido duas vezes. Ex.: Encomendas/Processado'],
                ],
            ],
            'whatsapp' => [
                'titulo' => 'WhatsApp (Cloud API da Meta)',
                'descricao' => 'Opcional. Com isto ligado, as mensagens que chegam ao numero da Horta da Maria entram sozinhas na caixa de pedidos.',
                'campos' => [
                    'whatsapp_ativo' => ['label' => 'Receber mensagens do WhatsApp', 'tipo' => 'booleano'],
                    'whatsapp_numero' => ['label' => 'Numero de WhatsApp', 'tipo' => 'texto', 'ajuda' => 'So para referencia. Ex.: +351 912 345 678'],
                    'whatsapp_phone_number_id' => ['label' => 'Phone number ID', 'tipo' => 'texto', 'ajuda' => 'Meta for Developers -> a tua app -> WhatsApp -> API Setup.'],
                    'whatsapp_access_token' => ['label' => 'Access token', 'tipo' => 'segredo', 'ajuda' => 'Token permanente de um System User (Business Settings).'],
                    'whatsapp_verify_token' => ['label' => 'Verify token do webhook', 'tipo' => 'segredo', 'ajuda' => 'Inventas tu uma palavra e pões a mesma na configuracao do webhook na Meta.'],
                    'whatsapp_app_secret' => ['label' => 'App secret', 'tipo' => 'segredo', 'ajuda' => 'Meta for Developers -> App settings -> Basic. Serve para confirmar que as mensagens vem mesmo da Meta.'],
                ],
            ],
            'avisos' => [
                'titulo' => 'Avisos',
                'campos' => [
                    'avisar_ntfy' => ['label' => 'Avisar no ntfy quando entram pedidos', 'tipo' => 'booleano'],
                ],
            ],
        ];
    }

    /** @return array<string,array{label:string,tipo:string,ajuda?:string}> */
    public static function campos(): array
    {
        $campos = [];

        foreach (self::esquema() as $grupo) {
            $campos += $grupo['campos'];
        }

        return $campos;
    }

    /** Valores guardados (os segredos ainda encriptados). */
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

    /** Um valor ja pronto a usar (segredos desencriptados). */
    public static function get(string $chave, mixed $omissao = null): mixed
    {
        $guardado = self::guardados()[$chave] ?? null;

        if ($guardado === null || $guardado === '') {
            return self::DEFAULTS[$chave] ?? $omissao;
        }

        if ((self::campos()[$chave]['tipo'] ?? null) === 'segredo') {
            try {
                return Crypt::decryptString((string) $guardado);
            } catch (Throwable) {
                return $omissao; // APP_KEY mudou: o segredo tem de ser posto outra vez
            }
        }

        return $guardado;
    }

    public static function ligado(string $chave): bool
    {
        return filter_var(self::get($chave, '0'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function definido(string $chave): bool
    {
        return filled(self::guardados()[$chave] ?? null);
    }

    /**
     * Guarda o que veio do formulario. Um segredo deixado em branco mantem o que
     * ja estava; para o apagar manda-se o nome em $apagar.
     *
     * @param  array<int,string>  $apagar
     */
    public static function guardar(array $valores, array $apagar = []): void
    {
        $atual = self::guardados();

        foreach (self::campos() as $chave => $campo) {
            $valor = $valores[$chave] ?? null;

            if ($campo['tipo'] === 'segredo') {
                if (in_array($chave, $apagar, true)) {
                    unset($atual[$chave]);
                } elseif (filled($valor)) {
                    $atual[$chave] = Crypt::encryptString(trim((string) $valor));
                }

                continue;
            }

            if ($valor === null || $valor === '') {
                unset($atual[$chave]);
            } else {
                $atual[$chave] = is_string($valor) ? trim($valor) : $valor;
            }
        }

        Setting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => json_encode($atual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        );

        Cache::forget(self::CACHE_KEY);
    }

    /** O que o Claude precisa de saber para o trabalho do fim do dia (sem segredos). */
    public static function publicas(): array
    {
        return [
            'gmail_etiqueta_entrada' => self::get('gmail_etiqueta_entrada'),
            'gmail_etiqueta_processado' => self::get('gmail_etiqueta_processado'),
            'whatsapp_ativo' => self::ligado('whatsapp_ativo'),
        ];
    }
}
