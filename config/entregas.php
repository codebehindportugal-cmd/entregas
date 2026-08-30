<?php

return [
    // Quantas semanas para a frente se geram datas de entrega das subscricoes
    // que nao tem data de fim. Tem de cobrir com folga o periodo que se ve na
    // preparacao; se for curto, as subscricoes deixam de aparecer.
    'horizonte_subscricao_semanas' => (int) env('ENTREGAS_HORIZONTE_SUBSCRICAO_SEMANAS', 8),
];
