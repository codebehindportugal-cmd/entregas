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

    public function test_admin_can_postpone_regular_b2c_order_delivery_date(): void
    {
        $admin = User::factory()->admin()->create();
        $order = WooOrder::factory()->create([
            'source_type' => 'order',
            'status' => 'processing',
            'scheduled_delivery_at' => '2026-05-27',
        ]);

        $response = $this->actingAs($admin)->put(route('encomendas.postpone', $order), [
            'postponed_until' => '2026-05-30',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Encomenda adiada ate 30/05/2026.');

        $this->assertDatabaseHas('woo_orders', [
            'id' => $order->id,
            'postponed_until' => '2026-05-30',
            'scheduled_delivery_at' => '2026-05-30',
        ]);
    }

    public function test_admin_can_postpone_regular_b2c_order_more_than_once_and_restore_original_date(): void
    {
        $admin = User::factory()->admin()->create();
        $order = WooOrder::factory()->create([
            'source_type' => 'order',
            'status' => 'processing',
            'scheduled_delivery_at' => '2026-05-27',
        ]);

        $this->actingAs($admin)->put(route('encomendas.postpone', $order), [
            'postponed_until' => '2026-05-30',
        ])->assertRedirect();

        $this->actingAs($admin)->put(route('encomendas.postpone', $order), [
            'postponed_until' => '2026-06-03',
        ])->assertRedirect();

        $order->refresh();

        $this->assertSame('2026-06-03', $order->postponed_until->toDateString());
        $this->assertSame('2026-06-03', $order->scheduled_delivery_at->toDateString());
        $this->assertSame([
            ['from' => '2026-05-27', 'to' => '2026-05-30'],
            ['from' => '2026-05-30', 'to' => '2026-06-03'],
        ], collect($order->postponement_history)->map(fn (array $item): array => [
            'from' => $item['from'],
            'to' => $item['to'],
        ])->all());

        $this->actingAs($admin)->delete(route('encomendas.postpone.clear', $order))->assertRedirect();

        $order->refresh();

        $this->assertNull($order->postponed_until);
        $this->assertSame('2026-05-27', $order->scheduled_delivery_at->toDateString());
        $this->assertSame([], $order->postponement_history);
    }

    public function test_updating_subscription_schedule_clears_delivery_dates_for_regeneration(): void
    {
        $admin = User::factory()->admin()->create();
        $order = WooOrder::factory()->create([
            'source_type' => 'subscription',
            'status' => 'active',
            'dia_entrega' => 'quarta',
            'ciclo_entrega' => 'semanal',
            'first_delivery_at' => '2026-05-06',
            'next_payment_at' => '2026-06-06',
            'subscription_ends_at' => '2026-05-27',
            'delivery_dates' => ['2026-05-06', '2026-05-13', '2026-05-20', '2026-05-27'],
        ]);

        $this->actingAs($admin)->put(route('encomendas.profile.update', $order), [
            'billing_name' => $order->billing_name,
            'billing_phone' => $order->billing_phone,
            'billing_email' => $order->billing_email,
            'customer_language' => $order->customer_language,
            'source_type' => 'subscription',
            'dia_entrega' => 'quarta',
            'ciclo_entrega' => 'quinzenal',
            'scheduled_delivery_at' => null,
            'first_delivery_at' => '2026-05-06',
            'next_payment_at' => '2026-06-06',
            'subscription_ends_at' => '2026-05-27',
            'profile_preferences' => null,
            'customer_notes' => null,
        ])->assertRedirect();

        $this->assertSame([], $order->refresh()->delivery_dates);
    }
}
