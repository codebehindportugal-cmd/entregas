<?php

namespace App\Services;

use App\Models\SepaCobranca;
use App\Models\SepaMandato;
use App\Models\Setting;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Gera os ficheiros de debito direto SEPA (pain.008.001.08, esquema CORE) que
 * se enviam ao banco.
 *
 * Regras (André, 24/09/2026):
 * - O banco so aceita UM pedido por mes por cliente.
 * - Cobra-se em atraso: numa cobranca do mes M so entram meses ate M-1.
 * - Se houver meses em atraso, cada cobranca leva ate `max_meses_por_cobranca`
 *   (2 por defeito) ate a conta ficar em dia.
 */
class SepaService
{
    public const SETTING_KEY = 'sepa_credor';

    private const NS = 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.08';

    private const MESES = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
        7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
    ];

    public function __construct(private readonly HolidayCalendarService $feriados)
    {
    }

    /* ------------------------------------------------------------------ credor */

    /** @return array{nome:string,iban:string,bic:string,identificador:string,prefixo:string,descricao:string} */
    public function credor(): array
    {
        $padrao = [
            'nome' => 'Ateneya, Lda',
            'iban' => 'PT50004551304031193852963',
            'bic' => 'CCCMPTPL',
            'identificador' => 'PT71ZZZ118060',
            'prefixo' => 'ATN',
            'descricao' => 'Horta da Maria',
        ];

        $guardado = json_decode((string) Setting::query()->where('key', self::SETTING_KEY)->value('value'), true);

        return array_merge($padrao, array_filter(is_array($guardado) ? $guardado : [], fn ($v) => filled($v)));
    }

    /** @param array<string,string|null> $dados */
    public function guardarCredor(array $dados): void
    {
        Setting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => json_encode($dados, JSON_UNESCAPED_UNICODE)],
        );
    }

    /* ------------------------------------------------------------------ meses */

    /**
     * Meses que podem entrar numa cobranca feita em $data: do mes a seguir ao
     * ultimo cobrado ate ao mes anterior ao da cobranca.
     *
     * @return list<string> YYYY-MM
     */
    public function mesesPendentes(SepaMandato $mandato, Carbon $data): array
    {
        if (blank($mandato->ultimo_mes_cobrado)) {
            return [];
        }

        $mes = Carbon::createFromFormat('Y-m-d', $mandato->ultimo_mes_cobrado.'-01')->startOfMonth()->addMonthNoOverflow();
        $limite = $data->copy()->startOfMonth()->subMonthNoOverflow();

        $meses = [];
        while ($mes->lte($limite) && count($meses) < 36) {
            $meses[] = $mes->format('Y-m');
            $mes->addMonthNoOverflow();
        }

        return $meses;
    }

    /** Quantos meses se cobram por defeito nesta data. */
    public function mesesSugeridos(SepaMandato $mandato, Carbon $data): int
    {
        return min(count($this->mesesPendentes($mandato, $data)), max(1, (int) $mandato->max_meses_por_cobranca));
    }

    /**
     * Proxima data de cobranca sugerida: o dia habitual do cliente, no primeiro
     * mes sem cobranca, com pelo menos 2 dias de antecedencia, passada para o
     * dia util seguinte se calhar em fim de semana ou feriado.
     */
    public function dataSugerida(SepaMandato $mandato): Carbon
    {
        $minimo = $this->primeiraDataPossivel()->addDay();
        $mesesUsados = $mandato->cobrancas()->pluck('mes_cobranca')->all();

        $mes = now()->startOfMonth();
        for ($i = 0; $i < 24; $i++, $mes->addMonthNoOverflow()) {
            if (in_array($mes->format('Y-m'), $mesesUsados, true)) {
                continue;
            }
            $dia = min(max(1, (int) $mandato->dia_cobranca), $mes->daysInMonth);
            $data = $this->diaUtil($mes->copy()->day($dia));
            if ($data->gte($minimo) && $data->format('Y-m') === $mes->format('Y-m')) {
                return $data;
            }
        }

        return $this->diaUtil($minimo);
    }

    /** Amanha (ou o dia util seguinte). */
    public function primeiraDataPossivel(): Carbon
    {
        return $this->diaUtil(now()->startOfDay()->addDay());
    }

    public function diaUtil(Carbon $data): Carbon
    {
        $data = $data->copy()->startOfDay();
        while ($this->motivoDiaNaoUtil($data) !== null) {
            $data->addDay();
        }

        return $data;
    }

    /** null = dia util; senao, o motivo (fim de semana / feriado). */
    public function motivoDiaNaoUtil(Carbon $data): ?string
    {
        if ($data->isWeekend()) {
            return 'é fim de semana';
        }

        // Feriados TARGET (sistema de pagamentos europeu) que nao sao nacionais.
        $md = $data->format('m-d');
        if ($md === '12-26') {
            return 'é feriado bancário (26 de dezembro)';
        }
        $pascoa = Carbon::createFromTimestamp(easter_date($data->year))->startOfDay();
        if ($data->isSameDay($pascoa->copy()->addDay())) {
            return 'é feriado bancário (Segunda-feira de Páscoa)';
        }

        $feriado = $this->feriados->holidayForCorporate($data, null);
        if ($feriado !== null && ($feriado['type'] ?? 'nacional') === 'nacional') {
            return 'é feriado ('.$feriado['name'].')';
        }

        return null;
    }

    /** "Junho 2026", "Junho e Julho 2026", "Dezembro 2026 a Janeiro 2027"... */
    public function descreverMeses(string $inicio, string $fim): string
    {
        [$ai, $mi] = array_map('intval', explode('-', $inicio));
        [$af, $mf] = array_map('intval', explode('-', $fim));
        $nome = fn (int $m): string => self::MESES[$m];

        if ($inicio === $fim) {
            return $nome($mi).' '.$ai;
        }
        $n = ($af - $ai) * 12 + ($mf - $mi) + 1;
        $ligacao = $n === 2 ? ' e ' : ' a ';

        return $ai === $af
            ? $nome($mi).$ligacao.$nome($mf).' '.$af
            : $nome($mi).' '.$ai.$ligacao.$nome($mf).' '.$af;
    }

    /* ------------------------------------------------------------------ gerar */

    /**
     * Cria a cobranca, gera e valida o XML e avanca o "ultimo mes cobrado".
     *
     * @throws RuntimeException com uma mensagem para mostrar ao utilizador
     */
    public function gerar(SepaMandato $mandato, Carbon $data, int $nMeses, float $valor, ?string $utilizador = null): SepaCobranca
    {
        $data = $data->copy()->startOfDay();

        if (! $mandato->ativo) {
            throw new RuntimeException('Este mandato está desativado.');
        }
        if ($data->lt($this->primeiraDataPossivel())) {
            throw new RuntimeException('A data de cobrança tem de ser a partir de '.$this->primeiraDataPossivel()->format('d/m/Y').'.');
        }
        if (($motivo = $this->motivoDiaNaoUtil($data)) !== null) {
            throw new RuntimeException('A data '.$data->format('d/m/Y').' '.$motivo.'. Escolhe um dia útil.');
        }
        $mesCobranca = $data->format('Y-m');
        $jaExiste = $mandato->cobrancas()->where('mes_cobranca', $mesCobranca)->first();
        if ($jaExiste !== null) {
            throw new RuntimeException('Já há uma cobrança de '.$mandato->nome_devedor.' em '.$this->descreverMeses($mesCobranca, $mesCobranca)
                .' (dia '.$jaExiste->data_cobranca->format('d/m').'). O banco só aceita uma por mês.');
        }

        $pendentes = $this->mesesPendentes($mandato, $data);
        if ($pendentes === []) {
            throw new RuntimeException('Não há meses por cobrar até '.$data->format('d/m/Y').'. (Cobra-se sempre o mês anterior.)');
        }
        if ($nMeses < 1 || $nMeses > count($pendentes)) {
            throw new RuntimeException('Só há '.count($pendentes).' mês(es) por cobrar nesta data.');
        }
        if ($valor <= 0) {
            throw new RuntimeException('O valor tem de ser maior que zero.');
        }

        $meses = array_slice($pendentes, 0, $nMeses);
        $inicio = $meses[0];
        $fim = end($meses);
        $credor = $this->credor();

        $msgId = $this->textoSepa(Str::upper($credor['prefixo']).str_replace('-', '', $mesCobranca).preg_replace('/[^A-Za-z0-9]/', '', $mandato->mandato_ref), 35);
        if (SepaCobranca::query()->where('msg_id', $msgId)->exists()) {
            $msgId = substr($msgId, 0, 31).strtoupper(Str::random(4));
        }
        $endToEnd = $this->textoSepa('HM-'.$mandato->mandato_ref.'-'.str_replace('-', '', $inicio).($inicio !== $fim ? '-'.str_replace('-', '', $fim) : ''), 35);
        $descricao = $this->textoSepa(trim($credor['descricao'].' - '.$this->descreverMeses($inicio, $fim), ' -'), 140);

        $xml = $this->xml($credor, $mandato, $data, round($valor, 2), $msgId, $endToEnd, $descricao);

        return DB::transaction(function () use ($mandato, $data, $mesCobranca, $inicio, $fim, $nMeses, $valor, $msgId, $endToEnd, $descricao, $xml, $utilizador): SepaCobranca {
            $cobranca = $mandato->cobrancas()->create([
                'msg_id' => $msgId,
                'end_to_end_id' => $endToEnd,
                'data_cobranca' => $data->toDateString(),
                'mes_cobranca' => $mesCobranca,
                'mes_inicio' => $inicio,
                'mes_fim' => $fim,
                'n_meses' => $nMeses,
                'valor' => round($valor, 2),
                'descricao' => $descricao,
                'ultimo_mes_anterior' => $mandato->ultimo_mes_cobrado,
                'xml' => $xml,
                'gerado_por' => $utilizador,
            ]);

            $mandato->update(['ultimo_mes_cobrado' => $fim]);

            return $cobranca;
        });
    }

    /**
     * Apaga uma cobranca gerada por engano. So a mais recente de cada cliente,
     * para o "ultimo mes cobrado" voltar atras sem buracos.
     */
    public function desfazer(SepaCobranca $cobranca): void
    {
        $mandato = $cobranca->mandato;
        $ultima = $mandato->cobrancas()->orderByDesc('mes_fim')->orderByDesc('id')->first();
        if ($ultima === null || $ultima->id !== $cobranca->id) {
            throw new RuntimeException('Só se pode apagar a cobrança mais recente deste cliente.');
        }

        DB::transaction(function () use ($cobranca, $mandato): void {
            $mandato->update(['ultimo_mes_cobrado' => $cobranca->ultimo_mes_anterior]);
            $cobranca->delete();
        });
    }

    /* ------------------------------------------------------------------ XML */

    /** @param array<string,string> $credor */
    public function xml(array $credor, SepaMandato $mandato, Carbon $data, float $valor, string $msgId, string $endToEnd, string $descricao): string
    {
        $montante = number_format($valor, 2, '.', '');

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->xmlStandalone = true;
        $doc->formatOutput = true;

        $root = $doc->createElementNS(self::NS, 'Document');
        $doc->appendChild($root);
        $init = $this->el($doc, $root, 'CstmrDrctDbtInitn');

        // Cabecalho
        $grp = $this->el($doc, $init, 'GrpHdr');
        $this->el($doc, $grp, 'MsgId', $msgId);
        $this->el($doc, $grp, 'CreDtTm', now()->format('Y-m-d\TH:i:s'));
        $this->el($doc, $grp, 'NbOfTxs', '1');
        $this->el($doc, $grp, 'CtrlSum', $montante);
        $ip = $this->el($doc, $grp, 'InitgPty');
        $this->el($doc, $ip, 'Nm', $this->textoSepa($credor['nome'], 140));
        $this->idPrivado($doc, $ip, $msgId);

        // Bloco de pagamento
        $pmt = $this->el($doc, $init, 'PmtInf');
        $this->el($doc, $pmt, 'PmtInfId', $msgId);
        $this->el($doc, $pmt, 'PmtMtd', 'DD');
        $this->el($doc, $pmt, 'NbOfTxs', '1');
        $this->el($doc, $pmt, 'CtrlSum', $montante);
        $tp = $this->el($doc, $pmt, 'PmtTpInf');
        $this->el($doc, $this->el($doc, $tp, 'SvcLvl'), 'Cd', 'SEPA');
        $this->el($doc, $this->el($doc, $tp, 'LclInstrm'), 'Cd', 'CORE');
        $this->el($doc, $tp, 'SeqTp', 'RCUR');
        $this->el($doc, $pmt, 'ReqdColltnDt', $data->toDateString());
        $this->el($doc, $this->el($doc, $pmt, 'Cdtr'), 'Nm', $this->textoSepa($credor['nome'], 70));
        $this->el($doc, $this->el($doc, $this->el($doc, $pmt, 'CdtrAcct'), 'Id'), 'IBAN', self::normalizarIban($credor['iban']));
        $this->el($doc, $this->el($doc, $this->el($doc, $pmt, 'CdtrAgt'), 'FinInstnId'), 'BICFI', strtoupper(trim($credor['bic'])));
        $this->el($doc, $pmt, 'ChrgBr', 'SLEV');
        $this->idPrivado($doc, $this->el($doc, $pmt, 'CdtrSchmeId'), strtoupper(trim($credor['identificador'])));

        // Transacao
        $tx = $this->el($doc, $pmt, 'DrctDbtTxInf');
        $this->el($doc, $this->el($doc, $tx, 'PmtId'), 'EndToEndId', $endToEnd);
        $amt = $this->el($doc, $tx, 'InstdAmt', $montante);
        $amt->setAttribute('Ccy', 'EUR');
        $mnd = $this->el($doc, $this->el($doc, $tx, 'DrctDbtTx'), 'MndtRltdInf');
        $this->el($doc, $mnd, 'MndtId', $this->textoSepa($mandato->mandato_ref, 35));
        $this->el($doc, $mnd, 'DtOfSgntr', $mandato->data_assinatura->toDateString());
        $this->el($doc, $mnd, 'AmdmntInd', 'false');
        $agt = $this->el($doc, $this->el($doc, $tx, 'DbtrAgt'), 'FinInstnId');
        if (filled($mandato->bic)) {
            $this->el($doc, $agt, 'BICFI', strtoupper(trim($mandato->bic)));
        } else {
            $this->el($doc, $this->el($doc, $agt, 'Othr'), 'Id', 'NOTPROVIDED');
        }
        $this->el($doc, $this->el($doc, $tx, 'Dbtr'), 'Nm', $this->textoSepa($mandato->nome_devedor, 70));
        $this->el($doc, $this->el($doc, $this->el($doc, $tx, 'DbtrAcct'), 'Id'), 'IBAN', self::normalizarIban($mandato->iban));
        $this->el($doc, $this->el($doc, $tx, 'RmtInf'), 'Ustrd', $descricao);

        $xml = $doc->saveXML();

        $this->validarEsquema($xml);

        return $xml;
    }

    /** Valida contra o XSD oficial; lanca excecao com os erros se falhar. */
    public function validarEsquema(string $xml): void
    {
        $xsd = resource_path('sepa/pain.008.001.08.xsd');
        if (! is_file($xsd)) {
            throw new RuntimeException('Falta o ficheiro resources/sepa/pain.008.001.08.xsd.');
        }

        $anterior = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $doc = new DOMDocument();
        $doc->loadXML($xml);
        $ok = $doc->schemaValidate($xsd);
        $erros = array_map(fn ($e) => trim($e->message), libxml_get_errors());
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        if (! $ok) {
            throw new RuntimeException('O ficheiro gerado não passou na validação SEPA: '.implode(' | ', array_slice($erros, 0, 3)));
        }
    }

    private function el(DOMDocument $doc, DOMElement $pai, string $nome, ?string $valor = null): DOMElement
    {
        $el = $doc->createElementNS(self::NS, $nome);
        if ($valor !== null) {
            $el->appendChild($doc->createTextNode($valor));
        }
        $pai->appendChild($el);

        return $el;
    }

    private function idPrivado(DOMDocument $doc, DOMElement $pai, string $id): void
    {
        $this->el($doc, $this->el($doc, $this->el($doc, $this->el($doc, $pai, 'Id'), 'PrvtId'), 'Othr'), 'Id', $id);
    }

    /* ------------------------------------------------------------------ utilitarios */

    /** Texto no conjunto de caracteres aceite pelo SEPA (sem acentos nem simbolos). */
    public function textoSepa(string $texto, int $max): string
    {
        $texto = Str::ascii($texto);
        $texto = preg_replace("/[^A-Za-z0-9\/\-?:().,'+ ]/", ' ', $texto) ?? '';
        $texto = trim(preg_replace('/\s+/', ' ', $texto) ?? '');

        return mb_substr($texto, 0, $max);
    }

    public static function normalizarIban(?string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $iban) ?? '');
    }

    /** Verifica o digito de controlo do IBAN (mod 97). */
    public static function ibanValido(?string $iban): bool
    {
        $iban = self::normalizarIban($iban);
        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban)) {
            return false;
        }
        if (str_starts_with($iban, 'PT') && strlen($iban) !== 25) {
            return false;
        }

        $rearranjado = substr($iban, 4).substr($iban, 0, 4);
        $numerico = '';
        foreach (str_split($rearranjado) as $c) {
            $numerico .= ctype_alpha($c) ? (string) (ord($c) - 55) : $c;
        }
        $resto = 0;
        foreach (str_split($numerico, 7) as $bloco) {
            $resto = (int) (($resto.$bloco) % 97);
        }

        return $resto === 1;
    }

    public static function bicValido(?string $bic): bool
    {
        return (bool) preg_match('/^[A-Z]{6}[A-Z2-9][A-NP-Z0-9]([A-Z0-9]{3})?$/', strtoupper(trim((string) $bic)));
    }

    public static function nomeMes(string $ym): string
    {
        [$a, $m] = array_map('intval', explode('-', $ym));

        return (self::MESES[$m] ?? $ym).' '.$a;
    }
}
