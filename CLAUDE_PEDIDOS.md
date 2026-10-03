# Caixa de pedidos — trabalho do fim do dia

Instrucoes para o Claude que, todos os dias ao fim do dia, le as encomendas que
chegaram por email (e pelo WhatsApp, se a Cloud API estiver ligada) e as deixa
preparadas na caixa de pedidos da gestao.hortadamaria.com.

**Nunca cria encomendas.** So prepara. Quem cria e o Andre, no backoffice
(Clientes -> Caixa de pedidos -> Confirmar).

**Base:** `https://gestao.hortadamaria.com/api/v1`
**Autenticacao:** `Authorization: Bearer <token da caixa>` — o token `hmcp_...` gerado em
Clientes -> Caixa de pedidos -> Definicoes. Esse token so abre as rotas `/pedidos-recebidos/*`
(nao cria encomendas). As chaves de sempre (`CLAUDE_API_TOKEN`) tambem funcionam aqui.
**Respostas:** `{ sucesso, dados, avisos, erros }`.

## Passos

1. `GET /pedidos-recebidos/definicoes` — da as etiquetas do Gmail a usar
   (`gmail_etiqueta_entrada`, `gmail_etiqueta_processado`) e se o WhatsApp esta ligado.
   As etiquetas mudam-se no site (Clientes -> Definicoes de pedidos); usa sempre as que vierem daqui.
2. `GET /pedidos-recebidos/produtos` — catalogo, uma vez (mesma resposta do `/produtos`).
3. **Emails:** no Gmail, procura `label:"<gmail_etiqueta_entrada>" -label:"<gmail_etiqueta_processado>"`.
   Para cada email (o mais recente da conversa, mais o que for preciso das anteriores):
   - tira o telefone do texto ou da assinatura e confirma o cliente com `GET /pedidos-recebidos/clientes?telefone=` (mesma resposta do `/clientes`);
     se o email nao trouxer telefone, deixa `cliente.telefone` vazio e poe o nome e o email
     (o pedido fica "Falta telefone" e o Andre poe-no a mao);
   - interpreta as linhas como na skill de encomendas (`unidade: null` se o cliente nao disse; nunca adivinhar);
   - o que nao der para decidir vai em `duvidas` (frases curtas, a pergunta a fazer ao cliente);
   - um email que nao e encomenda (publicidade, resposta a um link de pagamento, etc.) nao se envia.
4. `POST /pedidos-recebidos/lote` com todos de uma vez (ver abaixo). O id da mensagem do Gmail vai em
   `origem_id`: se o mesmo email for enviado duas vezes, nao duplica.
5. So depois de o lote responder `sucesso: true`, muda a etiqueta de cada email enviado para
   `gmail_etiqueta_processado` (e tira a de entrada). Um email com erro no lote fica como estava,
   para ser apanhado no dia seguinte.
6. **WhatsApp** (so se `whatsapp_ativo`): `GET /pedidos-recebidos?estado=novo&canal=whatsapp`.
   Para cada um, interpreta o `texto_original` (o telefone e o do `remetente`) e devolve com
   `PUT /pedidos-recebidos/{id}/interpretacao` com `{ "pedido": {...}, "duvidas": [...] }`.
   Uma conversa que nao e encomenda: `{ "pedido": null, "duvidas": ["Nao parece encomenda: ..."] }`.
7. Resumo final em 2-3 linhas: quantos entraram, quantos prontos, quantos com duvidas.
   O ntfy ja avisa sozinho quando entram pedidos.

## `POST /pedidos-recebidos/lote`

```json
{
  "pedidos": [
    {
      "canal": "email",
      "origem_id": "18c2f0a9b7e4d123",
      "remetente": "Joana Costa <joana@exemplo.pt>",
      "assunto": "Encomenda para quarta",
      "recebido_em": "2026-10-03T09:12:00+01:00",
      "texto_original": "Bom dia, queria 2 kg de ameixas e 6 macas para quarta...",
      "pedido": {
        "cliente": { "nome": "Joana Costa", "telefone": "912345678" },
        "dia_entrega": "quarta",
        "notas": null,
        "cupoes": [],
        "linhas": [
          { "texto": "ameixas", "quantidade": 2, "unidade": "kg" },
          { "texto": "macas", "quantidade": 6, "unidade": null }
        ]
      },
      "duvidas": []
    }
  ]
}
```

- `pedido` tem o formato do `/encomendas/validar` (ver `CLAUDE_ENCOMENDAS.md`). Num cliente que ja
  existe basta o telefone; o resto vem das encomendas anteriores.
- `texto_original`: o texto do email limpo (sem assinaturas longas nem historico citado repetido).
- O servidor valida logo e decide o estado: `pronto`, `com_duvidas` ou `falta_telefone`.
- Resposta: `dados.novos` e `dados.resultados[]` (um por pedido, com `id`, `estado`, `erros`,
  `repetido`, `url_backoffice`).

## Estados

| Estado | Quer dizer |
|---|---|
| `novo` | Chegou (WhatsApp) e ainda ninguem interpretou |
| `pronto` | Validado sem erros nem duvidas: so falta o Confirmar |
| `com_duvidas` | Erros do validador (produto ambiguo, unidade em falta...) ou duvidas do Claude |
| `falta_telefone` | Sem telefone, nao deu para identificar o cliente |
| `criado` | Ja esta no WooCommerce (referencia `pr-<id>`) |
| `descartado` | Nao era encomenda / tratado por outro lado |
