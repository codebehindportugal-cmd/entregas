<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zonas de entrega (Andre, 01/10/2026). As entregas passam a pertencer a uma
 * zona e nao a um colaborador. Cada zona tem quem a faz em cada dia da semana
 * e substituicoes por datas (ferias, faltas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zonas', function (Blueprint $table): void {
            $table->id();
            $table->string('nome');
            $table->string('cor', 7)->default('#3B82F6');
            $table->string('descricao')->nullable();
            // Prefixos (4 digitos) que a zona cobre, para sugerir a zona de
            // cada entrega. Ex.: "4000-5199, 3500-3899".
            $table->string('codigos_postais')->nullable();
            $table->unsignedInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // Quem faz a zona em cada dia da semana.
        Schema::create('zona_horarios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('zona_id')->constrained('zonas')->cascadeOnDelete();
            $table->string('dia_semana', 10);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Ou: neste dia a zona vai com outra (ex.: Cascais vai com quem faz
            // a Zona Centro), incluindo as substituicoes dessa.
            $table->foreignId('acompanha_zona_id')->nullable()->constrained('zonas')->nullOnDelete();
            $table->timestamps();

            $table->unique(['zona_id', 'dia_semana']);
        });

        // Entre estas datas a zona e feita por outra pessoa.
        Schema::create('zona_substituicoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('zona_id')->constrained('zonas')->cascadeOnDelete();
            $table->date('inicio');
            $table->date('fim');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nota')->nullable();
            $table->timestamps();

            $table->index(['zona_id', 'inicio', 'fim']);
        });

        Schema::table('atribuicoes', function (Blueprint $table): void {
            $table->foreignId('zona_id')->nullable()->after('id')->constrained('zonas')->nullOnDelete();
            // Posta pela app pelo codigo postal (true) ou pelo admin (false).
            // As automaticas acompanham os codigos postais das zonas.
            $table->boolean('zona_automatica')->default(false)->after('zona_id');
        });

        // O colaborador da atribuicao deixa de ser obrigatorio: quem entrega e
        // quem faz a zona nesse dia. Fica guardado para a conversao.
        Schema::table('atribuicoes', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
        });

        // Zonas do Andre (01/10/2026). Os codigos postais sao um ponto de
        // partida para as sugestoes e acertam-se na pagina das Zonas. Ao
        // sabado Lisboa e Cascais sao feitas pela mesma pessoa e o Oeste por
        // outra: isso marca-se no horario (coluna Sabado).
        $agora = now();
        DB::table('zonas')->insert(collect([
            ['Zona Norte', '#3B82F6', 'Leva as encomendas para o Porto e faz entregas até Aveiro, Viseu e Vila Real', '3500-3899, 4000-5199'],
            ['Zona Centro', '#22C55E', 'De Cantanhede a Porto de Mós, até Tomar', '2300-2499, 3000-3299'],
            ['Zona Oeste', '#A855F7', 'Óbidos, Praia d\'El Rey, Caldas, Bombarral e arredores', '2500-2559'],
            ['Zona de Lisboa', '#EC4899', 'Lisboa Centro e até Coruche', '1000-1494, 1500-1999, 2100-2199, 2600-2739'],
            ['Zona de Cascais', '#F59E0B', 'De Miraflores até Cascais', '1495-1499, 2740-2799'],
        ])
            ->values()
            ->map(fn (array $zona, int $i): array => [
                'nome' => $zona[0],
                'cor' => $zona[1],
                'descricao' => $zona[2],
                'codigos_postais' => $zona[3],
                'ordem' => $i + 1,
                'ativo' => true,
                'created_at' => $agora,
                'updated_at' => $agora,
            ])
            ->all());
    }

    public function down(): void
    {
        Schema::table('atribuicoes', function (Blueprint $table): void {
            $table->dropColumn('zona_automatica');
            $table->dropConstrainedForeignId('zona_id');
        });

        Schema::dropIfExists('zona_substituicoes');
        Schema::dropIfExists('zona_horarios');
        Schema::dropIfExists('zonas');
    }
};
