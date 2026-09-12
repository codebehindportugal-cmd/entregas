# Encomendas ditadas por WhatsApp

Instrucoes para o chat que transforma uma mensagem de cliente numa encomenda da
Horta da Maria. O cliente manda a encomenda por WhatsApp, o Andre cola o texto no
chat, o chat cria a encomenda no site e devolve o link de pagamento.

**Base:** `https://gestao.hortadamaria.com/api/v1`
**Autenticacao:** `Authorization: Bearer <CLAUDE_API_TOKEN>` (o mesmo dos
endpoints `/api/claude/*` e da API de faturas).
**Forma das respostas:** `{ sucesso, dados, avisos, erros }`, como no
agro.codebehind.pt e no gestao.ateneya.com.

## A regra que importa

O WooCommerce so conta linhas inteiras. Uma linha e "1 x Ameixa 500g", nunca
"1,3 kg de ameixa". Por isso:

- **nunca arredondar por iniciativa propria** — se 1,3 kg nao da um numero certo
  de embalagens, pergunta-se ao cliente;
- **nunca escolher entre produtos parecidos** — se a API devolver candidatos,
  pergunta-se qual;
- **nunca assumir a unidade** num produto vendido ao peso — "2 de ameixa" pode
  ser 2 kg (4 embalagens) ou 2 embalagens (1 kg), e a diferenca e o dobro;
- `unidade: null` e a resposta certa quando o cliente nao disse a unidade. Deixar
  a duvida visivel e o objetivo, nao um falhanco.

## Procedimento

1. **`GET /produtos`** uma vez por conversa, para saber o catalogo e como cada
   produto se vende.
2. **Interpretar o texto do cliente** em linhas `{texto, quantidade, unidade}`.
   A unidade so vai preenchida se o cliente a tiver dito.
3. **`POST /encomendas/validar`**. Se vier `sucesso: false` ou avisos de
   conversao estimada, escrever ao Andre, em portugues, a pergunta exata a fazer
   ao cliente.
4. Depois de o Andre confirmar, **`POST /encomendas`** com o `token_confirmacao`
   e uma `referencia_externa` no formato `wa-AAAA-MM-DD-<primeiro-nome>-<nn>`.
5. Devolver ao Andre o **link de pagamento** e a **mensagem pronta a copiar**.

O token dura 30 minutos. Se expirar, volta-se ao passo 3 — nao ha maneira de
criar uma encomenda sem uma validacao recente.

## `GET /produtos`

Parametros opcionais: `q` (pesquisa), `apenas_disponiveis` (por defeito `1`).

```json
{
  "sucesso": true,
  "dados": {
    "total": 2,
    "produtos": [
      {
        "id": 12, "woo_id": 2201, "nome": "Ameixa 500g", "sku": "AME500",
        "aliases": ["ameixas", "ameixa preta"], "preco": 2.0,
        "unidade_venda": "peso", "formato_qtd": 0.5, "formato_unidade": "kg",
        "peso_medio_kg": null, "qtd_min": 1, "qtd_max": null,
        "descricao_formato": "embalagem de 500 g",
        "unidades_confirmadas": true, "disponivel": true
      },
      {
        "id": 15, "woo_id": 2210, "nome": "Maca Royal Gala", "preco": 0.6,
        "unidade_venda": "unidade", "formato_qtd": 1, "formato_unidade": "un",
        "peso_medio_kg": 0.2, "descricao_formato": "a unidade (~200 g)",
        "unidades_confirmadas": true, "disponivel": true
      }
    ]
  },
  "avisos": [], "erros": []
}
```

`unidades_confirmadas: false` quer dizer que o formato foi adivinhado pelo nome
do produto e ainda nao foi confirmado no backoffice — nesse caso vale a pena
confirmar a equivalencia com o Andre antes de criar.

## `POST /encomendas/validar`

```json
{
  "cliente": {
    "nome": "Joana Costa",
    "telefone": "912345678",
    "email": "joana@exemplo.pt",
    "morada": "Rua das Flores 10",
    "codigo_postal": "2500-100",
    "cidade": "Caldas da Rainha",
    "idioma": "pt"
  },
  "perfil_woo_order_id": 341,
  "dia_entrega": "quarta",
  "data_entrega": "2026-09-16",
  "notas": "Tocar a campainha do 2 esquerdo.",
  "cupoes": ["OUTONO10"],
  "linhas": [
    { "texto": "ameixas", "quantidade": 2, "unidade": "kg" },
    { "texto": "macas", "quantidade": 6, "unidade": "un" },
    { "woo_product_id": 18, "quantidade": 1, "unidade": "un" }
  ]
}
```

- `perfil_woo_order_id` e uma encomenda antiga do mesmo cliente: preenche a
  morada, o email e o idioma que faltarem. Encontra-se em `/api/claude/subscricoes`
  ou no backoffice.
- `woo_product_id` e o `id` do catalogo (nao o `woo_id`) e salta a resolucao por
  nome — usar quando o cliente ja disse qual dos candidatos era.
- Nome e telefone sao obrigatorios; a morada da so aviso.

Resposta com sucesso (200):

```json
{
  "sucesso": true,
  "dados": {
    "cliente": { "nome": "Joana Costa", "telefone": "912345678", "...": "..." },
    "linhas": [
      {
        "linha": 1, "texto_original": "ameixas",
        "produto": { "id": 12, "woo_id": 2201, "nome": "Ameixa 500g", "formato": "embalagem de 500 g" },
        "quantidade_pedida": 2, "unidade_pedida": "kg",
        "quantidade_woo": 4, "equivalencia": "4 x Ameixa 500g = 2 kg",
        "preco_unitario": 2.0, "subtotal": 8.0, "candidatos": []
      }
    ],
    "total_estimado": 11.6,
    "dia_entrega": "quarta",
    "token_confirmacao": "xxxxxxxx...",
    "valido_ate": "2026-09-12T11:05:00+01:00"
  },
  "avisos": [],
  "erros": []
}
```

Quando falha devolve **422 com os mesmos `dados`** — as linhas que deram e as que
nao deram. E dai que sai a pergunta ao cliente.

## `POST /encomendas`

```json
{
  "token_confirmacao": "xxxxxxxx...",
  "referencia_externa": "wa-2026-09-12-joana-01",
  "confirmado": true
}
```

Antes de criar, **valida tudo outra vez do zero** (o preco ou o stock podem ter
mudado nos 30 minutos). Resposta (201):

```json
{
  "sucesso": true,
  "dados": {
    "encomenda_id": 412, "woo_id": 987, "estado": "pending", "total": 11.6,
    "referencia_externa": "wa-2026-09-12-joana-01",
    "link_pagamento": "https://hortadamaria.com/checkout/order-pay/987/?pay_for_order=true&key=wc_order_abc",
    "link_whatsapp": "https://wa.me/351912345678?text=...",
    "mensagem_whatsapp": "Ola Joana! Tudo bem? Ja deixamos a sua encomenda...",
    "url_backoffice": "https://gestao.hortadamaria.com/encomendas/412",
    "repetida": false,
    "linhas": [{ "produto": "Ameixa 500g", "quantidade_woo": 4, "equivalencia": "4 x Ameixa 500g = 2 kg", "subtotal": 8.0 }]
  },
  "avisos": [], "erros": []
}
```

Repetir o pedido com a mesma `referencia_externa` devolve **200** com
`repetida: true` e nao cria nada — e seguro repetir se a rede falhar.
Se o site recusar, vem **502** com `WOOCOMMERCE_FALHOU` e nao fica nada gravado.

## Codigos e o que perguntar

| Codigo | O que aconteceu | O que perguntar ao cliente |
| --- | --- | --- |
| `UNIDADE_EM_FALTA` | erro | "Sao X kg ou X embalagens de 500 g?" |
| `QTD_NAO_MULTIPLA` | erro | "1,3 kg nao da certo em embalagens de 500 g: quer 1 kg ou 1,5 kg?" (as duas opcoes vem em `sugestoes`) |
| `CONVERSAO_IMPOSSIVEL` | erro | "As macas vendem-se a unidade — quantas quer?" |
| `PRODUTO_AMBIGUO` | erro | "Qual destes queria?" (lista em `sugestoes`) |
| `PRODUTO_NAO_ENCONTRADO` | erro | confirmar o nome do produto com o Andre |
| `PRODUTO_INDISPONIVEL` | erro | "Esse produto nao esta disponivel esta semana" |
| `QTD_NAO_INTEIRA` | erro | "Meia unidade nao da — 1 ou 2?" |
| `FORA_DOS_LIMITES` | erro | quantidade acima ou abaixo do permitido |
| `CLIENTE_INCOMPLETO` | erro | falta nome/telefone: pedir ao Andre ou mandar `perfil_woo_order_id` |
| `CUPAO_INVALIDO` | erro | o cupao nao existe no site |
| `CONVERSAO_ESTIMADA` | aviso | "1 kg de maca dao +/- 5 unidades — pode ser?" |
| `INTERPRETADO_COMO_EMBALAGENS` | aviso | confirmar o total em kg |
| `PRODUTO_APROXIMADO` | aviso | confirmar que e mesmo aquele produto |
| `UNIDADE_ASSUMIDA` | aviso | nenhuma (produto a unidade, leitura unica) |
| `MORADA_EM_FALTA` | aviso | confirmar a morada de entrega |
| `SEM_PRECO` | aviso | o total mostrado fica abaixo do real |
| `UNIDADES_POR_CONFIRMAR` | aviso | ha produtos com o formato por confirmar no backoffice |

## Backoffice

O formato de venda de cada produto edita-se em **Produtos**, coluna "Como se
vende": se e a unidade ou ao peso, o tamanho da embalagem, o peso medio de uma
unidade, minimos/maximos e os nomes por que os clientes tratam o produto
(`aliases` — e o que faz "ameixas pretas" encontrar a "Ameixa 500g").

As linhas a amarelo ainda tem o formato por confirmar. Para preencher tudo de
uma vez pela primeira vez:

```
php artisan produtos:inferir-unidades --dry-run
php artisan produtos:inferir-unidades
```

O palpite vem do nome do produto ("Ameixa 500g" -> embalagem de 0,5 kg) e nunca
inventa o peso medio de uma unidade, que e a unica coisa que faria uma encomenda
sair errada sem ninguem reparar. Confirmar um produto no ecra impede o sync do
site de lhe voltar a mexer.
