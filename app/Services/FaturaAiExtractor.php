<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FaturaAiExtractor
{
    public function extract(UploadedFile $file): array
    {
        $apiKey = config('services.openai.api_key');

        if (blank($apiKey)) {
            throw new RuntimeException('Configure OPENAI_API_KEY no .env para usar a extracao por IA.');
        }

        $mime = $file->getMimeType() ?: 'image/jpeg';
        $imageData = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($file->getRealPath()));

        $response = Http::withToken($apiKey)
            ->timeout(90)
            ->acceptJson()
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.model', 'gpt-5.5'),
                'input' => [[
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $this->prompt(),
                        ],
                        [
                            'type' => 'input_image',
                            'image_url' => $imageData,
                            'detail' => 'high',
                        ],
                    ],
                ]],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'fatura_extraida',
                        'strict' => true,
                        'schema' => $this->schema(),
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException($this->errorMessage($response->json(), $response->status()));
        }

        $json = $this->extractOutputText($response->json());
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw new RuntimeException('A IA nao devolveu um JSON valido.');
        }

        return $data;
    }

    private function prompt(): string
    {
        return <<<'PROMPT'
Extrai os dados desta fatura/recibo portuguesa para JSON.
Usa apenas informacao visivel na imagem. Nao inventes campos.
Os valores monetarios devem ser numeros em EUR, sem simbolo.
Para cada linha de produto, devolve:
- descricao
- quantidade original da fatura
- unidade_compra: kg, g, un, cx, emb, molho ou outro
- unidades_por_quantidade: quantas unidades vendiveis existem por cada quantidade comprada; se nao souberes, usa 1
- quantidade_unidades: quantidade * unidades_por_quantidade; se nao souberes, usa a quantidade
- preco_unitario sem IVA quando a fatura o permitir; se so existir total da linha, calcula pelo melhor valor visivel
- iva_percentagem: 0, 6, 13 ou 23; se nao estiver claro, usa 23
PROMPT;
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['titulo', 'numero_fatura', 'fornecedor', 'data', 'valor', 'items'],
            'properties' => [
                'titulo' => ['type' => 'string'],
                'numero_fatura' => ['type' => 'string'],
                'fornecedor' => ['type' => 'string'],
                'data' => ['type' => 'string', 'description' => 'Data no formato YYYY-MM-DD ou vazio.'],
                'valor' => ['type' => 'number'],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['descricao', 'quantidade', 'unidade_compra', 'unidades_por_quantidade', 'quantidade_unidades', 'preco_unitario', 'iva_percentagem', 'notas'],
                        'properties' => [
                            'descricao' => ['type' => 'string'],
                            'quantidade' => ['type' => 'number'],
                            'unidade_compra' => ['type' => 'string'],
                            'unidades_por_quantidade' => ['type' => 'number'],
                            'quantidade_unidades' => ['type' => 'number'],
                            'preco_unitario' => ['type' => 'number'],
                            'iva_percentagem' => ['type' => 'number'],
                            'notas' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function extractOutputText(array $response): string
    {
        if (isset($response['output_text']) && is_string($response['output_text'])) {
            return $response['output_text'];
        }

        foreach ($response['output'] ?? [] as $item) {
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text' && isset($content['text'])) {
                    return (string) $content['text'];
                }
            }
        }

        throw new RuntimeException('A resposta da IA veio sem texto extraido.');
    }

    private function errorMessage(?array $body, int $status): string
    {
        $code = $body['error']['code'] ?? null;
        $message = $body['error']['message'] ?? null;

        if ($code === 'insufficient_quota') {
            return 'A conta OpenAI nao tem quota/credito disponivel para usar a API. Verifique o billing da plataforma OpenAI e tente novamente.';
        }

        if ($status === 401) {
            return 'A chave OPENAI_API_KEY nao foi aceite. Confirme se a chave esta correta e ativa.';
        }

        if ($status === 429) {
            return 'A OpenAI recusou o pedido por limite de uso. Tente novamente mais tarde ou verifique os limites da conta.';
        }

        return 'A extracao por IA falhou'.($message ? ': '.$message : '.');
    }
}
