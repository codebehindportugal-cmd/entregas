<?php

return [
    // Quantas semanas para a frente se geram datas de entrega das subscricoes
    // quando nao ha um numero de entregas por ciclo definido. Tem de cobrir com
    // folga o periodo que se ve na preparacao.
    'horizonte_subscricao_semanas' => (int) env('ENTREGAS_HORIZONTE_SUBSCRICAO_SEMANAS', 8),

    // Quantas entregas tem uma subscricao. Sao sempre 4: semanal dura 4 semanas,
    // de 15 em 15 dias dura 8. No fim, ou se renova ou acaba.
    'entregas_por_subscricao' => (int) env('ENTREGAS_POR_SUBSCRICAO', 4),

    // Ate quantos dias depois da ultima entrega a renovacao automatica ainda
    // pode ser criada. Evita que subscricoes antigas gerem renovacoes de repente.
    'janela_renovacao_dias' => (int) env('ENTREGAS_JANELA_RENOVACAO_DIAS', 7),
];
