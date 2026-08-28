<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class MoloniService
{
    private const BASE_URL = 'https://api.moloni.pt/v2';

    /**
     * URL publica do PDF de um documento ja emitido.
     */
    public function pdfUrl(int $documentId): ?string
    {
        if ($documentId <= 0) {
            return null;
        }

        $response = $this->request('documents/getPDFLink', ['document_id' => $documentId]);

        $url = $response['url'] ?? null;

        return filled($url) ? (string) $url : null;
    }

    /**
     * O documento ainda existe no Moloni? Usado para nao bloquear a reemissao
     * quando a fatura foi apagada la (o registo local fica para tras).
     * Devolve null quando a API nao responde — nesse caso NAO se assume nada.
     */
    public function documentoExiste(int $documentId): ?bool
    {
        if ($documentId <= 0) {
            return false;
        }

        $resposta = $this->request('documents/getOne', ['document_id' => $documentId]);

        if (! is_array($resposta)) {
            return null; // sem resposta: nao concluir nada
        }

        if (isset($resposta['errors']) && filled($resposta['errors'])) {
            return false;
        }

        return (int) ($resposta['document_id'] ?? 0) === $documentId;
    }

    // ------------------------------------------------------------------
    //  Emissao de documentos
    // ------------------------------------------------------------------

    /**
     * Insere uma Fatura (B2B / empresas).
     *
     * @return array{document_id:int,raw:array}
     */
    public function inserirFatura(array $payload): array
    {
        return $this->inserirDocumento('invoices/insert', $payload);
    }

    /**
     * Insere uma Fatura-Recibo (B2C / subscricoes ja pagas).
     *
     * @return array{document_id:int,raw:array}
     */
    public function inserirFaturaRecibo(array $payload): array
    {
        return $this->inserirDocumento('invoiceReceipts/insert', $payload);
    }

    /**
     * Insere uma Guia de Transporte (billsOfLading).
     *
     * @return array{document_id:int,raw:array}
     */
    public function inserirGuiaTransporte(array $payload): array
    {
        return $this->inserirDocumento('billsOfLading/insert', $payload);
    }

    /**
     * Lista as series de documentos (document sets) da empresa no Moloni.
     *
     * @return array<int,array{document_set_id:int,name:string}>
     */
    /**
     * Resposta crua de products/getOne. Usada para ler a composicao de um
     * artigo composto e pelo comando de diagnostico `moloni:artigo`.
     */
    public function artigo(int $productId): ?array
    {
        if ($productId <= 0) {
            return null;
        }

        $resp = $this->request('products/getOne', ['product_id' => $productId]);

        return is_array($resp) ? $resp : null;
    }

    /**
     * Componentes (filhos) de um artigo composto do Moloni, ja com referencia,
     * nome, quantidade da composicao e preco de tabela (liquido).
     *
     * O Moloni recusa a linha de um artigo composto sem `child_products`
     * ("Field 'child_products' is required") — daqui saem essas linhas.
     *
     * @return array<int,array{product_id:int,reference:string,name:string,qty:float,price:float}>
     */
    public function componentesComposto(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        return Cache::remember("moloni.composto.{$productId}", now()->addMinutes(30), function () use ($productId): array {
            $resp = $this->artigo($productId);

            if (! is_array($resp)) {
                return [];
            }

            $filhos = $resp['child_products']
                ?? $resp['composition_products']
                ?? $resp['childs']
                ?? $resp['children']
                ?? $resp['composition']
                ?? [];

            if (! is_array($filhos)) {
                return [];
            }

            $out = [];

            foreach ($filhos as $f) {
                if (! is_array($f)) {
                    continue;
                }

                $produto = is_array($f['product'] ?? null) ? $f['product'] : [];

                $id = (int) (
                    $f['product_id']
                    ?? $f['child_id']
                    ?? $f['child_product_id']
                    ?? ($produto['product_id'] ?? 0)
                );

                if ($id <= 0) {
                    continue;
                }

                $nome = trim((string) ($f['name'] ?? ($produto['name'] ?? '')));
                $ref = trim((string) ($f['reference'] ?? ($produto['reference'] ?? '')));
                $qty = (float) ($f['qty'] ?? $f['quantity'] ?? 1);
                $preco = (float) ($f['price'] ?? ($produto['price'] ?? 0));

                // Alguns formatos so trazem o id do filho: vai buscar o resto.
                if ($nome === '' || $ref === '' || $preco <= 0) {
                    $detalhe = $this->artigo($id);

                    if (is_array($detalhe)) {
                        $nome = $nome !== '' ? $nome : trim((string) ($detalhe['name'] ?? ''));
                        $ref = $ref !== '' ? $ref : trim((string) ($detalhe['reference'] ?? ''));
                        $preco = $preco > 0 ? $preco : (float) ($detalhe['price'] ?? 0);
                    }
                }

                $out[] = [
                    'product_id' => $id,
                    'reference' => $ref,
                    'name' => $nome !== '' ? $nome : ('Componente '.$id),
                    'qty' => $qty > 0 ? $qty : 1.0,
                    'price' => round($preco, 4),
                ];
            }

            return $out;
        });
    }

    /** Armazem por defeito (obrigatorio em guias quando os artigos gerem stock). */
    public function warehouseId(): ?int
    {
        $valor = $this->configValue('warehouse_id');

        return filled($valor) ? (int) $valor : null;
    }

    /**
     * Lista os metodos de expedicao (delivery methods) do Moloni.
     *
     * @return array<int,array{delivery_method_id:int,name:string}>
     */
    public function deliveryMethods(): array
    {
        $resposta = $this->request('deliveryMethods/getAll', []);

        if (! is_array($resposta)) {
            return [];
        }

        $out = [];

        foreach ($resposta as $m) {
            if (! is_array($m)) {
                continue;
            }

            $out[] = [
                'delivery_method_id' => (int) ($m['delivery_method_id'] ?? 0),
                'name' => (string) ($m['name'] ?? ''),
            ];
        }

        return $out;
    }

    public function documentSets(): array
    {
        $resposta = $this->request('documentSets/getAll', []);

        if (! is_array($resposta)) {
            return [];
        }

        $sets = [];

        foreach ($resposta as $set) {
            if (! is_array($set)) {
                continue;
            }

            $sets[] = [
                'document_set_id' => (int) ($set['document_set_id'] ?? 0),
                'name' => (string) ($set['name'] ?? ''),
            ];
        }

        return $sets;
    }

    private function inserirDocumento(string $endpoint, array $payload): array
    {
        $response = $this->request($endpoint, $payload);

        if ($response === null) {
            throw new RuntimeException('Sem resposta da API Moloni ao inserir o documento.');
        }

        $this->abortarSeErro($response, "Moloni {$endpoint}");

        $documentId = (int) ($response['document_id'] ?? 0);

        if ($documentId <= 0) {
            // Payload completo no laravel.log — os erros do Moloni vem indexados
            // e sem contexto, e so com o payload se percebe que linha falhou.
            Log::error("Moloni {$endpoint} recusou o documento", [
                'payload' => $payload,
                'resposta' => $response,
            ]);

            $detalhe = mb_substr((string) json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 600);
            throw new RuntimeException('A API Moloni nao devolveu um document_id valido. Resposta: '.$detalhe.' (payload completo no laravel.log)');
        }

        return ['document_id' => $documentId, 'raw' => $response];
    }

    // ------------------------------------------------------------------
    //  Clientes
    // ------------------------------------------------------------------

    /**
     * Devolve o customer_id de um cliente com o NIF indicado, criando-o se nao existir.
     *
     * @param  array{name:string,vat?:string,email?:string,address?:string,city?:string,zip_code?:string,language_id?:int}  $dados
     */
    public function obterOuCriarCliente(array $dados): int
    {
        // Sem NIF valido -> consumidor final (999999990).
        $vat = $this->normalizarNif($dados['vat'] ?? null) ?? '999999990';

        // Procura sempre primeiro (inclui o consumidor final, que ja existe na
        // conta), para nao tentar recriar um cliente que ja existe.
        $encontrado = $this->request('customers/getByVat', ['vat' => $vat]);

        if (is_array($encontrado) && filled($encontrado)) {
            $customer = $encontrado[0] ?? $encontrado;
            $customerId = (int) ($customer['customer_id'] ?? 0);

            if ($customerId > 0) {
                return $customerId;
            }
        }

        return $this->criarCliente($dados, $vat);
    }

    private function criarCliente(array $dados, ?string $vat): int
    {
        $payload = [
            'vat' => $vat ?: '999999990', // consumidor final quando nao ha NIF
            'number' => $vat ?: ('C'.substr(md5($dados['name'] ?? uniqid()), 0, 8)),
            'name' => $dados['name'] ?? 'Cliente',
            'language_id' => $dados['language_id'] ?? 1,
            'address' => $dados['address'] ?? '',
            'city' => $dados['city'] ?? '',
            'zip_code' => $this->normalizarCodigoPostal($dados['zip_code'] ?? ''),
            'country_id' => $dados['country_id'] ?? 1, // Portugal
            'email' => $dados['email'] ?? '',
            'maturity_date_id' => $dados['maturity_date_id'] ?? 0,
            'payment_method_id' => $dados['payment_method_id'] ?? 0,
            'salesman_id' => 0,
            'field_notes' => '',
        ];

        $response = $this->request('customers/insert', array_filter(
            $payload,
            fn ($value) => $value !== null,
        ));

        if ($response === null) {
            throw new RuntimeException('Sem resposta da API Moloni ao criar o cliente.');
        }

        $this->abortarSeErro($response, 'Moloni customers/insert');

        $customerId = (int) ($response['customer_id'] ?? 0);

        if ($customerId <= 0) {
            // Pode ja existir (ex.: consumidor final): tenta encontra-lo antes de falhar.
            $existente = $this->request('customers/getByVat', ['vat' => $vat ?: '999999990']);

            if (is_array($existente) && filled($existente)) {
                $customer = $existente[0] ?? $existente;
                $customerId = (int) ($customer['customer_id'] ?? 0);
            }
        }

        if ($customerId <= 0) {
            throw new RuntimeException('A API Moloni nao devolveu um customer_id valido.');
        }

        return $customerId;
    }

    // ------------------------------------------------------------------
    //  Produtos (catalogo)
    // ------------------------------------------------------------------

    /**
     * Devolve o product_id de um produto pela referencia, criando-o se nao existir.
     */
    public function obterOuCriarProduto(string $referencia, string $nome, float $preco, ?int $taxId = null): int
    {
        $referencia = trim($referencia);

        $existente = $this->procurarProdutoPorReferencia($referencia);

        if ($existente > 0) {
            return $existente;
        }

        return $this->criarProduto($referencia, $nome, $preco, $taxId);
    }

    /**
     * product_id de um produto EXISTENTE pela referencia (sem o criar).
     * Devolve 0 se nao existir. Usado para reutilizar o artigo composto do cabaz.
     */
    public function idProdutoPorReferencia(string $referencia): int
    {
        return $this->procurarProdutoPorReferencia(trim($referencia));
    }

    /**
     * Dados de um produto EXISTENTE pela referencia (id, preco liquido, nome).
     * Devolve null se nao existir. Usado para o artigo composto do cabaz.
     *
     * @return array{product_id:int,price:float,name:string}|null
     */
    public function produtoPorReferencia(string $referencia): ?array
    {
        $referencia = trim($referencia);

        if ($referencia === '') {
            return null;
        }

        $encontrado = $this->request('products/getBySearch', ['search' => $referencia]);

        if (is_array($encontrado)) {
            foreach ($encontrado as $produto) {
                if (is_array($produto) && $this->referenciaCorresponde((string) ($produto['reference'] ?? ''), $referencia)) {
                    $id = (int) ($produto['product_id'] ?? 0);

                    if ($id > 0) {
                        return [
                            'product_id' => $id,
                            'price' => $this->precoArtigo($id, (float) ($produto['price'] ?? 0)),
                            'name' => (string) ($produto['name'] ?? ''),
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Preco unitario (liquido) de um artigo. Os artigos COMPOSTOS costumam vir
     * com price = 0 no products/getBySearch (o preco deles e derivado da
     * composicao) e o Moloni depois RECUSA a linha com outro preco:
     * "Field 'price' must be float, greater or equal to X, lesser or equal to X".
     * Por isso, quando o preco vem a zero, tenta o products/getOne e, em ultimo
     * caso, soma a composicao (qty x preco de cada filho).
     */
    public function precoArtigo(int $productId, float $precoConhecido = 0.0): float
    {
        if ($precoConhecido > 0) {
            return round($precoConhecido, 4);
        }

        if ($productId <= 0) {
            return 0.0;
        }

        return Cache::remember("moloni.preco.{$productId}", now()->addMinutes(30), function () use ($productId): float {
            $detalhe = $this->artigo($productId);

            foreach (['price', 'price_with_taxes', 'composition_price'] as $campo) {
                $valor = (float) ($detalhe[$campo] ?? 0);

                if ($valor > 0) {
                    return round($valor, 4);
                }
            }

            // Artigo composto sem preco proprio: preco = soma da composicao.
            $total = 0.0;

            foreach ($this->componentesComposto($productId) as $filho) {
                $total += (float) $filho['qty'] * (float) $filho['price'];
            }

            return round($total, 4);
        });
    }

    /**
     * Data do ultimo documento (fatura) emitido a um cliente no Moloni. Usada
     * para calcular o proximo ciclo de faturacao (ultima data + 4 semanas).
     */
    public function ultimaFaturaData(int $customerId): ?\Illuminate\Support\Carbon
    {
        if ($customerId <= 0) {
            return null;
        }

        $resposta = $this->request('invoices/getAll', [
            'customer_id' => $customerId,
            'qty' => 50,
            'offset' => 0,
        ]);

        if (! is_array($resposta)) {
            return null;
        }

        $ultima = null;

        foreach ($resposta as $doc) {
            $data = is_array($doc) ? ($doc['date'] ?? null) : null;

            if (! is_string($data) || $data === '') {
                continue;
            }

            try {
                $dt = \Illuminate\Support\Carbon::parse($data);
            } catch (\Throwable) {
                continue;
            }

            if ($ultima === null || $dt->gt($ultima)) {
                $ultima = $dt;
            }
        }

        return $ultima;
    }

    /** Procura um product_id pela referencia (comparacao sem distincao de maiusculas). */
    private function procurarProdutoPorReferencia(string $referencia): int
    {
        if ($referencia === '') {
            return 0;
        }

        $encontrado = $this->request('products/getBySearch', ['search' => $referencia]);

        if (is_array($encontrado)) {
            foreach ($encontrado as $produto) {
                if (is_array($produto) && $this->referenciaCorresponde((string) ($produto['reference'] ?? ''), $referencia)) {
                    $id = (int) ($produto['product_id'] ?? 0);

                    if ($id > 0) {
                        return $id;
                    }
                }
            }
        }

        return 0;
    }

    /**
     * Compara a referencia de um produto Moloni com a procurada. Aceita match
     * exato ou com o sufixo "-0" (a linha-pai de um artigo composto aparece
     * como REF-0 mas o artigo e criado/referido por REF).
     */
    private function referenciaCorresponde(string $referenciaProduto, string $referencia): bool
    {
        $referenciaProduto = trim($referenciaProduto);
        $referencia = trim($referencia);

        if ($referencia === '') {
            return false;
        }

        return strcasecmp($referenciaProduto, $referencia) === 0
            || strcasecmp($referenciaProduto, $referencia.'-0') === 0;
    }

    private function criarProduto(string $referencia, string $nome, float $preco, ?int $taxId): int
    {
        $taxId ??= $this->taxId();

        $payload = [
            'category_id' => $this->categoryId(),
            'type' => 1, // Produto
            'name' => $nome,
            'reference' => $referencia !== '' ? $referencia : mb_substr($nome, 0, 30),
            'price' => round($preco, 4),
            'unit_id' => $this->unitId(),
            'has_stock' => 0,
            'exemption_reason' => $taxId === null ? $this->configValue('exemption_reason') : null,
        ];

        if ($taxId !== null) {
            $payload['taxes'] = [[
                'tax_id' => $taxId,
                'value' => $this->configValue('default_tax_value') ?? 6,
                'order' => 1,
                'cumulative' => 0,
            ]];
        }

        $response = $this->request('products/insert', array_filter(
            $payload,
            fn ($value) => $value !== null,
        ));

        if ($response === null) {
            throw new RuntimeException('Sem resposta da API Moloni ao criar o produto.');
        }

        $this->abortarSeErro($response, 'Moloni products/insert');

        $productId = (int) ($response['product_id'] ?? 0);

        if ($productId <= 0) {
            // Pode ja existir com esta referencia: tenta encontra-lo antes de falhar.
            $productId = $this->procurarProdutoPorReferencia((string) ($payload['reference'] ?? $referencia));
        }

        if ($productId <= 0) {
            throw new RuntimeException('A API Moloni nao devolveu um product_id valido.');
        }

        return $productId;
    }

    // ------------------------------------------------------------------
    //  Metadados fiscais / catalogo (resolvidos e cacheados)
    // ------------------------------------------------------------------

    /**
     * Id do imposto (IVA) a usar. Preferencia: config; senao resolve pela taxes/getAll.
     */
    public function taxId(): ?int
    {
        $configured = $this->configValue('tax_id');

        if (filled($configured)) {
            return (int) $configured;
        }

        return Cache::remember('moloni.tax_id', now()->addHours(6), function (): ?int {
            $alvo = (float) ($this->configValue('default_tax_value') ?? 6);
            $taxes = $this->request('taxes/getAll', []);

            if (! is_array($taxes)) {
                return null;
            }

            foreach ($taxes as $tax) {
                if (is_array($tax) && abs(((float) ($tax['value'] ?? -1)) - $alvo) < 0.001) {
                    return ((int) ($tax['tax_id'] ?? 0)) ?: null;
                }
            }

            return null;
        });
    }

    /**
     * tax_id de uma taxa de IVA especifica (ex.: 23 para os portes). Resolve
     * pela taxes/getAll e guarda em cache.
     */
    public function taxIdPorValor(float $valor): ?int
    {
        return Cache::remember('moloni.tax_id.'.str_replace('.', '_', (string) $valor), now()->addHours(6), function () use ($valor): ?int {
            $taxes = $this->request('taxes/getAll', []);

            if (! is_array($taxes)) {
                return null;
            }

            foreach ($taxes as $tax) {
                if (is_array($tax) && abs(((float) ($tax['value'] ?? -1)) - $valor) < 0.001) {
                    return ((int) ($tax['tax_id'] ?? 0)) ?: null;
                }
            }

            return null;
        });
    }

    public function documentSetId(string $tipo): ?int
    {
        $chave = match ($tipo) {
            'fatura_recibo' => 'document_set_id_fatura_recibo',
            'guia' => 'document_set_id_guia',
            default => 'document_set_id_fatura',
        };
        $valor = $this->configValue($chave);

        return filled($valor) ? (int) $valor : null;
    }

    public function unitId(): ?int
    {
        $configured = $this->configValue('unit_id');

        if (filled($configured)) {
            return (int) $configured;
        }

        return Cache::remember('moloni.unit_id', now()->addHours(6), function (): ?int {
            $unidades = $this->request('measurementUnits/getAll', []);

            if (is_array($unidades) && isset($unidades[0]['unit_id'])) {
                return (int) $unidades[0]['unit_id'];
            }

            return null;
        });
    }

    public function categoryId(): ?int
    {
        $configured = $this->configValue('category_id');

        if (filled($configured)) {
            return (int) $configured;
        }

        return Cache::remember('moloni.category_id', now()->addHours(6), function (): ?int {
            $categorias = $this->request('productCategories/getAll', ['parent_id' => 0]);

            if (is_array($categorias) && isset($categorias[0]['category_id'])) {
                return (int) $categorias[0]['category_id'];
            }

            return null;
        });
    }

    public function paymentMethodId(): ?int
    {
        $valor = $this->configValue('payment_method_id');

        if (filled($valor)) {
            return (int) $valor;
        }

        // Se nao configurado, resolve automaticamente o primeiro metodo de pagamento.
        return Cache::remember('moloni.payment_method_id', now()->addHours(6), function (): ?int {
            $metodos = $this->request('paymentMethods/getAll', []);

            if (is_array($metodos) && isset($metodos[0]['payment_method_id'])) {
                return (int) $metodos[0]['payment_method_id'];
            }

            return null;
        });
    }

    // ------------------------------------------------------------------
    //  Listagens (para o comando de configuracao moloni:setup)
    // ------------------------------------------------------------------

    /** Empresas/contas acessiveis com estas credenciais (companies/getAll). */
    public function listarContas(): array
    {
        return $this->request('companies/getAll', [], false) ?? [];
    }

    /** Series de documentos da empresa (documentSets/getAll). */
    public function listarSeries(): array
    {
        return $this->request('documentSets/getAll', []) ?? [];
    }

    /** Metodos de pagamento (paymentMethods/getAll). */
    public function listarMetodosPagamento(): array
    {
        return $this->request('paymentMethods/getAll', []) ?? [];
    }

    /** Impostos/IVA (taxes/getAll). */
    public function listarTaxas(): array
    {
        return $this->request('taxes/getAll', []) ?? [];
    }

    // ------------------------------------------------------------------
    //  HTTP / infra
    // ------------------------------------------------------------------

    /**
     * Faz um POST autenticado a API Moloni v2 e devolve o corpo JSON como array.
     */
    private function request(string $endpoint, array $payload, bool $comEmpresa = true): ?array
    {
        $accessToken = $this->accessToken();

        if (blank($accessToken)) {
            Log::warning('Moloni: sem access token — verifica as credenciais no .env.');

            return null;
        }

        $companyId = $this->configValue('company_id');

        if ($comEmpresa && filled($companyId) && ! array_key_exists('company_id', $payload)) {
            $payload['company_id'] = (int) $companyId;
        }

        $url = self::BASE_URL.'/'.trim($endpoint, '/').'/?'.http_build_query([
            'human_errors' => 'true',
            'access_token' => $accessToken,
        ]);

        try {
            // Moloni aceita form-encoded com arrays aninhados (products[0][taxes][0][tax_id]).
            $response = Http::asForm()
                ->timeout(30)
                ->retry(2, 500, throw: false)
                ->post($url, $payload);
        } catch (Throwable $exception) {
            Log::error('Moloni request falhou: '.$exception->getMessage(), ['endpoint' => $endpoint]);

            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    /**
     * Lanca excecao se a resposta Moloni contiver erros (formato human_errors).
     */
    private function abortarSeErro(array $response, string $contexto): void
    {
        // Respostas de erro do Moloni trazem a chave "errors" ou {valid:0,...}.
        if (isset($response['errors']) && filled($response['errors'])) {
            $mensagem = collect((array) $response['errors'])
                ->flatten()
                ->filter(fn ($m) => is_string($m))
                ->implode('; ');

            throw new RuntimeException("{$contexto}: ".($mensagem ?: 'erro desconhecido'));
        }

        if (isset($response['valid']) && (int) $response['valid'] === 0) {
            throw new RuntimeException("{$contexto}: pedido invalido (valid=0).");
        }
    }

    private function accessToken(): ?string
    {
        $staticToken = $this->configValue('access_token');

        if (filled($staticToken)) {
            return (string) $staticToken;
        }

        $developerId = $this->configValue('developer_id');
        $clientSecret = $this->configValue('client_secret');
        $username = $this->configValue('username');
        $password = $this->configValue('password');

        if (blank($developerId) || blank($clientSecret) || blank($username) || blank($password)) {
            return null;
        }

        return Cache::remember('moloni.access_token', now()->addMinutes(45), function () use ($developerId, $clientSecret, $username, $password): ?string {
            $url = self::BASE_URL.'/grant/?'.http_build_query([
                'grant_type' => 'password',
                'client_id' => $developerId,
                'client_secret' => $clientSecret,
                'username' => $username,
                'password' => $password,
            ]);

            try {
                $response = Http::timeout(20)
                    ->retry(2, 500, throw: false)
                    ->get($url);
            } catch (Throwable) {
                return null;
            }

            if ($response->failed()) {
                return null;
            }

            $accessToken = $response->json('access_token');

            return filled($accessToken) ? (string) $accessToken : null;
        });
    }

    private function normalizarNif(?string $vat): ?string
    {
        $vat = preg_replace('/\s+/', '', (string) $vat);

        return filled($vat) ? $vat : null;
    }

    private function normalizarCodigoPostal(string $zip): string
    {
        // Moloni aceita codigos postais no formato XXXX-XXX (PT).
        return trim($zip);
    }

    private function configValue(string $key): ?string
    {
        $value = config("moloni.{$key}");

        if (filled($value)) {
            return (string) $value;
        }

        $envKey = 'MOLONI_'.strtoupper($key);
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            return null;
        }

        foreach (File::lines($envPath) as $line) {
            $line = trim($line);

            if (! str_starts_with($line, $envKey.'=')) {
                continue;
            }

            $raw = trim(substr($line, strlen($envKey) + 1));

            return trim($raw, "\"'");
        }

        return null;
    }
}
