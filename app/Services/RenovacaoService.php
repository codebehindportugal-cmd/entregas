<?php

namespace App\Services;

use App\Models\WooOrder;
use RuntimeException;

class RenovacaoService
{
    public function __construct(private readonly WooCommerceService $woocommerce) {}

    /**
     * Cria no WooCommerce a encomenda de renovacao da subscricao, em pagamento
     * pendente, e guarda-a na subscricao antiga. O link de pagamento sai depois
     * no botao de WhatsApp (WooOrder::whatsappPagamentoUrl).
     */
    public function renovar(WooOrder $subscricao): WooOrder
    {
        if ($subscricao->renovada_em !== null) {
            throw new RuntimeException('Esta subscricao ja foi renovada.');
        }

        $resultado = $this->woocommerce->createPendingOrderFrom($subscricao);

        /** @var WooOrder $nova */
        $nova = $resultado['order'];

        $subscricao->marcarRenovacaoCriada($nova);

        return $nova;
    }
}
