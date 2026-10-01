<?php

namespace Tests\Feature;

use App\Models\Localizacao;
use App\Services\Geolocalizador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeolocalizadorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['entregas.geocoder_url' => 'https://nominatim.test']);
    }

    public function test_procura_a_morada_uma_vez_e_guarda(): void
    {
        Http::fake(['nominatim.test/*' => Http::response([['lat' => '38.6977', 'lon' => '-9.3070']])]);
        $geo = app(Geolocalizador::class);

        // Ao ver a pagina nao se procura nada.
        $this->assertNull($geo->guardada('Av. Marginal 1', '2770-129', 'Paço de Arcos'));
        Http::assertNothingSent();

        $this->assertSame([38.6977, -9.307], $geo->coordenadas('Av. Marginal 1', '2770-129', 'Paço de Arcos', true));
        $this->assertSame([38.6977, -9.307], $geo->guardada('Av.  Marginal 1', '2770-129', 'Paço de Arcos'));

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'Av. Marginal 1, 2770-129 Paço de Arcos, Portugal'));
    }

    public function test_sem_resultado_pela_morada_tenta_o_codigo_postal(): void
    {
        Http::fake(['nominatim.test/*' => Http::sequence()
            ->push([])
            ->push([['lat' => '39.40', 'lon' => '-9.13']])]);

        $this->assertSame([39.4, -9.13], app(Geolocalizador::class)->coordenadas('Rua Inventada 99, 2500-001 Caldas da Rainha', null, null, true));
        $this->assertSame('codigo_postal', Localizacao::first()->fonte);
    }

    public function test_resultado_fora_de_portugal_ou_nada_fica_como_falhado(): void
    {
        Http::fake(['nominatim.test/*' => Http::response([['lat' => '40.41', 'lon' => '-3.70']])]);

        $this->assertNull(app(Geolocalizador::class)->coordenadas('Calle Mayor 1', '1050-001', 'Lisboa', true));
        $this->assertSame('falhou', Localizacao::first()->fonte);

        // Nao se volta a pedir logo a seguir.
        app(Geolocalizador::class)->coordenadas('Calle Mayor 1', '1050-001', 'Lisboa', true);
        Http::assertSentCount(2);
    }

    public function test_erro_de_rede_nao_guarda_e_tenta_depois(): void
    {
        Http::fake(['nominatim.test/*' => Http::response('erro', 503)]);

        $this->assertNull(app(Geolocalizador::class)->coordenadas('Rua A 1', '1050-001', 'Lisboa', true));
        $this->assertSame(0, Localizacao::count());
    }
}
