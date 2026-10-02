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

    // Morada de onde as voltas partem (o armazem), para o mapa da volta.
    // Vazio: o percurso comeca onde o colaborador estiver.
    'origem_rota' => env('ENTREGAS_ORIGEM_ROTA'),

    // Onde fica o armazem (Caldas da Rainha), para organizar as voltas.
    'origem_coordenadas' => [39.4036, -9.1361],

    // As voltas acabam onde comecaram (o armazem, ou a partida da zona): o
    // caminho de regresso conta para escolher a ordem das paragens.
    'volta_regressa_a_partida' => (bool) env('ENTREGAS_VOLTA_REGRESSA', true),

    // Horario das empresas sem horario definido: nao se entrega antes de
    // abrirem nem depois de fecharem. Quem tiver horario proprio escreve-o na
    // empresa (ex.: "ate as 8h", "9 e 11h", "ate 17:30").
    'janela_padrao' => [env('ENTREGAS_ABRE', '09:00'), env('ENTREGAS_FECHA', '18:00')],

    // Minutos parados em cada entrega, para as horas previstas.
    'minutos_por_paragem' => (int) env('ENTREGAS_MINUTOS_POR_PARAGEM', 7),

    // Onde fica cada morada (Nominatim / OpenStreetMap, gratis). Vazio: so o
    // centro do codigo postal. O Nominatim pede um User-Agent que identifique
    // quem pede.
    'geocoder_url' => env('ENTREGAS_GEOCODER_URL', 'https://nominatim.openstreetmap.org'),
    'geocoder_user_agent' => env('ENTREGAS_GEOCODER_USER_AGENT', 'gestao.hortadamaria.com (entregas)'),

    // Minutos de carro pela estrada entre as paragens (OSRM, gratis). Vazio:
    // linha reta entre os pontos.
    'osrm_url' => env('ENTREGAS_OSRM_URL', 'https://router.project-osrm.org'),

    // A carrinha demora mais que o tempo do OSRM (que e para um carro com a
    // estrada livre), e em cada paragem e preciso estacionar.
    'fator_tempo_estrada' => (float) env('ENTREGAS_FATOR_TEMPO_ESTRADA', 1.15),
    'minutos_estacionar' => (int) env('ENTREGAS_MINUTOS_ESTACIONAR', 3),
];
