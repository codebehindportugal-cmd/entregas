# Prompt para Claude Code (VS Code) — Tabela de Preços + Custo por Cabaz

## CONTEXTO DO PROJETO

Projeto Laravel 11 + Blade + Tailwind CSS + SQLite.
Já existem os modelos: `WooOrder` (subscrições B2C), `Corporate` (clientes B2B), `ListaCabaz`, `ListaCabazItem`.
A `ListaCabaz` define os produtos por semana para cada tipo de cabaz (mini/pequeno/medio/grande).
A `ListaCabazItem` tem: `lista_cabaz_id`, `cabaz_tipo`, `produto`, `categoria`, `quantidade`, `unidade`.
O objetivo desta tarefa é criar um sistema de tabelas de preços de fornecedores e calcular o custo de cada cabaz a partir dos preços.

---

## TAREFA 1 — TABELA DE PREÇOS DE FORNECEDOR

### 1.1 Migrações — 2 tabelas novas

**Tabela `tabelas_precos`** (cabeçalho do catálogo de preços):
```php
$table->id();
$table->string('fornecedor')->default('Sentido da Fruta');
$table->string('descricao')->nullable();     // ex: "Tabela Abril 2026"
$table->date('valida_de');
$table->date('valida_ate')->nullable();
$table->boolean('ativa')->default(true);
$table->timestamps();
```

**Tabela `tabela_preco_itens`** (produtos com preço):
```php
$table->id();
$table->foreignId('tabela_preco_id')->constrained('tabelas_precos')->cascadeOnDelete();
$table->string('categoria');          // "Frutas Exóticas", "Frutas", "Citrinos", "Maçãs", "Pêras",
                                      // "Legumes", "Cogumelos", "Batatas", "Cozidos e Embalados", "Secos"
$table->string('produto');            // nome do produto (ex: "Banana Angola", "Maçã Royal Gala")
$table->string('origem')->nullable(); // "Portugal", "Espanha", "Brasil", etc.
$table->string('calibre')->nullable();// "70/75", "N/cal", "Extra", etc.
$table->decimal('preco_kg', 8, 4);   // preço sem IVA por kg (ou por unidade se unidade != kg)
$table->decimal('preco_kg_iva', 8, 4);// preço com IVA
$table->string('unidade', 20)->default('kg'); // "kg", "uni", "molho", "emb"
$table->text('notas')->nullable();    // notas como "Por Encomenda", "cx 8,2kg", etc.
$table->unsignedSmallInteger('ordem')->default(0);
$table->timestamps();
```

### 1.2 Models

**`app/Models/TabelaPreco.php`**:
- `$fillable`: `fornecedor`, `descricao`, `valida_de`, `valida_ate`, `ativa`
- `$casts`: `valida_de` → `date`, `valida_ate` → `date`, `ativa` → `boolean`
- Relação `hasMany` → `TabelaPrecoItem` (método `itens()`)
- Scope `ativa()`: `->where('ativa', true)->orderByDesc('valida_de')`
- Método `tituloFormatado()`: `"{$this->fornecedor} — {$this->descricao} ({$this->valida_de->format('d/m/Y')})"`
- Método estático `ativaParaData(Carbon $data)`: devolve a tabela ativa que cobre essa data

**`app/Models/TabelaPrecoItem.php`**:
- `$fillable`: todos os campos acima
- `$casts`: `preco_kg` → `decimal:4`, `preco_kg_iva` → `decimal:4`
- Relação `belongsTo` → `TabelaPreco`
- Scope `porCategoria(string $categoria)`: filtra por categoria
- Método `precoFormatado()`: `number_format($this->preco_kg, 2, ',', ' ') . ' €/kg'`

### 1.3 Seeder com a tabela de preços actual

Cria `database/seeders/TabelaPrecoSeeder.php` que insere a tabela de preços "Sentido da Fruta" válida de 07/04/2026 a 10/04/2026, com todos os seguintes produtos (usa `TabelaPreco::create()` seguido de `$tabela->itens()->createMany([...])`):

**Categoria: Frutas Exóticas**
| Produto | Origem | Calibre | Preço/Kg | C/IVA |
|---|---|---|---|---|
| Abacaxi Pré-maturado | Costa Rica | 5/6 | 1.70 | 1.80 |
| Abacaxi | Costa Rica | 5/6 | 1.35 | 1.43 |
| Amoras (covete 0,125kg) | Portugal | N/cal | 18.00 | 19.08 |
| Anonas | Espanha | N/cal | 3.50 | 3.71 |
| Banana (mais pequena) | Importado | 14 | 1.02 | 1.08 |
| Banana Angola | Angola | 20 | 1.08 | 1.14 |
| Banana Canárias | Canárias | 19 | 2.45 | 2.60 |
| Banana Delmonte | Costa Rica | 19 | 1.19 | 1.26 |
| Framboesa (covete 0,125kg) | Portugal | N/cal | 19.00 | 20.14 |
| Gengibre | Brasil/China | N/cal | 3.30 | 3.50 |
| Mandioca | Costa Rica | N/cal | 2.80 | 2.97 |
| Manga Palmer (Avião) | Brasil | 7/8/9 | 3.60 | 3.82 |
| Manga Palmer Sweet | Brasil | 8 | 3.80 | 4.03 |
| Manga Palmer (Barco) | Brasil | 8/10 | 2.10 | 2.23 |
| Mamão (Avião) | Brasil | 6 | 3.60 | 3.82 |
| Maracujá | Importado | N/cal | 7.80 | 8.27 |
| Mirtilos (covete 0,125kg) | Perú | N/cal | 13.50 | 14.31 |
| Papaia (Avião) | Brasil | 7 | 3.95 | 4.19 |
| Pitaya | Angola/Espanha | N/cal | 4.50 | 4.77 |
| Plátano Verde | Equador | N/cal | 2.20 | 2.33 |

**Categoria: Frutas**
| Produto | Origem | Calibre | Preço/Kg | C/IVA |
|---|---|---|---|---|
| Ameixa Vermelha | África do Sul | AAA | 2.40 | 2.54 |
| Kiwi (grosso) | Portugal | 27 | 2.60 | 2.76 |
| Kiwi (médio) | Importado/Portugal | 39 | 1.65 | 1.75 |
| Kiwi Germinado | Portugal/Grécia | N/cal | 1.80 | 1.91 |
| Melancia Riscada | Marrocos | N/cal | 1.25 | 1.33 |
| Melão Amarelo | Costa Rica | 4 | 2.05 | 2.17 |
| Melão Branco | Brasil | 4/5 | 2.30 | 2.44 |
| Melão Verde | Brasil | 4 | 2.30 | 2.44 |
| Meloa Gália Nova | Marrocos | 5/6 | 3.40 | 3.60 |
| Morango cx 5kg (PT) | Portugal | N/cal | 2.60 | 2.76 |
| Morango cx 5kg (PT/Esp) | Portugal/Espanha | N/cal | 2.30 | 2.44 |
| Nectarina Amarela | Chile | AA | 2.40 | 2.54 |
| Nêsperas (cx azul e preta) | Espanha | G | 3.30 | 3.50 |
| Nêsperas (cx cartão) | Espanha | GG | 3.90 | 4.13 |
| Romã Vermelha | Egito | N/cal | 3.50 | 3.71 |
| Uva Branca S/Grainha (cx 8,2kg) | Perú | XL | 4.45 | 4.72 |
| Uva Branca S/Grainha (cx verde 4,5kg) | África do Sul | L | 3.60 | 3.82 |
| Uva Red Globe (cx 4,5Kg) | Chile | L | 3.10 | 3.29 |
| Uva Red Globe (cx 8,2Kg) | Perú | XL | 3.40 | 3.60 |
| Uva Preta S/Grainha | Perú | N/cal | 3.85 | 4.08 |

**Categoria: Citrinos**
| Produto | Origem | Calibre | Preço/Kg | C/IVA |
|---|---|---|---|---|
| Clementina Afuer | Portugal | 2/3 | 1.20 | 1.27 |
| Clementina Afuer C/Folha | Espanha | 1XX | 1.48 | 1.57 |
| Clementina S/Folha | Espanha | 1XX | 1.55 | 1.64 |
| Lima | Brasil | 42 | 2.40 | 2.54 |
| Laranjas Lanelate | Portugal | 4/5 | 0.88 | 0.93 |
| Laranjas Lanelate | Portugal | 6/7 | 0.79 | 0.84 |
| Limão | Portugal | N/cal | 0.95 | 1.01 |
| Limão | Espanha | N/cal | 1.80 | 1.91 |

**Categoria: Maçãs**
| Produto | Origem | Calibre | Preço/Kg | C/IVA |
|---|---|---|---|---|
| Maça Bravo | Portugal | 60/65 | 1.45 | 1.54 |
| Maça Bravo (tab plastico) | Portugal | 65/70 | 2.60 | 2.76 |
| Maça Bravo | Portugal | 70/75 | 3.10 | 3.29 |
| Maça Fuji | França | 70/75 | 1.50 | 1.59 |
| Maça Fuji | Portugal/França | 75/80 | 1.60 | 1.70 |
| Maçã Golden | Portugal | 60/65 | 0.78 | 0.83 |
| Maçã Golden | Portugal | 65/70 | 0.85 | 0.90 |
| Maçã Golden | Portugal | 70/75 | 1.03 | 1.09 |
| Maçã Golden | Portugal | 75/80 | 1.20 | 1.27 |
| Maçã Golden | Portugal | 80/85 | 1.20 | 1.27 |
| Maçã Granny Smith (2 camadas) | Espanha | 75/80 | 1.30 | 1.38 |
| Maçã Granny Smith Extra | Espanha | 75/80 | 1.95 | 2.07 |
| Maça Jonagored | Portugal | 70/75 | 0.90 | 0.95 |
| Maça Jonagored | Portugal | 75/80 | 0.95 | 1.01 |
| Maçã Reineta | Portugal | 70/75 | 1.05 | 1.11 |
| Maçã Reineta | Portugal | 75/80 | 1.35 | 1.43 |
| Maçã Reineta | Portugal | 80+ | 1.75 | 1.86 |
| Maçã Royal Gala | Portugal | 60/65 | 0.70 | 0.74 |
| Maçã Royal Gala | Portugal | 65/70 | 0.85 | 0.90 |
| Maçã Royal Gala | Portugal/França | 70/75 | 1.28 | 1.36 |
| Maçã Royal Gala | Portugal | 75/80 | 1.30 | 1.38 |
| Maça Starking | Portugal | 65/70 | 0.75 | 0.80 |
| Maça Starking | Portugal | 70/75 | 0.90 | 0.95 |
| Maça Starking | Portugal | 75/80 | 0.95 | 1.01 |

**Categoria: Pêras**
| Produto | Origem | Calibre | Preço/Kg | C/IVA |
|---|---|---|---|---|
| Pêra Abacate Hasso | Espanha/Portugal | N/cal | 3.95 | 4.19 |
| Pêra Abacate Liso | Portugal | N/cal | 2.80 | 2.97 |
| Pêra Conferência | Holanda | N/cal | 1.45 | 1.54 |
| Pêra Comice | Holanda | N/cal | 1.40 | 1.48 |
| Pêra Rocha | Portugal | 60/65 | 1.25 | 1.33 |
| Pêra Rocha | Portugal | 65/70 | 1.85 | 1.96 |
| Pêra Rocha | Portugal | 70/75 | 2.20 | 2.33 |
| Pêra Rocha | Portugal | 75/80 | 2.20 | 2.33 |
| Pêra Packam's | Importado | 70 | 1.40 | 1.48 |
| Pêra Cope Rose | África do Sul | 70 | 1.60 | 1.70 |

**Categoria: Legumes**
| Produto | Origem | Preço/Kg | C/IVA |
|---|---|---|---|
| Abobora Menina | Portugal | 0.65 | 0.69 |
| Abobora Butternut | Portugal | 0.70 | 0.74 |
| Aipo de Mesa | Espanha | 1.95 | 2.07 |
| Aipo Bola (Raiz) | Espanha | 1.90 | 2.01 |
| Alface Frisada | Portugal | 1.30 | 1.38 |
| Alface Roxa | Portugal | 2.60 | 2.76 |
| Agrião (Molho) | Portugal | 1.80 | 1.91 |
| Alho Francês | Portugal | 1.45 | 1.54 |
| Alho Seco (Cx 6kg) PT | Portugal | 3.70 | 3.92 |
| Alho Seco (Cx 6kg) CH/ESP | China/Espanha | 3.50 | 3.71 |
| Alho Seco II (saco 6kg) | Espanha | 2.90 | 3.07 |
| Alho Seco (Embalado 0,5kg) | Espanha | 3.95 | 4.19 |
| Beterraba Fresca | Portugal | 1.20 | 1.27 |
| Beterraba C/Rama (ao molho) | Portugal | 3.40 | 3.60 |
| Beringela | Portugal | 1.85 | 1.96 |
| Cebola Branca | Espanha | 2.50 | 2.65 |
| Cebola Doce | Perú | 1.25 | 1.33 |
| Cebola Grossa | Portugal | 0.63 | 0.67 |
| Cebola Média | Portugal | 0.70 | 0.74 |
| Cebola Média | França/Holanda | 0.53 | 0.56 |
| Cebola Nova | Portugal | 1.25 | 1.33 |
| Cebola Roxa | Espanha/Portugal | 0.72 | 0.76 |
| Cenoura | Portugal | 0.73 | 0.77 |
| Cenoura II (restauração) | Portugal | 0.45 | 0.48 |
| Chalotas (250gr) | Espanha | 4.90 | 5.19 |
| Chuchu | Importado/Portugal | 3.15 | 3.34 |
| Coentros (Molho 500gr) | Portugal | 3.10 | 3.29 |
| Courgettes | Portugal | 1.30 | 1.38 |
| Courgettes II (restauração) | Portugal | 0.88 | 0.93 |
| Couve Bróculo | Portugal | 4.10 | 4.35 |
| Couve Coração | Portugal | 1.05 | 1.11 |
| Couve Flor | Portugal | 1.90 | 2.01 |
| Couve Lombarda | Portugal | 0.95 | 1.01 |
| Couve Roxa | Portugal | 1.10 | 1.17 |
| Espargo | Espanha | 9.60 | 10.18 |
| Espinafre (Molho) | Portugal | 3.00 | 3.18 |
| Favas | Portugal | 2.25 | 2.39 |
| Feijão Verde Fino | Espanha/Marrocos | 4.30 | 4.56 |
| Feijão Verde Moya | Marrocos | 3.60 | 3.82 |
| Hortelã (Molho) | Portugal | 1.95 | 2.07 |
| Nabiças (Molho) | Portugal | 2.60 | 2.76 |
| Nabos com Rama (kg) | Portugal | 1.80 | 1.91 |
| Nabos sem Rama (kg) | Portugal | 1.85 | 1.96 |
| Pepino | Portugal | 1.25 | 1.33 |
| Pimento Amarelo | Holanda | 4.20 | 4.45 |
| Pimento Verde | Espanha | 2.20 | 2.33 |
| Pimento Verde II | Espanha | 1.80 | 1.91 |
| Pimento Vermelho | Espanha | 2.25 | 2.39 |
| Rabanetes | Holanda | 2.95 | 3.13 |
| Salsa (Molho 500gr) | Portugal | 2.45 | 2.60 |
| Tomate Coração de Boi/Rosa | Espanha | 4.10 | 4.35 |
| Tomate Daniela | Espanha | 2.60 | 2.76 |
| Tomate Daniela cx petit | Espanha | 2.50 | 2.65 |
| Tomate Rama cx casi cartão | Espanha | 3.40 | 3.60 |
| Tomate Rama médio cx plastico | Portugal | 2.95 | 3.13 |
| Tomate Xuxa | Portugal | 3.30 | 3.50 |
| Tomate Cherry c/rama | Holanda/Espanha | 5.60 | 5.94 |

**Categoria: Cogumelos**
| Produto | Origem | Preço/Kg | C/IVA |
|---|---|---|---|
| Cogumelo Paris (branco) | Espanha | 3.40 | 3.60 |
| Cogumelo Marron | Espanha | 4.95 | 5.25 |
| Cogumelo Portobello | Holanda | 5.60 | 5.94 |

**Categoria: Batatas**
| Produto | Origem | Preço/Kg | C/IVA |
|---|---|---|---|
| Batata Agria (20kg) | Portugal | 0.37 | 0.39 |
| Batata Branca Nova | Egito | 0.85 | 0.90 |
| Batata Branca Picasso | Portugal | 0.98 | 1.04 |
| Batata Branca Lavada Miúda (10kg) | França | 0.65 | 0.69 |
| Batata Branca Lavada (20kg) | França | 0.48 | 0.51 |
| Batata Branca Lavada (3kg) | Portugal | 0.62 | 0.66 |
| Batata Doce | Portugal | 1.05 | 1.11 |
| Batata Doce Grande | Portugal | 0.75 | 0.80 |
| Batata Doce Laranja Nova | Portugal | 1.05 | 1.11 |
| Batata Vermelha (não lavada, 20kg) | Portugal | 0.39 | 0.41 |
| Batata Vermelha Lavada (20kg) | França | 0.58 | 0.61 |
| Batata Vermelha Lavada (5kg) | Portugal | 0.59 | 0.63 |
| Batata Vermelha Lavada (3kg) | Portugal | 0.65 | 0.69 |

**Categoria: Cozidos e Embalados**
| Produto | Origem | Preço/Kg | C/IVA |
|---|---|---|---|
| Alho Seco Descascado (1kg) | Espanha | 3.90 | 4.13 |
| Azeitona Evaristo | Portugal | 3.10 | 3.29 |
| Azeitona Retalhada Nova (balde 5kg) | Portugal | 2.70 | 3.32 |
| Azeitonas Retalhadas (balde 1kg) | Portugal | 3.20 | 3.39 |
| Azeitonas Preta Oxidada 28/32 | Portugal | 2.80 | 3.44 |
| Azeitonas Preta Oxidada 18/20 | Portugal | 3.20 | 3.94 |
| Azeitona Galega Nova | Portugal | 3.10 | 3.81 |
| Azeitona Britada | Espanha | 2.80 | 3.44 |
| Beterraba Pré-Cozida (emb.) | Espanha | 1.35 | 1.43 |
| Caldo Verde (kg) | Portugal | 3.90 | 4.13 |
| Gomas Vegan | Itália | 5.80 | 7.13 |
| Milho Doce Pré-Cozido | Espanha | 2.80 | 3.44 |
| Pickles Balde | Espanha | 6.50 | 8.00 |
| Tremoço (balde grande) | Portugal | 1.85 | 2.28 |
| Tremoço (balde pequeno) | Portugal | 2.40 | 2.54 |

**Categoria: Secos**
| Produto | Origem | Preço/Kg | C/IVA |
|---|---|---|---|
| Amêndoa Torrada (balde 1,4kg) | USA | 10.00 | 12.30 |
| Ameixa Seca C/Caroço | Portugal | 4.80 | 5.90 |
| Feijão Catarino | Portugal | 2.40 | 2.54 |
| Feijão Frade | Portugal | 1.80 | 1.91 |
| Feijão Branco | Portugal | 2.20 | 2.33 |
| Feijão Manteiga | Portugal | 1.80 | 1.91 |
| Feijão Vermelho | Portugal | 2.40 | 2.54 |
| Feijão Preto | Portugal | 1.75 | 1.86 |
| Figos Secos (Embalagem 500gr) | Espanha | 1.97 | 2.09 |
| Grão de Bico | Portugal | 1.90 | 2.01 |
| Tâmara | Israel | 12.65 | 13.41 |
| Noz | Chile | 4.90 | 5.19 |

---

## TAREFA 2 — LIGAR PREÇOS À LISTA DE CABAZ

### 2.1 Migração — alterar `lista_cabaz_itens`
Adiciona as seguintes colunas à tabela `lista_cabaz_itens`:

```php
$table->foreignId('tabela_preco_item_id')->nullable()->constrained('tabela_preco_itens')->nullOnDelete()->after('unidade');
$table->decimal('preco_unitario', 8, 4)->nullable()->after('tabela_preco_item_id');
// Preço manual, caso não queira ligar à tabela de preços
// Se tabela_preco_item_id estiver preenchido, o preço vem de lá automaticamente
```

### 2.2 Método no Model `ListaCabazItem`
Adiciona:
```php
public function tabelaPrecoItem(): BelongsTo
{
    return $this->belongsTo(TabelaPrecoItem::class);
}

// Devolve o preço efectivo: tabela de preços (sem IVA) ou manual
public function precoEfetivo(): ?float
{
    if ($this->tabelaPrecoItem) {
        return (float) $this->tabelaPrecoItem->preco_kg;
    }
    return $this->preco_unitario ? (float) $this->preco_unitario : null;
}

// Custo total deste item por 1 cabaz (quantidade × preço)
public function custoUnitario(): ?float
{
    $preco = $this->precoEfetivo();
    return $preco !== null ? round((float) $this->quantidade * $preco, 4) : null;
}
```

---

## TAREFA 3 — CONTROLLER `TabelaPrecoController`

Cria `app/Http/Controllers/TabelaPrecoController.php` com:

**`index()`** — Lista todas as tabelas de preços, ordenadas por `valida_de desc`. Mostra: fornecedor, período, estado ativo/inativo, nº de produtos. View: `tabelas-precos.index`.

**`create()`** — Formulário para criar nova tabela (fornecedor, descrição, valida_de, valida_ate). View: `tabelas-precos.create`.

**`store(Request $request)`** — Valida e cria a `TabelaPreco`.

**`show(TabelaPreco $tabelaPreco)`** — Lista todos os produtos agrupados por categoria, com preços. Tem campo de pesquisa por nome. View: `tabelas-precos.show`.

**`edit(TabelaPreco $tabelaPreco)`** — Formulário de edição dos campos do cabeçalho. View: `tabelas-precos.edit`.

**`update(Request $request, TabelaPreco $tabelaPreco)`** — Actualiza cabeçalho.

**`storeItem(Request $request, TabelaPreco $tabelaPreco)`** — Valida e cria um `TabelaPrecoItem`. Regras: `produto` required, `categoria` required in lista acima, `preco_kg` required numeric min:0, `preco_kg_iva` required numeric min:0.

**`updateItem(Request $request, TabelaPrecoItem $item)`** — Actualiza produto/preços.

**`destroyItem(TabelaPrecoItem $item)`** — Apaga item.

**`clonar(TabelaPreco $tabelaPreco)`** — Clona a tabela e todos os itens, com nova `valida_de` = hoje. Redireciona para o `edit` da nova tabela.

### Rotas (dentro do grupo `role:admin`):
```php
Route::resource('/tabelas-precos', TabelaPrecoController::class)->except(['destroy']);
Route::post('/tabelas-precos/{tabelaPreco}/itens', [TabelaPrecoController::class, 'storeItem'])->name('tabelas-precos.itens.store');
Route::put('/tabelas-precos/itens/{item}', [TabelaPrecoController::class, 'updateItem'])->name('tabelas-precos.itens.update');
Route::delete('/tabelas-precos/itens/{item}', [TabelaPrecoController::class, 'destroyItem'])->name('tabelas-precos.itens.destroy');
Route::post('/tabelas-precos/{tabelaPreco}/clonar', [TabelaPrecoController::class, 'clonar'])->name('tabelas-precos.clonar');
```

---

## TAREFA 4 — CALCULAR CUSTO NA LISTA SEMANAL

### 4.1 Actualizar `ListaCabazController@edit()`

Na view de edição da lista semanal, passar também:
- `$tabelasPrecos`: todas as `TabelaPreco` activas com os seus itens, para o select de ligação de produto
- `$custoPorTipo`: array com custo total de 1 cabaz por tipo calculado a partir dos itens já associados

### 4.2 View `lista-cabazes/edit.blade.php` — actualizar

Em cada linha de produto na tabela:
- Adicionar coluna "Preço"
- Mostrar select de pesquisa para ligar ao produto da tabela de preços (ou campo de preço manual)
- Mostrar custo calculado (quantidade × preço) no final da linha

No rodapé de cada tab de tipo de cabaz, mostrar:
```
Custo estimado de 1 cabaz [Tipo]: X,XX €
```

### 4.3 View `lista-cabazes/totais.blade.php` — actualizar

Adicionar colunas de custo à tabela:
| Produto | Mini (qtd) | Mini (€) | Pequeno (qtd) | Pequeno (€) | Médio (qtd) | Médio (€) | Grande (qtd) | Grande (€) | Total kg | Total € |

No final mostrar o custo agregado para todos os subscritores e corporates:
```
Custo total [Mini]: X,XX € × N subscritores = XX,XX €
Custo total [Pequeno]: X,XX € × N subscritores = XX,XX €
...
CUSTO TOTAL GERAL DE COMPRAS: XXX,XX €
```

---

## TAREFA 5 — VIEWS BLADE

#### `resources/views/tabelas-precos/index.blade.php`
- Tabela com: Fornecedor, Descrição, Válida De, Válida Até, Nº Produtos, Estado (badge), Ações (Ver, Editar, Clonar)
- Botão "Nova Tabela de Preços"

#### `resources/views/tabelas-precos/show.blade.php`
- Campo de pesquisa por nome de produto (filtra em tempo real com JS simples)
- Produtos agrupados por categoria com cabeçalho colorido por categoria
- Colunas: Produto, Origem, Calibre, Preço/Kg, C/IVA
- Botão para adicionar produto à tabela (inline form no final de cada categoria)
- Botão "Clonar esta Tabela" no topo

#### `resources/views/tabelas-precos/create.blade.php` e `edit.blade.php`
- Formulário simples: Fornecedor (input, default "Sentido da Fruta"), Descrição, Válida de (date), Válida até (date), Ativa (checkbox)

---

## TAREFA 6 — ACTUALIZAR NAVEGAÇÃO

Em `resources/views/layouts/app.blade.php`, no menu de admin, adiciona:
```blade
<a class="rounded px-3 py-2 hover:bg-white/10 {{ request()->routeIs('tabelas-precos.*') ? 'bg-white/10 text-white' : '' }}"
   href="{{ route('tabelas-precos.index') }}">Preços</a>
```

---

## ORDEM DE IMPLEMENTAÇÃO

1. Migração `tabelas_precos` e `tabela_preco_itens`
2. Models `TabelaPreco` e `TabelaPrecoItem`
3. `TabelaPrecoSeeder` com todos os produtos acima
4. Adicionar a migração com as colunas `tabela_preco_item_id` e `preco_unitario` à `lista_cabaz_itens`
5. Actualizar `ListaCabazItem` com relação e métodos de custo
6. `TabelaPrecoController` e rotas
7. Views de `tabelas-precos`
8. Actualizar views de `lista-cabazes` para mostrar preços e custos
9. Actualizar navegação
10. Correr `php artisan db:seed --class=TabelaPrecoSeeder`

## CONVENÇÕES A MANTER

- Todos os textos em Português de Portugal
- Dark theme: `bg-[#0A0F1A]` fundo, `bg-[#151E2D]` cards, `border-white/10` bordas, `text-slate-300`
- Botão primário: `bg-[#3B82F6]`, destrutivo: `bg-red-600`, confirmação: `bg-[#22C55E]`
- `@csrf` e `@method()` em todos os formulários
- Flash messages via `session('success')` e `session('error')`
- Preços sempre formatados com `number_format($preco, 2, ',', ' ') . ' €'`
- Custo sem IVA por defeito (usar `preco_kg`, não `preco_kg_iva`)