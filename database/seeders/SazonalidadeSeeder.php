<?php

namespace Database\Seeders;

use App\Models\CabazTemplate;
use App\Models\Sazonalidade;
use Illuminate\Database\Seeder;

class SazonalidadeSeeder extends Seeder
{
    public function run(): void
    {
        // Produtos portugueses por época
        $produtos = [
            // Frutas
            ['produto' => 'Laranja',        'categoria' => 'fruta', 'meses' => [1, 2, 3, 4, 11, 12]],
            ['produto' => 'Tangerina',       'categoria' => 'fruta', 'meses' => [1, 2, 3, 11, 12]],
            ['produto' => 'Limão',           'categoria' => 'fruta', 'meses' => [1, 2, 3, 4, 5, 11, 12]],
            ['produto' => 'Kiwi',            'categoria' => 'fruta', 'meses' => [1, 2, 3, 4, 11, 12]],
            ['produto' => 'Maçã',            'categoria' => 'fruta', 'meses' => [1, 2, 3, 8, 9, 10, 11, 12]],
            ['produto' => 'Pera',            'categoria' => 'fruta', 'meses' => [1, 2, 3, 8, 9, 10, 11, 12]],
            ['produto' => 'Morango',         'categoria' => 'fruta', 'meses' => [3, 4, 5, 6]],
            ['produto' => 'Cereja',          'categoria' => 'fruta', 'meses' => [5, 6]],
            ['produto' => 'Nectarina',       'categoria' => 'fruta', 'meses' => [6, 7, 8]],
            ['produto' => 'Pêssego',         'categoria' => 'fruta', 'meses' => [6, 7, 8]],
            ['produto' => 'Melão',           'categoria' => 'fruta', 'meses' => [6, 7, 8, 9]],
            ['produto' => 'Melancia',        'categoria' => 'fruta', 'meses' => [6, 7, 8, 9]],
            ['produto' => 'Ameixa',          'categoria' => 'fruta', 'meses' => [6, 7, 8]],
            ['produto' => 'Figo',            'categoria' => 'fruta', 'meses' => [7, 8, 9]],
            ['produto' => 'Uva',             'categoria' => 'fruta', 'meses' => [8, 9, 10]],
            ['produto' => 'Marmelo',         'categoria' => 'fruta', 'meses' => [9, 10, 11]],
            ['produto' => 'Romã',            'categoria' => 'fruta', 'meses' => [9, 10, 11]],
            ['produto' => 'Banana',          'categoria' => 'fruta', 'meses' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]],

            // Legumes
            ['produto' => 'Tomate',          'categoria' => 'legume', 'meses' => [6, 7, 8, 9, 10]],
            ['produto' => 'Pepino',          'categoria' => 'legume', 'meses' => [5, 6, 7, 8, 9]],
            ['produto' => 'Pimento',         'categoria' => 'legume', 'meses' => [6, 7, 8, 9, 10]],
            ['produto' => 'Beringela',       'categoria' => 'legume', 'meses' => [6, 7, 8, 9]],
            ['produto' => 'Curgete',         'categoria' => 'legume', 'meses' => [5, 6, 7, 8, 9]],
            ['produto' => 'Abóbora',         'categoria' => 'legume', 'meses' => [8, 9, 10, 11]],
            ['produto' => 'Batata Nova',     'categoria' => 'legume', 'meses' => [4, 5, 6, 7]],
            ['produto' => 'Batata',          'categoria' => 'legume', 'meses' => [1, 2, 3, 4, 9, 10, 11, 12]],
            ['produto' => 'Cebola',          'categoria' => 'legume', 'meses' => [1, 2, 3, 6, 7, 8, 9, 10, 11, 12]],
            ['produto' => 'Alho',            'categoria' => 'legume', 'meses' => [5, 6, 7, 8, 9, 10]],
            ['produto' => 'Cenoura',         'categoria' => 'legume', 'meses' => [1, 2, 3, 4, 9, 10, 11, 12]],
            ['produto' => 'Beterraba',       'categoria' => 'legume', 'meses' => [6, 7, 8, 9, 10, 11]],
            ['produto' => 'Nabo',            'categoria' => 'legume', 'meses' => [10, 11, 12, 1, 2, 3]],
            ['produto' => 'Feijão Verde',    'categoria' => 'legume', 'meses' => [5, 6, 7, 8, 9]],

            // Hortaliças
            ['produto' => 'Alface',          'categoria' => 'hortalica', 'meses' => [1, 2, 3, 4, 5, 9, 10, 11, 12]],
            ['produto' => 'Rúcula',          'categoria' => 'hortalica', 'meses' => [1, 2, 3, 4, 5, 9, 10, 11, 12]],
            ['produto' => 'Espinafres',      'categoria' => 'hortalica', 'meses' => [1, 2, 3, 10, 11, 12]],
            ['produto' => 'Couve Portuguesa','categoria' => 'hortalica', 'meses' => [10, 11, 12, 1, 2, 3]],
            ['produto' => 'Couve Coração',   'categoria' => 'hortalica', 'meses' => [1, 2, 3, 10, 11, 12]],
            ['produto' => 'Couve Flor',      'categoria' => 'hortalica', 'meses' => [10, 11, 12, 1, 2, 3]],
            ['produto' => 'Brócolos',        'categoria' => 'hortalica', 'meses' => [10, 11, 12, 1, 2, 3]],
            ['produto' => 'Couve Lombarda',  'categoria' => 'hortalica', 'meses' => [10, 11, 12, 1, 2, 3]],
            ['produto' => 'Grelos',          'categoria' => 'hortalica', 'meses' => [1, 2, 3, 4]],
            ['produto' => 'Espargos',        'categoria' => 'hortalica', 'meses' => [3, 4, 5, 6]],
            ['produto' => 'Ervilhas',        'categoria' => 'hortalica', 'meses' => [4, 5, 6]],
            ['produto' => 'Favas',           'categoria' => 'hortalica', 'meses' => [3, 4, 5]],
            ['produto' => 'Alho Francês',    'categoria' => 'hortalica', 'meses' => [10, 11, 12, 1, 2, 3]],
            ['produto' => 'Aipo',            'categoria' => 'hortalica', 'meses' => [10, 11, 12, 1, 2, 3]],
            ['produto' => 'Acelgas',         'categoria' => 'hortalica', 'meses' => [9, 10, 11, 12, 1, 2, 3]],
        ];

        foreach ($produtos as $produto) {
            Sazonalidade::updateOrCreate(
                ['produto' => $produto['produto']],
                $produto,
            );
        }

        // Templates de composição dos cabazes (regras por categoria)
        $templates = [
            // Cabaz Mini (~2-3 produtos)
            ['cabaz_tipo' => 'mini', 'categoria' => 'fruta',     'quantidade_itens' => 2, 'quantidade_por_item' => 1.0, 'unidade' => 'kg', 'ordem' => 1],
            ['cabaz_tipo' => 'mini', 'categoria' => 'legume',    'quantidade_itens' => 1, 'quantidade_por_item' => 1.0, 'unidade' => 'kg', 'ordem' => 2],
            ['cabaz_tipo' => 'mini', 'categoria' => 'hortalica', 'quantidade_itens' => 1, 'quantidade_por_item' => 1.0, 'unidade' => 'un', 'ordem' => 3],

            // Cabaz Pequeno (~4-5 produtos)
            ['cabaz_tipo' => 'pequeno', 'categoria' => 'fruta',     'quantidade_itens' => 2, 'quantidade_por_item' => 1.5, 'unidade' => 'kg', 'ordem' => 1],
            ['cabaz_tipo' => 'pequeno', 'categoria' => 'legume',    'quantidade_itens' => 2, 'quantidade_por_item' => 1.0, 'unidade' => 'kg', 'ordem' => 2],
            ['cabaz_tipo' => 'pequeno', 'categoria' => 'hortalica', 'quantidade_itens' => 1, 'quantidade_por_item' => 1.0, 'unidade' => 'un', 'ordem' => 3],

            // Cabaz Médio (~6-7 produtos)
            ['cabaz_tipo' => 'medio', 'categoria' => 'fruta',     'quantidade_itens' => 3, 'quantidade_por_item' => 1.5, 'unidade' => 'kg', 'ordem' => 1],
            ['cabaz_tipo' => 'medio', 'categoria' => 'legume',    'quantidade_itens' => 3, 'quantidade_por_item' => 1.0, 'unidade' => 'kg', 'ordem' => 2],
            ['cabaz_tipo' => 'medio', 'categoria' => 'hortalica', 'quantidade_itens' => 2, 'quantidade_por_item' => 1.0, 'unidade' => 'un', 'ordem' => 3],

            // Cabaz Grande (~8-10 produtos)
            ['cabaz_tipo' => 'grande', 'categoria' => 'fruta',     'quantidade_itens' => 4, 'quantidade_por_item' => 2.0, 'unidade' => 'kg', 'ordem' => 1],
            ['cabaz_tipo' => 'grande', 'categoria' => 'legume',    'quantidade_itens' => 4, 'quantidade_por_item' => 1.5, 'unidade' => 'kg', 'ordem' => 2],
            ['cabaz_tipo' => 'grande', 'categoria' => 'hortalica', 'quantidade_itens' => 2, 'quantidade_por_item' => 1.0, 'unidade' => 'un', 'ordem' => 3],
        ];

        foreach ($templates as $template) {
            CabazTemplate::updateOrCreate(
                ['cabaz_tipo' => $template['cabaz_tipo'], 'categoria' => $template['categoria']],
                $template,
            );
        }

        $this->command->info('Sazonalidade: '.count($produtos).' produtos e '.count($templates).' templates de cabaz inseridos.');
    }
}
