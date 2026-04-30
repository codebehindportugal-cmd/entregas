# VARIÁVEIS E META KEYS — Horta da Maria
# Cola isto no prompt do Claude do VS Code para validar o dashboard

---

## META KEYS DAS ENCOMENDAS WOOCOMMERCE
# Usar EXACTAMENTE estes nomes ao ler via REST API ou get_meta()

### Preferências de entrega (guardadas pela class-hdm-campos-checkout.php)
```
_hdm_frequencia_entrega       → "weekly" | "biweekly"
_hdm_dia_entrega              → "segunda" | "quarta" | "sabado"
_hdm_data_primeira_entrega    → "YYYY-MM-DD"
_hdm_produtos_excluidos       → string texto livre (ex: "não gosto de courgette")
```

### Datas calculadas (guardadas pelo HDM_Order_Status_Subscricao)
```
_hdm_datas_entrega            → JSON string: '["2026-05-07","2026-05-14","2026-05-21","2026-05-28"]'
_hdm_datas_canceladas         → JSON string: '["2026-05-14"]'  (datas que o cliente adiou)
_hdm_fim_subscricao           → "YYYY-MM-DD"  (data da última entrega calculada)
_hdm_email_subscricao_enviado → "1" | ""
_hdm_fim_notificado           → "1" | ""
```

---

## CUSTOM ORDER STATUS
```
slug:   wc-subscricao
label:  Subscrição
```

---

## COMO A API WOOCOMMERCE RETORNA OS META DATA
# A API REST devolve meta_data como array de objectos:
```json
"meta_data": [
  { "id": 123, "key": "_hdm_frequencia_entrega",    "value": "weekly" },
  { "id": 124, "key": "_hdm_dia_entrega",           "value": "quarta" },
  { "id": 125, "key": "_hdm_data_primeira_entrega", "value": "2026-04-30" },
  { "id": 126, "key": "_hdm_datas_entrega",         "value": "[\"2026-04-30\",\"2026-05-07\",\"2026-05-14\",\"2026-05-21\"]" },
  { "id": 127, "key": "_hdm_datas_canceladas",      "value": "[]" },
  { "id": 128, "key": "_hdm_fim_subscricao",        "value": "2026-05-21" },
  { "id": 129, "key": "_hdm_produtos_excluidos",    "value": "não gosto de courgette" }
]
```

### Função para extrair meta (usar em WooCommerceService)
```php
public function getMeta(array $metaData, string $key): mixed
{
    foreach ($metaData as $meta) {
        if ($meta['key'] === $key) {
            return $meta['value'];
        }
    }
    return null;
}
```

### Atenção: _hdm_datas_entrega vem como JSON string dentro de string
```php
// Ao ler do array da API:
$raw = $this->getMeta($order['meta_data'], '_hdm_datas_entrega');
$datas = json_decode($raw, true) ?? [];  // pode ser necessário duplo decode

// Verificar sempre:
if (is_string($datas)) {
    $datas = json_decode($datas, true) ?? [];
}
```

---

## ENDPOINTS WOOCOMMERCE A USAR

### Buscar encomendas para o dashboard (subscrições + normais)
```
GET {WOO_URL}/wp-json/wc/v3/orders
    ?status=wc-subscricao,processing
    &per_page=100
    &orderby=date
    &order=desc
```

### Buscar subscrições já entregues (para secção de renovação)
```
GET {WOO_URL}/wp-json/wc/v3/orders
    ?status=completed
    &per_page=100
    &meta_key=_hdm_frequencia_entrega
    &meta_compare=EXISTS
```

### Marcar como entregue
```
PUT {WOO_URL}/wp-json/wc/v3/orders/{id}
    Body: { "status": "completed" }
```

---

## LÓGICA DE DIAS DE ENTREGA

### Dia da semana por valor
```
"segunda" → PHP: date('w') = 1  | Carbon: Monday    | JS: getDay() = 1
"quarta"  → PHP: date('w') = 3  | Carbon: Wednesday | JS: getDay() = 3
"sabado"  → PHP: date('w') = 6  | Carbon: Saturday  | JS: getDay() = 6
```

### Zona que permite Sábado
```
Caldas da Rainha → Lisboa (prefixos CP: 2500–2529, 2560–2569, 2580–2589,
                           2600–2609, 2625–2795, 1000–1999)
Todas as outras zonas → apenas Quarta-feira
Segunda-feira → apenas disponível via admin (não aparece no checkout)
```

### Frequência
```
"weekly"   → intervalo de 7 dias
"biweekly" → intervalo de 14 dias
```

---

## TABELA woo_orders — CAMPOS A MAPEAR

```php
// Ao sincronizar da API WooCommerce para a tabela local:
[
    'id'                    => $order['id'],
    'woo_status'            => $order['status'],       // 'wc-subscricao', 'processing', 'completed'
    'customer_id'           => $order['customer_id'],
    'primeiro_nome'         => $order['billing']['first_name'],
    'ultimo_nome'           => $order['billing']['last_name'],
    'telefone'              => $order['billing']['phone'],
    'email'                 => $order['billing']['email'],
    'produtos'              => json_encode($order['line_items']),  // [{name, quantity, ...}]
    'total'                 => $order['total'],
    'tipo'                  => $order['status'] === 'wc-subscricao' ? 'subscricao' : 'normal',
    'frequencia'            => getMeta($meta, '_hdm_frequencia_entrega'),   // weekly|biweekly|null
    'dia_entrega'           => getMeta($meta, '_hdm_dia_entrega'),          // segunda|quarta|sabado|null
    'data_primeira_entrega' => getMeta($meta, '_hdm_data_primeira_entrega'),
    'proximas_datas'        => json_decode(getMeta($meta, '_hdm_datas_entrega') ?? '[]'),
    'datas_canceladas'      => json_decode(getMeta($meta, '_hdm_datas_canceladas') ?? '[]'),
    'fim_subscricao'        => getMeta($meta, '_hdm_fim_subscricao'),
    'produtos_excluidos'    => getMeta($meta, '_hdm_produtos_excluidos'),
    'synced_at'             => now(),
]
```

---

## LÓGICA DO DASHBOARD — COMO FILTRAR ENTREGAS POR DIA

```php
// Dado um dia da semana (ex: "quarta"), mostrar encomendas com entrega nesse dia:

$hoje = Carbon::today();
$diaLabel = 'quarta'; // ou 'segunda', 'sabado'

$encomendas = WooOrder::where(function($q) use ($diaLabel, $hoje) {

    // Subscrições: têm _hdm_dia_entrega = $diaLabel
    // E têm uma data em proximas_datas que corresponde a hoje ou à semana
    $q->where('dia_entrega', $diaLabel)
      ->whereJsonContains('proximas_datas', $hoje->toDateString());

    // OU: encomendas processing sem dia definido (entrega manual)
    // Estas aparecem sempre em "Sem dia definido"
})
->orWhere(function($q) {
    $q->where('tipo', 'normal')
      ->whereNull('dia_entrega');
})
->get();
```

---

## PROBLEMA QUE O DASHBOARD ESTÁ A TER

O dashboard não está a mostrar bem porque provavelmente está a filtrar
por `_hdm_dia_entrega` directamente sem ter em conta que:

1. `proximas_datas` é um JSON array — não basta ter o dia certo, a data
   concreta também tem de estar dentro de `proximas_datas`

2. Encomendas `processing` sem meta `_hdm_dia_entrega` não têm dia definido —
   devem aparecer numa secção separada "Para processar / sem dia"

3. O status `wc-subscricao` vem da API como `"wc-subscricao"` (com prefixo wc-)
   mas pode ser guardado sem o prefixo dependendo da versão WooCommerce —
   verificar ambos: `wc-subscricao` E `subscricao`

4. `proximas_datas` pode vir da API como:
   - String JSON normal: `'["2026-05-07","2026-05-14"]'`
   - String com escape duplo: `'"[\"2026-05-07\"]"'`
   - Array já descodificado (raro)
   Fazer sempre: `is_string($v) ? json_decode($v, true) : $v`

---

## VARIÁVEIS DE AMBIENTE (.env)

```env
WOOCOMMERCE_URL=https://hortadamaria.com
WOOCOMMERCE_KEY=ck_xxxxxxxxxxxxxxxxxxxx
WOOCOMMERCE_SECRET=cs_xxxxxxxxxxxxxxxxxxxx

WHATSAPP_PHONE_ID=xxxxxxxxxxxxxxxx
WHATSAPP_TOKEN=EAAxxxxxxxxxxxxxxxx
```

---

## TEMPLATES WHATSAPP — VARIÁVEIS DISPONÍVEIS

### Para clientes WooCommerce (subscricao)
```
{nome}          → billing.first_name
{telefone}      → billing.phone
{encomenda_id}  → order.id
{produto}       → line_items[0].name
{frequencia}    → "todas as semanas" | "de 15 em 15 dias"
{dia_entrega}   → "segunda-feira" | "quarta-feira" | "sábado"
{proxima_data}  → proximas_datas[0] (formatada: "07 de maio")
{excluidos}     → _hdm_produtos_excluidos
```

### Para renovação de subscrição
```
Olá {nome}! A sua subscrição está a chegar ao fim (última entrega: {fim_subscricao}).
Quer renovar? Responda SIM e tratamos de tudo! 🥦
```

### Para confirmar entrega
```
Olá {nome}! A sua encomenda #{encomenda_id} foi entregue hoje. Bom proveito! 🥦
Se tiver alguma questão estamos aqui.
```inform