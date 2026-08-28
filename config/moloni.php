<?php

return [
    // Autenticacao (OAuth password grant ou access token estatico)
    'access_token' => env('MOLONI_ACCESS_TOKEN'),
    'company_id' => env('MOLONI_COMPANY_ID'),
    'developer_id' => env('MOLONI_DEVELOPER_ID'),
    'client_secret' => env('MOLONI_CLIENT_SECRET'),
    'username' => env('MOLONI_USERNAME'),
    'password' => env('MOLONI_PASSWORD'),

    // Series de documentos (document_set_id) por tipo de documento.
    // Fatura-Recibo -> subscricoes B2C (ja pagas online).
    // Fatura        -> empresas B2B (a pagar depois).
    'document_set_id_fatura' => env('MOLONI_DOCUMENT_SET_ID_FATURA'),
    'document_set_id_fatura_recibo' => env('MOLONI_DOCUMENT_SET_ID_FATURA_RECIBO'),
    'document_set_id_guia' => env('MOLONI_DOCUMENT_SET_ID_GUIA'),

    // Configuracao fiscal / catalogo por defeito ao criar produtos e linhas.
    // tax_id: id do imposto (IVA) no Moloni. Se vazio, e resolvido dinamicamente
    // (taxes/getAll) escolhendo a taxa cujo valor == default_tax_value.
    'tax_id' => env('MOLONI_TAX_ID'),
    'default_tax_value' => (float) env('MOLONI_DEFAULT_TAX_VALUE', 6), // IVA 6% (taxa reduzida, continente)
    // exemption_reason: usado quando uma linha nao tem imposto (tax vazio).
    'exemption_reason' => env('MOLONI_EXEMPTION_REASON'),
    // unit_id / category_id usados ao criar produtos novos no catalogo Moloni.
    'unit_id' => env('MOLONI_UNIT_ID'),
    'category_id' => env('MOLONI_CATEGORY_ID'),

    // Produto "guarda-chuva" usado como linha do cabaz composto (child_products).
    // Se nao existir no Moloni, e criado com esta referencia/nome.
    'cabaz_reference' => env('MOLONI_CABAZ_REFERENCE', 'CABAZ'),
    'cabaz_name' => env('MOLONI_CABAZ_NAME', 'Cabaz de fruta e legumes'),

    // Referencia do ARTIGO COMPOSTO ja existente no Moloni a reutilizar na linha
    // da fatura B2B (o Moloni expande a composicao definida no artigo). Se vazio,
    // usa cabaz_reference. O artigo tem de existir no Moloni (nao e criado).
    'cabaz_composto_referencia' => env('MOLONI_CABAZ_COMPOSTO_REFERENCIA', env('MOLONI_CABAZ_REFERENCE', 'CABAZ')),

    // Metodo de pagamento (payment_method_id) para a Fatura-Recibo (subscricoes pagas).
    'payment_method_id' => env('MOLONI_PAYMENT_METHOD_ID'),

    // Linha de TRANSPORTE/PORTES na fatura. Artigo ja existente no Moloni
    // (referencia). O valor por entrega vem da ficha da empresa (custo_envio)
    // e a quantidade e o nº de entregas do ciclo. IVA proprio (23% por defeito).
    'portes_referencia' => env('MOLONI_PORTES_REFERENCIA'),
    // Custo de envio por entrega usado quando a ficha da empresa nao tem valor.
    // Vazio = sem portes. Na ficha, 0 significa "esta empresa nao paga portes".
    'custo_envio_padrao' => env('MOLONI_CUSTO_ENVIO_PADRAO'),
    'portes_tax_value' => (float) env('MOLONI_PORTES_TAX_VALUE', 23),
    'portes_tax_id' => env('MOLONI_PORTES_TAX_ID'),

    // Armazem por defeito. Obrigatorio no Moloni quando os artigos gerem stock
    // (tipico nas guias de transporte). Se vazio, nao e enviado.
    'warehouse_id' => env('MOLONI_WAREHOUSE_ID'),

    // Nº de semanas do ciclo de faturacao (quantidades das linhas-filhas) e
    // quantidade da linha-pai do artigo composto na fatura mensal.
    'fatura_semanas' => (float) env('MOLONI_FATURA_SEMANAS', 4),
    'fatura_qtd_pai' => (float) env('MOLONI_FATURA_QTD_PAI', 4),

    // Dias de vencimento por defeito nas Faturas B2B.
    'fatura_dias_vencimento' => (int) env('MOLONI_FATURA_DIAS_VENCIMENTO', 30),

    // Se true, os documentos sao inseridos fechados (status=1); se false, ficam em rascunho.
    'fechar_documentos' => (bool) env('MOLONI_FECHAR_DOCUMENTOS', false),

    // Se true, os precos de venda (site / preco_venda_peca) ja incluem IVA e sao
    // convertidos para liquido ao faturar; se false, sao tratados como liquidos.
    'precos_incluem_iva' => (bool) env('MOLONI_PRECOS_INCLUEM_IVA', true),

    // Guia de transporte: local de carga (expedidor).
    'guia_morada_carga' => env('MOLONI_GUIA_MORADA_CARGA', 'Rua da Criatividade'),
    'guia_cp_carga' => env('MOLONI_GUIA_CP_CARGA', '2510-216'),
    'guia_cidade_carga' => env('MOLONI_GUIA_CIDADE_CARGA', 'Óbidos'),
    // Artigo da guia (ex. 'Mix Corporativo Frutas'); por empresa pode ter o seu (moloni_guia_ref).
    'guia_referencia' => env('MOLONI_GUIA_REFERENCIA'),
    'guia_hora_transporte' => env('MOLONI_GUIA_HORA_TRANSPORTE', '08:00'),
    'guia_delivery_method_id' => env('MOLONI_GUIA_DELIVERY_METHOD_ID'),
    'guia_observacoes' => env('MOLONI_GUIA_OBSERVACOES', "Retornam ao local de carga as caixas de transporte e acondicionamento\nRácio de quantidades: 1 kg = 6 peças de Fruta ou porção equivalente no caso de frutas de menor dimensão como é o caso dos Morangos, uvas e semelhantes"),
];
