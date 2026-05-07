# Prompt para Claude Code — Sistema de Listas Semanais de Cabazes

## CONTEXTO DO PROJETO

Projeto Laravel 11 + Blade + Tailwind CSS + SQLite.
Stack existente: Controllers em `app/Http/Controllers/`, Models em `app/Models/`, Views Blade em `resources/views/`, rotas em `routes/web.php`.
Middleware de autenticação: `auth` e `role:admin`.
Padrão de views: layout `<x-layouts.app>`, componente `<x-page-title>`, estilos dark com `bg-[#0A0F1A]`, `bg-[#151E2D]`, `border-white/10`, `text-slate-300`.

## CONTEXTO DE NEGÓCIO

Existem 4 tipos de cabazes de subscrição semanal:
- **Mini** (~10 variedades/semana, 1 pessoa)
- **Pequeno** (~14 variedades/semana, 1-2 pessoas)
- **Médio** (~16 variedades/semana, 4-5 pessoas)
- **Grande** (~20 variedades/semana, 6-7 pessoas)

As subscrições B2C estão no modelo `WooOrder` com `source_type = 'subscription'`.
O tipo de cabaz de cada subscrição está no campo `line_items` (array JSON) — o nome do produto contém "Solo Mio", "Pequeno", "Médio" ou "Grande".

Os clientes empresariais (B2B) estão no modelo `Corporate`. Atualmente têm configuração de frutas individuais. Alguns destes clientes empresariais também encomenda cabazes (Pequeno/Médio/Grande) para os seus funcionários — e quero que possam usar o mesmo sistema de tipos de cabaz.

---

## TAREFA 1 — ADICIONAR `cabaz_tipo` ao WooOrder

### 1.1 Migração
Cria uma nova migração para adicionar a coluna `cabaz_tipo` (nullable string) à tabela `woo_orders`:

```php
// database/migrations/YYYY_MM_DD_000001_add_cabaz_tipo_to_woo_orders_table.php
$table->string('cabaz_tipo')->nullable()->after('dia_entrega');
// Valores possíveis: 'mini', 'pequeno', 'medio', 'grande'
```

### 1.2 Model WooOrder
Adiciona `cabaz_tipo` ao array `$fillable` do model `WooOrder`.

Adiciona o seguinte método ao model `WooOrder`:

```php
public static function detectarCabazTipo(array $lineItems): ?string
{
    $nomes = collect($lineItems)->pluck('name')->implode(' ');
    $nomeLower = mb_strtolower($nomes);
    
    if (str_contains($nomeLower, 'solo') || str_contains($nomeLower, 'mini')) {
        return 'mini';
    }
    if (str_contains($nomeLower, 'grande')) {
        return 'grande';
    }
    if (str_contains($nomeLower, 'médio') || str_contains($nomeLower, 'medio')) {
        return 'medio';
    }
    if (str_contains($nomeLower, 'pequeno')) {
        return 'pequeno';
    }
    return null;
}
```

### 1.3 WooCommerceService
No método `payload()` em `WooCommerceService`, adiciona a deteção do tipo de cabaz e inclui-o no array retornado:

```php
'cabaz_tipo' => WooOrder::detectarCabazTipo(Arr::get($order, 'line_items', [])),
```

### 1.4 Comando Artisan para re-calcular subscrições existentes
Cria o comando `php artisan woo:recalcular-cabaz-tipo` que percorre todos os `WooOrder` com `source_type = 'subscription'` e actualiza o campo `cabaz_tipo` usando `detectarCabazTipo()` a partir de `raw_payload.line_items`.

---

## TAREFA 2 — SISTEMA DE LISTAS SEMANAIS (`lista_cabazes`)

### 2.1 Migrações — 2 tabelas novas

**Tabela `lista_cabazes`** (cabeçalho da lista semanal):
```php
$table->id();
$table->unsignedTinyInteger('semana_numero'); // 1, 2, 3 ou 4 (semana do mês)
$table->unsignedSmallInteger('ano');
$table->unsignedTinyInteger('mes');          // 1-12
$table->string('descricao')->nullable();      // ex: "Semana 1 — Maio 2026"
$table->enum('estado', ['rascunho', 'publicada'])->default('rascunho');
$table->timestamps();
$table->unique(['semana_numero', 'ano', 'mes']); // uma lista por semana/mês/ano
```

**Tabela `lista_cabaz_itens`** (produtos dentro de cada lista):
```php
$table->id();
$table->foreignId('lista_cabaz_id')->constrained('lista_cabazes')->cascadeOnDelete();
$table->enum('cabaz_tipo', ['mini', 'pequeno', 'medio', 'grande']);
$table->string('produto');                    // ex: "Maçãs Gala", "Alface"
$table->string('categoria')->nullable();      // ex: "Fruta", "Legumes"
$table->decimal('quantidade', 8, 3);          // quantidade por cabaz individual
$table->string('unidade', 20)->default('un'); // un, kg, g, molho, cx
$table->unsignedSmallInteger('ordem')->default(0);
$table->timestamps();
```

### 2.2 Models

**`app/Models/ListaCabaz.php`**:
- `$fillable`: `semana_numero`, `ano`, `mes`, `descricao`, `estado`
- `$casts`: `semana_numero` → `integer`, `ano` → `integer`, `mes` → `integer`
- Relação `hasMany` para `ListaCabazItem` (método `itens()`)
- Scope `publicada()`: `->where('estado', 'publicada')`
- Método `tituloFormatado()`: retorna ex. "Semana 1 — Maio 2026" (usa array de meses PT: Janeiro...Dezembro)
- Método `itensPorTipo(string $tipo)`: retorna `itens()->where('cabaz_tipo', $tipo)->orderBy('categoria')->orderBy('ordem')->get()`

**`app/Models/ListaCabazItem.php`**:
- `$fillable`: `lista_cabaz_id`, `cabaz_tipo`, `produto`, `categoria`, `quantidade`, `unidade`, `ordem`
- `$casts`: `quantidade` → `decimal:3`
- Relação `belongsTo` para `ListaCabaz`

### 2.3 Controller `ListaCabazController`

Cria `app/Http/Controllers/ListaCabazController.php` com os seguintes métodos:

**`index()`** — Lista todas as listas, ordenadas por `ano desc`, `mes desc`, `semana_numero desc`. View: `lista-cabazes.index`.

**`create()`** — Passa para a view: anos disponíveis (ano atual ± 1), meses (array PT), semanas (1 a 4), tipos de cabaz. View: `lista-cabazes.create`.

**`store(Request $request)`** — Valida `semana_numero` (1-4), `ano` (integer), `mes` (1-12), `descricao` nullable. Cria `ListaCabaz`. Redireciona para `edit` da lista criada.

**`edit(ListaCabaz $listaCabaz)`** — Carrega a lista com os itens agrupados por `cabaz_tipo`. Calcula contagem de subscritores activos por tipo (query a `WooOrder` com `source_type = 'subscription'` e `status` em `['active', 'subscricao', 'wc-subscricao', 'processing', 'on-hold']`). Calcula contagem de cabazes empresariais activos por tipo (query a `Corporate` com `ativo = true` e `cabaz_tipo` não nulo). View: `lista-cabazes.edit`.

**`update(Request $request, ListaCabaz $listaCabaz)`** — Actualiza `descricao` e `estado`.

**`destroy(ListaCabaz $listaCabaz)`** — Apaga a lista (itens apagados em cascade).

**`storeItem(Request $request, ListaCabaz $listaCabaz)`** — Valida e cria um novo `ListaCabazItem`. Retorna redirect de volta ao edit.

**`updateItem(Request $request, ListaCabazItem $item)`** — Actualiza produto, categoria, quantidade, unidade, ordem.

**`destroyItem(ListaCabazItem $item)`** — Apaga o item. Retorna redirect de volta ao edit da lista pai.

**`totais(Request $request, ListaCabaz $listaCabaz)`** — Calcula e retorna a view de totais a comprar.

Lógica de cálculo de totais em `totais()`:
```
Para cada item da lista:
  total_a_comprar = (quantidade_por_cabaz × num_subscritores_desse_tipo)
                  + (quantidade_por_cabaz × num_cabazes_corporate_desse_tipo)

Agregar por produto (somar todos os tipos).
Mostrar também breakdown por tipo de cabaz.
```

### 2.4 Rotas (adicionar em `routes/web.php` dentro do grupo `role:admin`)

```php
Route::resource('/lista-cabazes', ListaCabazController::class)->except(['show']);
Route::post('/lista-cabazes/{listaCabaz}/itens', [ListaCabazController::class, 'storeItem'])->name('lista-cabazes.itens.store');
Route::put('/lista-cabazes/itens/{item}', [ListaCabazController::class, 'updateItem'])->name('lista-cabazes.itens.update');
Route::delete('/lista-cabazes/itens/{item}', [ListaCabazController::class, 'destroyItem'])->name('lista-cabazes.itens.destroy');
Route::get('/lista-cabazes/{listaCabaz}/totais', [ListaCabazController::class, 'totais'])->name('lista-cabazes.totais');
```

### 2.5 Views Blade

#### `resources/views/lista-cabazes/index.blade.php`
- Tabela com colunas: Semana, Mês/Ano, Descrição, Estado (badge rascunho=amarelo/publicada=verde), Ações (Editar, Ver Totais, Apagar)
- Botão "Nova Lista" no topo
- Seguir o padrão visual das outras views (dark theme, border-white/10, etc.)

#### `resources/views/lista-cabazes/create.blade.php`
- Formulário para criar nova lista
- Campos: Semana do mês (select 1-4), Mês (select PT), Ano (select), Descrição (input text)

#### `resources/views/lista-cabazes/edit.blade.php`
Esta é a view principal. Deve ter:

1. **Cabeçalho** com título da lista, estado, botão para mudar estado (rascunho↔publicada)

2. **4 separadores/tabs** (Mini, Pequeno, Médio, Grande) — pode ser com tabs em JavaScript puro ou âncoras HTML
   - Cada tab mostra o contador de subscritores activos desse tipo (ex: "Mini — 12 subscritores + 0 empresas")
   - Tabela de itens desse tipo: Produto, Categoria, Quantidade/Un, Ações (editar inline ou apagar)
   - Formulário inline no fundo de cada tab para adicionar novo item (campos: Produto, Categoria, Quantidade, Unidade)

3. **Botão "Ver Totais a Comprar"** que leva para a view `totais`

#### `resources/views/lista-cabazes/totais.blade.php`
- Mostrar resumo: "X subscritores Mini + Y cabazes empresariais Mini = Z cabazes Mini no total" (para cada tipo)
- Tabela de totais a comprar agrupada por Produto, com colunas: Produto | Categoria | Mini | Pequeno | Médio | Grande | **TOTAL**
- Botão "Imprimir" (`window.print()`) com CSS `@media print` que oculta nav e botões

---

## TAREFA 3 — CABAZES EMPRESARIAIS (Corporates com tipo de cabaz)

### 3.1 Migração
Adiciona campos à tabela `corporates`:

```php
$table->string('cabaz_tipo')->nullable()->after('numero_caixas');
// Valores: 'pequeno', 'medio', 'grande' (Mini não se aplica a empresas)
$table->unsignedSmallInteger('cabaz_quantidade')->nullable()->after('cabaz_tipo');
// Número de cabazes por entrega quando usa tipo de cabaz
```

### 3.2 Model Corporate
Adiciona `cabaz_tipo` e `cabaz_quantidade` ao `$fillable`.

Adiciona método:
```php
public function usaCabazTipo(): bool
{
    return filled($this->cabaz_tipo);
}
```

### 3.3 Controller CorporateController
No método `store()` e `update()`, adiciona tratamento dos campos `cabaz_tipo` e `cabaz_quantidade`.

No `StoreCorporateRequest`, adiciona as regras:
```php
'cabaz_tipo' => 'nullable|in:pequeno,medio,grande',
'cabaz_quantidade' => 'nullable|integer|min:1',
```

### 3.4 View `_form.blade.php` dos Corporates

Adiciona uma nova secção **"Tipo de Cabaz"** no formulário, após o campo `numero_caixas`:

```
[ ] Esta empresa recebe cabazes do catálogo (Mini/Pequeno/Médio/Grande)

Se marcado, mostrar:
  - Select: Tipo de Cabaz [ Pequeno | Médio | Grande ]
  - Input number: Quantidade de cabazes por entrega

Nota: Se "Tipo de Cabaz" estiver definido, é usado nos cálculos da Lista Semanal.
      Se não estiver definido, continuam a ser usadas as frutas individuais configuradas abaixo.
```

Implementar com JavaScript simples: se o checkbox estiver marcado, mostra os campos de cabaz_tipo e cabaz_quantidade; se não, mantém a configuração de frutas individuais.

---

## TAREFA 4 — ACTUALIZAR NAVEGAÇÃO

Em `resources/views/layouts/app.blade.php`, adiciona no menu de admin o link:
```blade
<a class="rounded px-3 py-2 hover:bg-white/10 {{ request()->routeIs('lista-cabazes.*') ? 'bg-white/10 text-white' : '' }}" 
   href="{{ route('lista-cabazes.index') }}">Listas Semanais</a>
```
Coloca-o entre "Entregas" e "Empresas".

---

## ORDEM DE IMPLEMENTAÇÃO

1. Executar as 3 migrações novas (cabaz_tipo em woo_orders, lista_cabazes, lista_cabaz_itens, cabaz_tipo em corporates)
2. Criar/actualizar Models (WooOrder, Corporate, ListaCabaz, ListaCabazItem)
3. Actualizar WooCommerceService para detectar cabaz_tipo
4. Criar comando Artisan `woo:recalcular-cabaz-tipo`
5. Criar ListaCabazController e rotas
6. Criar as 4 views de lista-cabazes
7. Actualizar _form.blade.php dos Corporates
8. Actualizar navegação

## VALIDAÇÕES IMPORTANTES

- Não permitir criar lista com semana/mês/ano duplicados (unique constraint já existe)
- Ao apagar uma lista publicada, pedir confirmação (JavaScript `confirm()`)
- Quantidades de itens devem ser > 0
- `cabaz_tipo` em Corporate só aceita: null, 'pequeno', 'medio', 'grande'

## CONVENÇÕES A MANTER

- Todos os textos em Português de Portugal
- Seguir o padrão dark theme: `bg-[#0A0F1A]` fundo geral, `bg-[#151E2D]` cards, `border-white/10` bordas
- Botões de acção primária: `bg-[#3B82F6]` (azul) ou `bg-[#22C55E]` (verde para confirmações)
- Botões destrutivos: `bg-red-600` com hover `bg-red-500`
- Usar `@csrf` e `@method('PUT'/'DELETE')` nos formulários
- Flash messages via `session('success')` e `session('error')` como nas outras views