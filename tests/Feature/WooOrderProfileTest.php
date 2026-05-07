<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WooOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WooOrderProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('O driver pdo_sqlite nao esta instalado neste ambiente.');
        }

        parent::setUp();
    }

    public function test_admin_can_update_b2c_customer_profile_data(): void
    {
        $admin = User::factory()->admin()->create();
        $order = WooOrder::factory()->create([
            'billing_name' => 'Cliente Antigo',
            'billing_phone' => '910000000',
            'billing_email' => 'antigo@example.test',
            'source_type' => 'order',
            'dia_entrega' => 'quarta',
            'ciclo_entrega' => 'semanal',
        ]);

        $response = $this->actingAs($admin)->put(route('encomendas.profile.update', $order), [
            'billing_name' => 'Cliente Novo',
            'billing_phone' => '919999999',
            'billing_email' => 'novo@example.test',
            'source_type' => 'subscription',
            'dia_entrega' => 'segunda',
            'ciclo_entrega' => 'quinzenal',
            'scheduled_delivery_at' => '2026-05-09',
            'first_delivery_at' => '2026-05-09',
            'next_payment_at' => '2026-06-09',
            'subscription_ends_at' => '2026-07-09',
            'profile_preferences' => 'Sem banana.',
            'customer_notes' => 'Cliente prefere contacto por SMS.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Perfil do cliente atualizado.');

        $this->assertDatabaseHas('woo_orders', [
            'id' => $order->id,
            'billing_name' => 'Cliente Novo',
            'billing_phone' => '919999999',
            'billing_email' => 'novo@example.test',
            'source_type' => 'subscription',
            'dia_entrega' => 'segunda',
            'ciclo_entrega' => 'quinzenal',
            'scheduled_delivery_at' => '2026-05-09',
            'first_delivery_at' => '2026-05-09',
            'next_payment_at' => '2026-06-09',
            'subscription_ends_at' => '2026-07-09',
            'profile_preferences' => 'Sem banana.',
            'customer_notes' => 'Cliente prefere contacto por SMS.',
        ]);
    }
}
