<?php

return [

    /*
    | Avisos no telemovel (https://ntfy.sh). Sem NTFY_TOPIC nao se envia nada.
    | Usar o mesmo topico do gestao.ateneya.com para chegar tudo a mesma app.
    */

    'enabled' => (bool) env('NTFY_ENABLED', true),

    'url' => rtrim((string) env('NTFY_URL', 'https://ntfy.sh'), '/'),

    'topic' => env('NTFY_TOPIC'),

    'token' => env('NTFY_TOKEN'),

    'timeout' => (int) env('NTFY_TIMEOUT', 8),

    'avisos' => [
        'sincronizacao' => (bool) env('NTFY_AVISA_SYNC', true),
        'renovacoes'    => (bool) env('NTFY_AVISA_RENOVACOES', true),
        'fim_subscricoes' => (bool) env('NTFY_AVISA_FIM_SUBSCRICOES', true),
        'pedidos' => (bool) env('NTFY_AVISA_PEDIDOS', true),
    ],

];
