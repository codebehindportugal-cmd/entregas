<?php

namespace App\Console\Commands;

use App\Models\Corporate;
use App\Models\Zona;
use App\Services\EntregasDoDia;
use App\Services\Geolocalizador;
use Illuminate\Console\Command;

/**
 * Procura onde ficam as moradas das entregas dos proximos dias (empresas e
 * B2C) que ainda nao estao localizadas, para as voltas serem organizadas pela
 * distancia real. Cada morada procura-se uma vez; corre de hora a hora e so
 * pede as que faltam (o Nominatim aceita um pedido por segundo).
 */
class GeolocalizarMoradasCommand extends Command
{
    protected $signature = 'entregas:geolocalizar {--dias=7 : Quantos dias para a frente} {--maximo=300 : Maximo de moradas novas por vez}';

    protected $description = 'Localiza (latitude/longitude) as moradas das proximas entregas para organizar as voltas';

    public function handle(Geolocalizador $geo, EntregasDoDia $entregasDoDia): int
    {
        $moradas = collect();

        Corporate::with('entregarEm')->where('ativo', true)->where('parceiro_local', false)->get()
            ->each(function (Corporate $corporate) use ($moradas): void {
                $local = $corporate->localDaVolta();
                $moradas->push([$local->moradaParaEntrega(), $local->cp_entrega, $local->cidade_entrega]);
            });

        for ($i = 0; $i < max(1, (int) $this->option('dias')); $i++) {
            $data = now()->startOfDay()->addDays($i);
            $dia = Zona::DIAS[$data->dayOfWeek] ?? null;

            if ($dia === null) {
                continue;
            }

            foreach ($entregasDoDia->encomendasB2c($dia, $data) as $order) {
                $endereco = $order->enderecoDeEntrega();
                $moradas->push([$endereco['morada'], $endereco['cp'], $endereco['localidade']]);
            }
        }

        $moradas = $moradas->unique(fn (array $m): string => mb_strtolower(implode('|', $m)))->values();
        $novas = $moradas->filter(fn (array $m): bool => $geo->guardada(...$m) === null)->take(max(1, (int) $this->option('maximo')));

        $encontradas = $novas->filter(fn (array $m): bool => $geo->coordenadas($m[0], $m[1], $m[2], true) !== null)->count();

        $this->info("{$moradas->count()} moradas; {$novas->count()} procuradas agora, {$encontradas} encontradas.");

        return self::SUCCESS;
    }
}
