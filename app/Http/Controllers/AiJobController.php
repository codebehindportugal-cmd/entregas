<?php

namespace App\Http\Controllers;

use App\Models\AiJob;
use App\Models\Despesa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiJobController extends Controller
{
    public function pendingJobs(): JsonResponse
    {
        $jobs = AiJob::where('status', 'pending')
            ->get()
            ->map(fn (AiJob $job) => [
                'id' => $job->id,
                'despesa_id' => $job->despesa_id,
                'image_url' => $job->image_url,
            ])
            ->values();

        return response()->json($jobs);
    }

    public function jobResult(Request $request): JsonResponse
    {
        $data = $request->validate([
            'job_id' => ['required', 'integer', 'exists:ai_jobs,id'],
            'products' => ['present', 'array'],
            'products.*.nome' => ['required', 'string', 'max:255'],
            'products.*.quantidade' => ['required', 'numeric', 'min:0'],
            'products.*.preco_unitario' => ['required', 'numeric', 'min:0'],
            'products.*.unidade' => ['nullable', 'string', 'max:20'],
        ]);

        $job = AiJob::findOrFail($data['job_id']);

        if ($job->status !== 'pending') {
            return response()->json(['error' => 'Job já processado.'], 409);
        }

        try {
            DB::transaction(function () use ($job, $data): void {
                $despesa = Despesa::findOrFail($job->despesa_id);
                $products = $data['products'];

                if (count($products) > 0) {
                    foreach ($products as $product) {
                        $despesa->items()->create([
                            'descricao' => $product['nome'],
                            'quantidade' => $product['quantidade'],
                            'unidade_compra' => $product['unidade'] ?? 'un',
                            'unidades_por_quantidade' => 1,
                            'quantidade_unidades' => $product['quantidade'],
                            'preco_unitario' => $product['preco_unitario'],
                            'iva_percentagem' => 23,
                            'notas' => null,
                        ]);
                    }

                    $despesa->load('items');
                    $novoValor = $despesa->items->sum(
                        fn ($item) => round((float) $item->quantidade * (float) $item->preco_unitario * (1 + (float) $item->iva_percentagem / 100), 4)
                    );
                    $despesa->update(['valor' => $novoValor]);
                }

                $job->update([
                    'status' => 'done',
                    'result' => json_encode($products),
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('AiJob falhou ao processar resultado', ['job_id' => $job->id, 'error' => $e->getMessage()]);
            $job->update(['status' => 'failed']);

            return response()->json(['error' => 'Erro interno ao processar job.'], 500);
        }

        return response()->json(['ok' => true]);
    }
}
