<?php

namespace Tests\Feature;

use App\Models\Corporate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CorporateImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('O driver pdo_sqlite nao esta instalado neste ambiente.');
        }

        parent::setUp();
    }

    public function test_corporates_can_be_exported_to_json(): void
    {
        $corporate = Corporate::factory()->create([
            'empresa' => 'Horta Teste',
            'sucursal' => 'Lisboa',
            'dias_entrega' => ['Segunda', 'Quarta'],
            'periodicidade_entrega' => 'quinzenal',
            'quinzenal_referencia' => '2026-04-27',
            'frutas_por_dia' => [
                'Segunda' => [
                    'banana' => 5,
                    'maca' => 4,
                    'pera' => 3,
                    'laranja' => 2,
                    'kiwi' => 1,
                    'uvas' => 1.5,
                    'fruta_epoca' => 6,
                ],
            ],
        ]);
        $path = storage_path('app/testing/corporates-export.json');

        File::delete($path);

        $this->artisan('corporates:export', ['path' => $path])
            ->assertExitCode(0);

        $payload = json_decode(File::get($path), true);

        $this->assertSame(1, $payload['count']);
        $this->assertSame($corporate->empresa, $payload['corporates'][0]['empresa']);
        $this->assertSame(['Segunda', 'Quarta'], $payload['corporates'][0]['dias_entrega']);
        $this->assertSame('2026-04-27', $payload['corporates'][0]['quinzenal_referencia']);
        $this->assertSame(1.5, $payload['corporates'][0]['frutas_por_dia']['Segunda']['uvas']);
    }

    public function test_corporates_can_be_imported_and_updated_from_json(): void
    {
        Corporate::factory()->create([
            'empresa' => 'Empresa Existente',
            'sucursal' => null,
            'numero_caixas' => 1,
            'ativo' => true,
        ]);
        $path = storage_path('app/testing/corporates-import.json');

        $this->writeImportFile($path, [
            [
                'empresa' => 'Empresa Existente',
                'sucursal' => '',
                'dias_entrega' => ['Terca'],
                'numero_caixas' => 4,
                'peso_total' => 12.5,
                'frutas' => ['banana' => 10, 'uvas' => 2.75],
                'ativo' => false,
            ],
            [
                'empresa' => 'Empresa Nova',
                'sucursal' => 'Porto',
                'dias_entrega' => ['Quinta'],
                'periodicidade_entrega' => 'quinzenal',
                'quinzenal_referencia' => '2026-05-04',
                'fatura_email' => 'fatura@example.test',
                'numero_caixas' => 2,
                'peso_total' => 8,
                'frutas_por_dia' => [
                    'Quinta' => ['maca' => 6, 'uvas' => 1.25],
                ],
            ],
        ]);

        $this->artisan('corporates:import', ['path' => $path])
            ->expectsOutput('Atualizar Empresa Existente')
            ->expectsOutput('Criar Empresa Nova - Porto')
            ->assertExitCode(0);

        $this->assertDatabaseCount('corporates', 2);
        $this->assertDatabaseHas('corporates', [
            'empresa' => 'Empresa Existente',
            'sucursal' => null,
            'numero_caixas' => 4,
            'ativo' => false,
        ]);
        $this->assertDatabaseHas('corporates', [
            'empresa' => 'Empresa Nova',
            'sucursal' => 'Porto',
            'periodicidade_entrega' => 'quinzenal',
            'quinzenal_referencia' => '2026-05-04',
        ]);

        $nova = Corporate::where('empresa', 'Empresa Nova')->firstOrFail();

        $this->assertSame(1.25, $nova->frutas_por_dia['Quinta']['uvas']);
    }

    public function test_import_dry_run_does_not_write_to_database(): void
    {
        $path = storage_path('app/testing/corporates-dry-run.json');

        $this->writeImportFile($path, [
            [
                'empresa' => 'Empresa Dry Run',
                'dias_entrega' => ['Segunda'],
            ],
        ]);

        $this->artisan('corporates:import', ['path' => $path, '--dry-run' => true])
            ->expectsOutput('Criar Empresa Dry Run')
            ->expectsOutput('Dry-run: 1 criadas, 0 atualizadas.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('corporates', [
            'empresa' => 'Empresa Dry Run',
        ]);
    }

    public function test_import_fails_with_clear_line_number_for_invalid_rows(): void
    {
        $path = storage_path('app/testing/corporates-invalid.json');

        $this->writeImportFile($path, [
            [
                'empresa' => '',
                'dias_entrega' => ['Segunda'],
            ],
        ]);

        $this->artisan('corporates:import', ['path' => $path])
            ->expectsOutputToContain('Linha 1:')
            ->assertExitCode(1);

        $this->assertDatabaseCount('corporates', 0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $corporates
     */
    private function writeImportFile(string $path, array $corporates): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'exported_at' => now()->toIso8601String(),
            'count' => count($corporates),
            'corporates' => $corporates,
        ], JSON_PRETTY_PRINT));
    }
}
