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

    public function test_rua_com_o_mesmo_nome_noutra_terra_nao_serve(): void
    {
        // "Av. do Mediterraneo 1" encontrada em Faro, mas o codigo postal e
        // do Parque das Nacoes: tenta-se sem o nome do edificio, depois o codigo postal.
        Http::fake(['nominatim.test/*' => Http::sequence()
            ->push([['lat' => '37.02', 'lon' => '-7.93']])
            ->push([['lat' => '38.765', 'lon' => '-9.096']])]);

        $this->assertSame([38.765, -9.096], app(Geolocalizador::class)->coordenadas('Av. do Mediterrâneo 1, 1990-203 Lisboa', null, null, true));
        $this->assertSame('codigo_postal', Localizacao::first()->fonte);
    }

    public function test_tira_o_nome_do_edificio_se_nao_encontrar(): void
    {
        Http::fake(['nominatim.test/*' => Http::sequence()
            ->push([])
            ->push([['lat' => '38.7137', 'lon' => '-9.2380']])]);

        $this->assertSame([38.7137, -9.238], app(Geolocalizador::class)->coordenadas('Edificio Central Park, R. Alexandre Herculano 1, 2795-240 Linda-a-Velha', null, null, true));
        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'q=R. Alexandre Herculano 1, 2795-240 Linda-a-Velha, Portugal'));
    }

    public function test_sem_codigo_postal_usa_a_localidade(): void
    {
        Http::fake(['nominatim.test/*' => Http::sequence()
            ->push([])
            ->push([['lat' => '38.569', 'lon' => '-8.901']])]);

        $this->assertSame([38.569, -8.901], app(Geolocalizador::class)->coordenadas('Rua da Prata Lote 133 - Urbanização Vale do Alecrim - Palmela.', null, null, true));
        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'q=Palmela, Portugal'));
    }

    public function test_morada_guardada_longe_do_codigo_postal_volta_a_ser_procurada(): void
    {
        $geo = app(Geolocalizador::class);
        Http::fake(['nominatim.test/*' => Http::sequence()
            ->push([['lat' => '37.02', 'lon' => '-7.93']])
            ->push([['lat' => '38.765', 'lon' => '-9.096']])]);

        // Guardada antes da verificacao: em Faro.
        Localizacao::create(['chave' => sha1(mb_strtolower('Av. do Mediterrâneo 1, 1990-203 Lisboa').'|1990-203'), 'morada' => 'x', 'cp' => '1990-203', 'lat' => 37.02, 'lng' => -7.93, 'fonte' => 'morada']);

        $this->assertNull($geo->guardada('Av. do Mediterrâneo 1, 1990-203 Lisboa', null));
        $this->assertSame([38.765, -9.096], $geo->coordenadas('Av. do Mediterrâneo 1, 1990-203 Lisboa', null, null, true));
    }
}
