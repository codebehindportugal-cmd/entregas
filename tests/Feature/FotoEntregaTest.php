<?php

namespace Tests\Feature;

use App\Models\RegistoEntrega;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FotoEntregaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('O driver pdo_sqlite nao esta instalado neste ambiente.');
        }

        parent::setUp();

        Storage::fake('public');
    }

    public function test_colaborador_uploads_jpeg_and_png_successfully(): void
    {
        $user = User::factory()->create();
        $registo = RegistoEntrega::factory()->for($user)->create();

        $this->actingAs($user)
            ->put(route('minhas-entregas.update', $registo), [
                'status' => 'entregue',
                'nota' => 'Fotos entregues.',
                'fotos' => [
                    UploadedFile::fake()->image('entrega.jpg'),
                    UploadedFile::fake()->image('entrega.png'),
                ],
            ])
            ->assertRedirect(route('minhas-entregas.show', $registo));

        $fotos = $registo->refresh()->fotos;

        $this->assertCount(2, $fotos);
        $this->assertStringEndsWith('.jpg', $fotos[0]);
        $this->assertStringEndsWith('.png', $fotos[1]);
        Storage::disk('public')->assertExists($fotos[0]);
        Storage::disk('public')->assertExists($fotos[1]);
    }

    public function test_rejects_files_that_are_not_images(): void
    {
        $user = User::factory()->create();
        $registo = RegistoEntrega::factory()->for($user)->create();

        $this->actingAs($user)
            ->from(route('minhas-entregas.show', $registo))
            ->put(route('minhas-entregas.update', $registo), [
                'status' => 'entregue',
                'nota' => null,
                'fotos' => [
                    UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'),
                ],
            ])
            ->assertRedirect(route('minhas-entregas.show', $registo))
            ->assertSessionHasErrors('fotos.0');

        $this->assertSame([], $registo->refresh()->fotos);
    }

    public function test_rejects_more_than_six_photos_in_one_upload(): void
    {
        $user = User::factory()->create();
        $registo = RegistoEntrega::factory()->for($user)->create();

        $this->actingAs($user)
            ->from(route('minhas-entregas.show', $registo))
            ->put(route('minhas-entregas.update', $registo), [
                'status' => 'entregue',
                'nota' => null,
                'fotos' => collect(range(1, 7))
                    ->map(fn (int $index): UploadedFile => UploadedFile::fake()->image("foto-{$index}.jpg"))
                    ->all(),
            ])
            ->assertRedirect(route('minhas-entregas.show', $registo))
            ->assertSessionHasErrors('fotos');
    }

    public function test_total_photos_are_limited_to_six_when_existing_photos_are_present(): void
    {
        $user = User::factory()->create();
        $existing = $this->storedPhotos(5);
        $registo = RegistoEntrega::factory()->for($user)->create(['fotos' => $existing]);

        $this->actingAs($user)
            ->put(route('minhas-entregas.update', $registo), [
                'status' => 'entregue',
                'nota' => null,
                'fotos' => [
                    UploadedFile::fake()->image('nova-1.jpg'),
                    UploadedFile::fake()->image('nova-2.jpg'),
                ],
            ])
            ->assertRedirect(route('minhas-entregas.show', $registo));

        $fotos = $registo->refresh()->fotos;

        $this->assertCount(6, $fotos);
        $this->assertSame($existing, array_slice($fotos, 0, 5));
    }

    public function test_rejects_photos_larger_than_six_megabytes(): void
    {
        $user = User::factory()->create();
        $registo = RegistoEntrega::factory()->for($user)->create();

        $this->actingAs($user)
            ->from(route('minhas-entregas.show', $registo))
            ->put(route('minhas-entregas.update', $registo), [
                'status' => 'entregue',
                'nota' => null,
                'fotos' => [
                    UploadedFile::fake()->create('grande.jpg', 6145, 'image/jpeg'),
                ],
            ])
            ->assertRedirect(route('minhas-entregas.show', $registo))
            ->assertSessionHasErrors('fotos.0');
    }

    public function test_colaborador_removes_own_photo_and_file_is_deleted_from_storage(): void
    {
        $user = User::factory()->create();
        $fotos = $this->storedPhotos(1);
        $registo = RegistoEntrega::factory()->for($user)->create(['fotos' => $fotos]);

        $this->actingAs($user)
            ->delete(route('minhas-entregas.fotos.destroy', [$registo, 0]))
            ->assertRedirect(route('minhas-entregas.show', $registo));

        $this->assertSame([], $registo->refresh()->fotos);
        Storage::disk('public')->assertMissing($fotos[0]);
    }

    public function test_colaborador_cannot_remove_photo_from_another_colaborador(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $fotos = $this->storedPhotos(1);
        $registo = RegistoEntrega::factory()->for($owner)->create(['fotos' => $fotos]);

        $this->actingAs($other)
            ->delete(route('minhas-entregas.fotos.destroy', [$registo, 0]))
            ->assertForbidden();

        $this->assertSame($fotos, $registo->refresh()->fotos);
        Storage::disk('public')->assertExists($fotos[0]);
    }

    public function test_admin_can_remove_photo_from_any_colaborador(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $fotos = $this->storedPhotos(1);
        $registo = RegistoEntrega::factory()->for($owner)->create(['fotos' => $fotos]);

        $this->actingAs($admin)
            ->delete(route('minhas-entregas.fotos.destroy', [$registo, 0]))
            ->assertRedirect(route('minhas-entregas.show', $registo));

        $this->assertSame([], $registo->refresh()->fotos);
        Storage::disk('public')->assertMissing($fotos[0]);
    }

    public function test_invalid_photo_index_returns_not_found(): void
    {
        $user = User::factory()->create();
        $registo = RegistoEntrega::factory()->for($user)->create(['fotos' => $this->storedPhotos(1)]);

        $this->actingAs($user)
            ->delete(route('minhas-entregas.fotos.destroy', [$registo, 9]))
            ->assertNotFound();
    }

    public function test_removing_middle_photo_reindexes_photo_array(): void
    {
        $user = User::factory()->create();
        $fotos = $this->storedPhotos(3);
        $registo = RegistoEntrega::factory()->for($user)->create(['fotos' => $fotos]);

        $this->actingAs($user)
            ->delete(route('minhas-entregas.fotos.destroy', [$registo, 1]))
            ->assertRedirect(route('minhas-entregas.show', $registo));

        $registo->refresh();

        $this->assertSame([0, 1], array_keys($registo->fotos));
        $this->assertSame([$fotos[0], $fotos[2]], $registo->fotos);
        Storage::disk('public')->assertMissing($fotos[1]);
    }

    public function test_colaborador_cannot_edit_delivery_from_another_colaborador(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $registo = RegistoEntrega::factory()->for($owner)->create();

        $this->actingAs($other)
            ->put(route('minhas-entregas.update', $registo), [
                'status' => 'entregue',
                'nota' => 'Tentativa indevida.',
            ])
            ->assertForbidden();

        $this->assertSame('pendente', $registo->refresh()->status);
    }

    private function storedPhotos(int $count): array
    {
        return collect(range(1, $count))
            ->map(function (int $index): string {
                $path = "entregas/2026/06/1/foto-{$index}.jpg";

                Storage::disk('public')->put($path, 'fake image contents');

                return $path;
            })
            ->all();
    }
}
