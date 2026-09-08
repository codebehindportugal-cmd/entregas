<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Caminhos dos binários externos
    |--------------------------------------------------------------------------
    |
    | Deixar vazio faz o extractor procurar com `which`. Preencher salta essa
    | procura, o que interessa em servidores onde o PHP da web corre com um
    | PATH reduzido.
    |
    | Isto vive num ficheiro de config e não em env() dentro do serviço de
    | propósito: com `php artisan config:cache` — que o deploy corre — as
    | chamadas a env() fora de config/ devolvem null, e as definições
    | pareceriam ignoradas sem qualquer erro.
    |
    */

    'binaries' => [
        'pdftotext' => env('PDFTOTEXT_BINARY'),
        'pdftoppm'  => env('PDFTOPPM_BINARY'),
        'zbarimg'   => env('ZBARIMG_BINARY'),
        'tesseract' => env('TESSERACT_BINARY'),
    ],

];
