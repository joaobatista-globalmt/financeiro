<?php
/**
 * ContasReceberController - CRUD de Contas a Receber
 *
 * Espelho do ContasPagarController, mas com:
 *   - clientes no lugar de fornecedores
 *   - status 'recebida' no lugar de 'paga'
 *   - data_recebimento / valor_recebido
 *   - ao receber: gera movimentação de ENTRADA na conta bancária
 *
 * Suporta parcelamento e recorrência (mesmo padrão).
 */

declare(strict_types=1);

final class ContasReceberController
{
    /**
     * Lista contas a receber com filtros.
     */
    public function index(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];

        // Filtros
        $status      = $_GET['status']      ?? '';
        $clienteId   = (int)($_GET['cliente_id']   ?? 0);
        $categoriaId = (int)($_GET['categoria_id'] ?? 0);
        $dataInicio  = $_GET['data_inicio']  ?? '';
        $dataFim     = $_GET['data_fim']     ?? '';

        $sql = '
            SELECT cr.*,
                   c.razao_social AS cliente_nome,
                   cat.nome AS categoria_nome,
                   cat.cor AS categoria_cor,
                   cb.descricao AS conta_bancaria_descricao,
                   u.nome AS usuario_criacao_nome
            FROM contas_receber cr
            JOIN clientes c     ON c.id = cr.cliente_id
            JOIN categorias cat ON cat.id = cr.categoria_id
            LEFT JOIN contas_bancarias cb ON cb.id = cr.conta_bancaria_id
            JOIN usuarios u ON u.id = cr.usuario_criacao_id
            WHERE cr.empresa_id = ?
        ';
        $params = [$empresaId];

        if (in_array($status, ['pendente', 'aprovada', 'recebida', 'cancelada'], true)) {
            $sql .= ' AND cr.status = ?';
            $params[] = $status;
        }
        if ($clienteId > 0) {
            $sql .= ' AND cr.cliente_id = ?';
            $params[] = $clienteId;
        }
        if ($categoriaId > 0) {
            $sql .= ' AND cr.categoria_id = ?';
            $params[] = $categoriaId;
        }
        if (!empty($dataInicio)) {
            $sql .= ' AND cr.data_vencimento >= ?';
            $params[] = $dataInicio;
        }
        if (!empty($dataFim)) {
            $sql .= ' AND cr.data_vencimento <= ?';
            $params[] = $dataFim;
        }

        $sql .= ' ORDER BY cr.status, cr.data_vencimento ASC';

        $db = Database::getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $contas = $stmt->fetchAll();

        // Para os selects de filtro
        $clientes   = $this->listarClientes($empresaId);
        $categorias = $this->listarCategorias($empresaId);

        // Cards resumo
        $resumo = $this->calcularResumo($empresaId);

        layout('Contas a Receber', 'contas_receber/index.php', [
            'contas'      => $contas,
            'clientes'    => $clientes,
            'categorias'  => $categorias,
            'resumo'      => $resumo,
            'filtros'     => [
                'status'       => $status,
                'cliente_id'   => $clienteId,
                'categoria_id' => $categoriaId,
                'data_inicio'  => $dataInicio,
                'data_fim'     => $dataFim,
            ],
        ]);
    }

    /**
     * Drill-down: retorna os lancamentos que compoem o valor de um card especifico.
     * GET: ?action=drilldown&card=atrasadas|proximos7|pendente|recebido_mes
     * Retorna JSON.
     */
    public function drillDown(): void
    {
        Auth::require();
        header('Content-Type: application/json; charset=utf-8');

        $empresaId  = Auth::user()['empresa_id'];
        $card       = $_GET['card'] ?? '';
        $hoje       = date('Y-m-d');
        $hojeMais7  = date('Y-m-d', strtotime('+7 days'));
        $primeiroDia = date('Y-m-01');
        $ultimoDia   = date('Y-m-t');

        $titulos = [
            'atrasadas'    => 'Atrasadas',
            'proximos7'    => 'Pr\u00f3x. 7 dias',
            'pendente'     => 'Total Pendente',
            'recebido_mes' => 'Recebido no M\u00eas',
        ];

        if (!isset($titulos[$card])) {
            http_response_code(400);
            echo json_encode(['erro' => 'card invalido']);
            return;
        }

        $where  = ' AND cr.empresa_id = ?';
        $params = [$empresaId];

        switch ($card) {
            case 'atrasadas':
                $where   .= ' AND cr.status IN ("pendente","aprovada") AND cr.data_vencimento < ?';
                $params[] = $hoje;
                $campoTotal = 'valor';
                break;
            case 'proximos7':
                $where   .= ' AND cr.status IN ("pendente","aprovada") AND cr.data_vencimento BETWEEN ? AND ?';
                $params[] = $hoje;
                $params[] = $hojeMais7;
                $campoTotal = 'valor';
                break;
            case 'pendente':
                $where   .= ' AND cr.status IN ("pendente","aprovada")';
                $campoTotal = 'valor';
                break;
            case 'recebido_mes':
                $where   .= ' AND cr.status = "recebida" AND cr.data_recebimento BETWEEN ? AND ?';
                $params[] = $primeiroDia;
                $params[] = $ultimoDia;
                $campoTotal = 'valor_recebido';
                break;
        }

        $sql = "SELECT cr.id, cr.data_vencimento, cr.data_recebimento, cr.descricao, cr.valor, cr.valor_recebido, cr.status, cr.parcela_atual, cr.parcelas, cr.numero_documento, cr.forma_recebimento,
                       c.razao_social AS cliente_nome,
                       cat.nome AS categoria_nome, cat.cor AS categoria_cor
                FROM contas_receber cr
                JOIN clientes c ON c.id = cr.cliente_id
                JOIN categorias cat ON cat.id = cr.categoria_id
                WHERE 1=1 $where
                ORDER BY cr.data_vencimento ASC";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);
        $contas = $stmt->fetchAll();

        $total = 0.0;
        foreach ($contas as $cc) {
            $total += (float)($cc[$campoTotal] ?? 0);
        }

        echo json_encode([
            'card'    => $card,
            'titulo'  => $titulos[$card],
            'total'   => $total,
            'qtd'     => count($contas),
            'contas'  => $contas,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function form(): void
    {
        Auth::require();
        $id = (int)($_GET['id'] ?? 0);
        $conta = null;

        $empresaId = Auth::user()['empresa_id'];

        if ($id > 0) {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT * FROM contas_receber WHERE id = ? AND empresa_id = ?');
            $stmt->execute([$id, $empresaId]);
            $conta = $stmt->fetch();

            if (!$conta) {
                Flash::set('erro', 'Conta a receber não encontrada.');
                redirect('contas_receber.php');
            }
        }

        $clientes     = $this->listarClientes($empresaId);
        $categorias   = $this->listarCategorias($empresaId, true);
        $contasBanco  = $this->listarContasBancarias($empresaId);

        layout($conta ? 'Editar Conta a Receber' : 'Nova Conta a Receber', 'contas_receber/form.php', [
            'conta'        => $conta,
            'clientes'     => $clientes,
            'categorias'   => $categorias,
            'contasBanco'  => $contasBanco,
        ]);
    }

    public function salvar(): void
    {
        Auth::require();
        Permissao::requer('criar', 'contas_receber.php');

        $empresaId = Auth::user()['empresa_id'];
        $id = (int)($_POST['id'] ?? 0);
        $parcelas = max(1, (int)($_POST['parcelas'] ?? 1));

        if (empty(trim($_POST['descricao'] ?? ''))) {
            Flash::set('erro', 'Descrição é obrigatória.');
            redirect($id > 0 ? "conta_receber_form.php?id=$id" : 'conta_receber_form.php');
        }
        if (!is_numeric($_POST['valor'] ?? '') || (float)$_POST['valor'] <= 0) {
            Flash::set('erro', 'Valor inválido.');
            redirect($id > 0 ? "conta_receber_form.php?id=$id" : 'conta_receber_form.php');
        }
        if (empty($_POST['data_vencimento'] ?? '')) {
            Flash::set('erro', 'Data de vencimento é obrigatória.');
            redirect($id > 0 ? "conta_receber_form.php?id=$id" : 'conta_receber_form.php');
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            if ($id > 0) {
                // Edição simples (não mexe em parcelas já geradas — só na conta-pai)
                $dados = $this->coletarDadosFormulario($empresaId);
                $dados['id'] = $id;
                $dados['empresa_id'] = $empresaId;
                $stmt = $db->prepare('
                    UPDATE contas_receber SET
                        cliente_id=:cliente_id, categoria_id=:categoria_id, descricao=:descricao,
                        numero_documento=:numero_documento, valor=:valor, data_emissao=:data_emissao,
                        data_vencimento=:data_vencimento, forma_recebimento=:forma_recebimento,
                        observacoes=:observacoes
                    WHERE id=:id AND empresa_id=:empresa_id
                ');
                $stmt->execute($dados);
                Flash::set('sucesso', 'Conta a receber atualizada.');
            } else {
                if ($parcelas > 1) {
                    // Criar conta-pai + N filhas
                    $dados = $this->coletarDadosFormulario($empresaId);
                    $dados['empresa_id']     = $empresaId;
                    $dados['parcelas']       = $parcelas;
                    $dados['parcela_atual']  = 1;
                    $dados['status']         = 'pendente';
                    $dados['usuario_criacao_id'] = Auth::user()['id'];

                    $stmt = $db->prepare('
                        INSERT INTO contas_receber
                            (empresa_id, cliente_id, categoria_id, descricao, numero_documento, valor,
                             data_emissao, data_vencimento, forma_recebimento, observacoes,
                             parcelas, parcela_atual, status, usuario_criacao_id)
                        VALUES
                            (:empresa_id, :cliente_id, :categoria_id, :descricao, :numero_documento, :valor,
                             :data_emissao, :data_vencimento, :forma_recebimento, :observacoes,
                             :parcelas, :parcela_atual, :status, :usuario_criacao_id)
                    ');
                    $stmt->execute($dados);
                    $contaPaiId = (int)$db->lastInsertId();

                    // Gerar filhas
                    $valorParcela = round($dados['valor'] / $parcelas, 2);
                    $vencimentoBase = new DateTime($dados['data_vencimento']);

                    for ($i = 2; $i <= $parcelas; $i++) {
                        $vencimento = clone $vencimentoBase;
                        $vencimento->modify("+" . ($i - 1) . " months");

                        $stmtF = $db->prepare('
                            INSERT INTO contas_receber
                                (empresa_id, cliente_id, categoria_id, descricao, numero_documento, valor,
                                 data_emissao, data_vencimento, forma_recebimento, observacoes,
                                 parcelas, parcela_atual, conta_pai_id, status, usuario_criacao_id)
                            VALUES
                                (:empresa_id, :cliente_id, :categoria_id, :descricao, :numero_documento, :valor,
                                 :data_emissao, :data_vencimento, :forma_recebimento, :observacoes,
                                 :parcelas, :parcela_atual, :conta_pai_id, :status, :usuario_criacao_id)
                        ');
                        $stmtF->execute([
                            'empresa_id' => $empresaId,
                            'cliente_id' => $dados['cliente_id'],
                            'categoria_id' => $dados['categoria_id'],
                            'descricao' => $dados['descricao'] . " (parcela $i/$parcelas)",
                            'numero_documento' => $dados['numero_documento'],
                            'valor' => $valorParcela,
                            'data_emissao' => $dados['data_emissao'],
                            'data_vencimento' => $vencimento->format('Y-m-d'),
                            'forma_recebimento' => $dados['forma_recebimento'],
                            'observacoes' => $dados['observacoes'],
                            'parcelas' => $parcelas,
                            'parcela_atual' => $i,
                            'conta_pai_id' => $contaPaiId,
                            'status' => 'pendente',
                            'usuario_criacao_id' => Auth::user()['id'],
                        ]);
                    }

                    // Atualiza descrição da pai também
                    $stmtUp = $db->prepare('UPDATE contas_receber SET descricao = ? WHERE id = ?');
                    $stmtUp->execute([$dados['descricao'] . " (parcela 1/$parcelas)", $contaPaiId]);

                    Flash::set('sucesso', "Conta a receber parcelada em $parcelas vezes.");
                } else {
                    // Criação simples
                    $dados = $this->coletarDadosFormulario($empresaId);
                    $dados['empresa_id']     = $empresaId;
                    $dados['parcelas']       = 1;
                    $dados['parcela_atual']  = 1;
                    $dados['status']         = 'pendente';
                    $dados['usuario_criacao_id'] = Auth::user()['id'];

                    $stmt = $db->prepare('
                        INSERT INTO contas_receber
                            (empresa_id, cliente_id, categoria_id, descricao, numero_documento, valor,
                             data_emissao, data_vencimento, forma_recebimento, observacoes,
                             parcelas, parcela_atual, status, usuario_criacao_id)
                        VALUES
                            (:empresa_id, :cliente_id, :categoria_id, :descricao, :numero_documento, :valor,
                             :data_emissao, :data_vencimento, :forma_recebimento, :observacoes,
                             :parcelas, :parcela_atual, :status, :usuario_criacao_id)
                    ');
                    $stmt->execute($dados);
                    Flash::set('sucesso', 'Conta a receber criada.');
                }
            }

            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('[ContasReceber] Erro: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao salvar conta a receber.');
        }

        redirect('contas_receber.php');
    }

    /**
     * Fase 2.7: Quando uma CR eh marcada como 'recebida', atualiza a fatura
     * correspondente (numero_documento='FAT-{id}') para status='paga'.
     *
     * Logica:
     * - numero_documento da CR segue padrao 'FAT-{fatura_id}'
     * - Extrai o ID da fatura
     * - UPDATE faturas SET status='paga', data_pagamento=?, valor_pago=?
     *   WHERE id=? AND empresa_id=? AND status != 'cancelada'
     *
     * Retorna: ['ok' => bool, 'msg' => string, 'fatura_id' => int|null]
     */
    private function atualizarFaturaPorContaReceber(
        int $contaId,
        int $empresaId,
        string $dataRecebimento,
        float $valorRecebido
    ): array {
        $db = Database::getConnection();

        // Busca o numero_documento da CR
        $stmt = $db->prepare("SELECT numero_documento FROM contas_receber WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$contaId, $empresaId]);
        $numeroDoc = $stmt->fetchColumn();

        if (!$numeroDoc || strpos($numeroDoc, 'FAT-') !== 0) {
            // CR nao veio de uma fatura - nao faz nada
            return ['ok' => true, 'msg' => 'CR sem vinculo com fatura (numero_documento=' . ($numeroDoc ?: 'NULL') . ')', 'fatura_id' => null];
        }

        // Extrai o ID da fatura: 'FAT-123' -> 123
        $faturaId = (int)substr($numeroDoc, 4);
        if ($faturaId <= 0) {
            return ['ok' => false, 'msg' => 'numero_documento invalido: ' . $numeroDoc, 'fatura_id' => null];
        }

        // Atualiza a fatura (so se NAO estiver cancelada)
        $stmtUp = $db->prepare("
            UPDATE faturas SET
                status = 'paga',
                data_pagamento = :data_pagamento,
                valor_pago = :valor_pago,
                updated_at = NOW()
            WHERE id = :id
              AND empresa_id = :empresa_id
              AND status != 'cancelada'
        ");
        $stmtUp->execute([
            'data_pagamento' => $dataRecebimento,
            'valor_pago'     => $valorRecebido,
            'id'             => $faturaId,
            'empresa_id'     => $empresaId,
        ]);

        $rows = $stmtUp->rowCount();
        if ($rows > 0) {
            return ['ok' => true, 'msg' => "Fatura #{$faturaId} atualizada para 'paga'", 'fatura_id' => $faturaId];
        } else {
            // Pode ser que a fatura nao exista, ou ja esteja cancelada, ou status ja era 'paga'
            $stmtChk = $db->prepare("SELECT status FROM faturas WHERE id = ? AND empresa_id = ?");
            $stmtChk->execute([$faturaId, $empresaId]);
            $statusAtual = $stmtChk->fetchColumn();
            if (!$statusAtual) {
                return ['ok' => false, 'msg' => "Fatura #{$faturaId} nao encontrada", 'fatura_id' => $faturaId];
            }
            return ['ok' => true, 'msg' => "Fatura #{$faturaId} ja estava '{$statusAtual}' - sem alteracao", 'fatura_id' => $faturaId];
        }
    }

    public function gerarBoletoPdf(int $id): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT cr.*,
                   c.razao_social AS cliente_nome, c.cpf_cnpj AS cliente_doc,
                   c.endereco AS cliente_endereco,
                   c.cidade AS cliente_cidade,
                   c.uf AS cliente_uf, c.cep AS cliente_cep,
                   e.razao_social AS empresa_nome, e.cnpj AS empresa_cnpj,
                   cb.banco AS banco_nome, cb.agencia, cb.numero_conta, cb.digito,
                   cb.titular AS cedente_nome, cb.cpf_cnpj_titular AS cedente_doc
            FROM contas_receber cr
            JOIN clientes c ON c.id = cr.cliente_id
            JOIN empresas e ON e.id = cr.empresa_id
            LEFT JOIN contas_bancarias cb ON cb.id = cr.conta_bancaria_id
            WHERE cr.id = ? AND cr.empresa_id = ?
        ');
        $stmt->execute([$id, $empresaId]);
        $boleto = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$boleto) {
            Flash::set('erro', 'Conta a receber nao encontrada.');
            redirect('contas_receber.php');
        }

        if (empty($boleto['banco_nome']) || $boleto['banco_nome'] === 'A definir') {
            $stmtCb = $db->prepare('
                SELECT banco, agencia, numero_conta, digito, titular, cpf_cnpj_titular
                FROM contas_bancarias
                WHERE empresa_id = ? AND ativo = 1 AND banco IS NOT NULL AND banco != "A definir"
                ORDER BY id ASC LIMIT 1
            ');
            $stmtCb->execute([$empresaId]);
            $cb = $stmtCb->fetch(PDO::FETCH_ASSOC);
            if ($cb) {
                $boleto['banco_nome']   = $cb['banco'];
                $boleto['agencia']      = $cb['agencia'];
                $boleto['numero_conta'] = $cb['numero_conta'];
                $boleto['digito']       = $cb['digito'];
                $boleto['cedente_nome'] = $cb['titular'] ?? $boleto['empresa_nome'];
                $boleto['cedente_doc']  = $cb['cpf_cnpj_titular'] ?? $boleto['empresa_cnpj'];
            } else {
                $boleto['banco_nome']   = 'Banco Padrao';
                $boleto['agencia']      = '0000';
                $boleto['numero_conta'] = '00000';
                $boleto['digito']       = '0';
                $boleto['cedente_nome'] = $boleto['empresa_nome'];
                $boleto['cedente_doc']  = $boleto['empresa_cnpj'];
            }
        }

        $boleto['nosso_numero'] = str_pad((string)$boleto['id'], 11, '0', STR_PAD_LEFT);

        // Fase 3.5: codigo de barras REAL com DV modulo 11 (padrao Febraban)
        // Banco do Brasil (001) - calculo real
        $boleto['codigo_barras'] = $this->calcularCodigoBarrasBB($boleto);
        $linhaSemFmt = $this->calcularLinhaDigitavelBB($boleto['codigo_barras']);
        $boleto['linha_digitavel'] = $this->formatarLinhaDigitavel($linhaSemFmt);
        $boleto['valor_extenso'] = $this->valorPorExtenso((float)$boleto['valor']);

        ob_start();
        $boleto_view = $boleto;
        require __DIR__ . '/../views/boleto/template.php';
        $html = ob_get_clean();

        $tmpHtml = '/tmp/boleto_' . $id . '_' . time() . '.html';
        $tmpPdf  = '/tmp/boleto_' . $id . '_' . time() . '.pdf';
        file_put_contents($tmpHtml, $html);

        $cmd = sprintf(
            'wkhtmltopdf --quiet --enable-local-file-access --orientation Portrait --margin-top 5mm --margin-bottom 5mm --margin-left 8mm --margin-right 8mm --print-media-type %s %s 2>&1',
            escapeshellarg($tmpHtml),
            escapeshellarg($tmpPdf)
        );
        exec($cmd, $output, $rc);

        if ($rc !== 0 || !file_exists($tmpPdf)) {
            error_log('[Boleto] wkhtmltopdf falhou (rc=' . $rc . '): ' . implode("\n", $output));
            @unlink($tmpHtml);
            @unlink($tmpPdf);
            Flash::set('erro', 'Erro ao gerar PDF do boleto.');
            redirect('contas_receber.php');
        }

        @unlink($tmpHtml);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="boleto_CR_' . $id . '_' . date('Ymd') . '.pdf"');
        header('Content-Length: ' . filesize($tmpPdf));
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($tmpPdf);
        @unlink($tmpPdf);
        exit;
    }

    private function valorPorExtenso(float $valor): string
    {
        if ($valor <= 0) return 'zero reais';
        $inteiro = (int)$valor;
        $centavos = (int)round(($valor - $inteiro) * 100);

        $ext = '';
        if ($inteiro > 0) {
            $ext .= $this->numeroParaExtenso($inteiro) . ($inteiro == 1 ? ' real' : ' reais');
        }
        if ($centavos > 0) {
            if ($ext) $ext .= ' e ';
            $ext .= $this->numeroParaExtenso($centavos) . ($centavos == 1 ? ' centavo' : ' centavos');
        }
        return $ext;
    }

    /**
     * Converte numero inteiro (0-999999999) para extenso em portugues.
     * Implementacao PHP pura - nao depende da extensao intl.
     */
    private function numeroParaExtenso(int $n): string
    {
        if ($n == 0) return 'zero';
        if ($n == 100) return 'cem';

        $unidades = ['', 'um', 'dois', 'tres', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove', 'dez',
                     'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove'];
        $dezenas  = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
        $centenas = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];

        $partes = [];
        if ($n >= 1000000) {
            $mi = (int)($n / 1000000);
            $partes[] = $this->numeroParaExtenso($mi) . ($mi == 1 ? ' milhao' : ' milhoes');
            $n %= 1000000;
        }
        if ($n >= 1000) {
            $mil = (int)($n / 1000);
            if ($mil == 1) {
                $partes[] = 'mil';
            } else {
                $partes[] = $this->numeroParaExtenso($mil) . ' mil';
            }
            $n %= 1000;
        }
        if ($n >= 100) {
            $c = (int)($n / 100);
            $partes[] = $centenas[$c];
            $n %= 100;
        }
        if ($n >= 20) {
            $d = (int)($n / 10);
            $partes[] = $dezenas[$d];
            $n %= 10;
        } elseif ($n >= 10) {
            $partes[] = $unidades[$n];
            $n = 0;
        }
        if ($n > 0 && $n < 10) {
            $partes[] = $unidades[$n];
        }

        return implode(' e ', array_filter($partes));
    }

    /**
     * Calcula o DV (digito verificador) pelo modulo 11 padrao Febraban.
     * @param string|int $numero Numero (string de digitos ou int)
     * @return int DV (0-9)
     */
    private function dvModulo11($numero): int
    {
        $str = (string)$numero;
        $soma = 0;
        $peso = 2;
        // Percorre da direita pra esquerda
        for ($i = strlen($str) - 1; $i >= 0; $i--) {
            $soma += (int)$str[$i] * $peso;
            $peso++;
            if ($peso > 9) $peso = 2;  // ciclico
        }
        $resto = $soma % 11;
        $dv = 11 - $resto;
        // Padrao Febraban: se DV >= 10, vira 0
        if ($dv >= 10) $dv = 0;
        // Se DV == 0 (soma multipla de 11), DV = 0 tambem
        return $dv;
    }

    /**
     * Monta o codigo de barras Febraban (44 posicoes) para Banco do Brasil.
     * @param array $boleto dados do boleto (banco, agencia, conta, dv, nosso_numero, valor, data_vencimento)
     * @return string 44 digitos
     */
    private function calcularCodigoBarrasBB(array $b): string
    {
        // Fator de vencimento: dias desde 07/10/1997
        $base = strtotime('1997-10-07');
        $venc = strtotime($b['data_vencimento']);
        $fatorVenc = (int)round(($venc - $base) / 86400);

        // Valor em centavos (10 digitos com zeros a esquerda)
        $valorCent = (int)round(((float)$b['valor']) * 100);
        $valorFmt = str_pad((string)$valorCent, 10, '0', STR_PAD_LEFT);

        // Banco (3) + Moeda (1) = 4 pos
        // Fator (4) + Valor (10) = 14 pos
        // Campo Livre (25) = 25 pos
        // DV (1) = 1 pos (calculado depois)
        // Total = 44 pos

        // Campo Livre BB: 0000(4) + Agencia(4) + Conta(7) + DV_Conta(1) + Nosso_Numero(11) + Carteira(2)
        // Formato BB: "0000" + agencia(4) + conta(7) + conta_dv(1) + nosso_numero(11) + "21" (carteira 21 sem registro, ou "17" com registro)
        $agencia = str_pad(preg_replace('/\D/', '', $b['agencia'] ?? ''), 4, '0', STR_PAD_LEFT);
        $agencia = substr($agencia, -4);  // garante 4 digitos
        $conta = str_pad(preg_replace('/\D/', '', $b['numero_conta'] ?? ''), 7, '0', STR_PAD_LEFT);
        $conta = substr($conta, -7);
        $contaDv = (string)($b['digito'] ?? '0');
        $nossoNumero = str_pad((string)$b['nosso_numero'], 11, '0', STR_PAD_LEFT);
        $nossoNumero = substr($nossoNumero, -11);
        $carteira = '21';  // BB carteira 21 (sem registro) - configuravel no futuro

        $campoLivre = '0000' . $agencia . $conta . $contaDv . $nossoNumero . $carteira;
        $campoLivre = str_pad($campoLivre, 25, '0', STR_PAD_LEFT);

        $banco = '001';
        $moeda = '9';

        // Monta 43 digitos (sem o DV geral) pra calcular o DV
        $semDv = $banco . $moeda . $fatorVenc . $valorFmt . $campoLivre;
        $semDv = str_pad($semDv, 43, '0', STR_PAD_LEFT);
        $dvGeral = $this->dvModulo11($semDv);

        // Codigo de barras final: banco(3) + moeda(1) + DV(1) + fator(4) + valor(10) + campoLivre(25) = 44
        return $banco . $moeda . $dvGeral . $fatorVenc . $valorFmt . $campoLivre;
    }

    /**
     * Converte o codigo de barras (44 pos) na linha digitavel (47 pos com 3 DVs).
     * Formato: AAAA.A BBBBB.B CCCCCC.C D EEEEE.FFFFFF
     * @param string $cb codigo de barras de 44 digitos
     * @return string linha digitavel formatada (47 chars sem pontos/espacos)
     */
    private function calcularLinhaDigitavelBB(string $cb): string
    {
        // cb = BBB M D FFFF VVVVVVVVVV AAAA CCCCCCC C NNNNNNNNNNN 22 = 44
        //      3   1 1 4    10             4    7       1 11         2
        $banco = substr($cb, 0, 3);
        $moeda = substr($cb, 3, 1);
        $dv    = substr($cb, 4, 1);
        $fator = substr($cb, 5, 4);
        $valor = substr($cb, 9, 10);
        $campLivre = substr($cb, 19, 25);

        // Campo 1: banco(3) + moeda(1) + 5 primeiros do campLivre = 9 digitos
        // DV1 = modulo 11 sobre esses 9 digitos
        $c1 = substr($campLivre, 0, 5);
        $bloco1 = $banco . $moeda . $c1;
        $dv1 = $this->dvModulo11($bloco1);

        // Campo 2: 6-15 do campLivre = 10 digitos
        $c2 = substr($campLivre, 5, 10);
        $dv2 = $this->dvModulo11($c2);

        // Campo 3: 16-25 do campLivre = 10 digitos
        $c3 = substr($campLivre, 15, 10);
        $dv3 = $this->dvModulo11($c3);

        // Linha: BBBB.B DCCCC CCCC.C CCCCC.C D FFFFVVVVVVVVVVV
        //   AAAA.A BBBBB.B CCCCCC.C D EEEEE.FFFFFF
        //   ^      ^       ^       ^ ^
        //   C1     C2      C3     DV geral  fator + valor

        // Formato padrao Febraban: AAAA.A B(5)B.B C(5)C.C D EEEEE.FFFFFF
        $linha = $bloco1 . $dv1
               . $c2 . $dv2
               . $c3 . $dv3
               . $dv
               . $fator . $valor;

        return $linha;
    }

    /**
     * Formata a linha digitavel (47 digitos) com pontos/espacos padrao Febraban.
     * Formato: AAAA.A BBBBB.B CCCCCC.C D EEEEE.FFFFFF
     */
    private function formatarLinhaDigitavel(string $linha): string
    {
        // $linha tem 47 digitos
        $c1 = substr($linha, 0, 4) . '.' . substr($linha, 4, 5);
        $c2 = substr($linha, 10, 5) . '.' . substr($linha, 15, 5);
        $c3 = substr($linha, 21, 5) . '.' . substr($linha, 26, 5);
        $dv = $linha[32];
        $resto = substr($linha, 33);  // 14 chars: fator(4) + valor(10)
        $c5 = substr($resto, 0, 5) . '.' . substr($resto, 5, 6);
        return "$c1 $c2 $c3 $dv $c5";
    }


    public function acao(): void
    {
        Auth::require();
        $id = (int)($_POST['id'] ?? 0);
        $acao = $_POST['acao'] ?? '';
        $empresaId = Auth::user()['empresa_id'];
        $usuarioId = Auth::user()['id'];

        if ($id <= 0) {
            Flash::set('erro', 'ID inválido.');
            redirect('contas_receber.php');
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare('SELECT * FROM contas_receber WHERE id = ? AND empresa_id = ?');
            $stmt->execute([$id, $empresaId]);
            $conta = $stmt->fetch();

            if (!$conta) {
                Flash::set('erro', 'Conta não encontrada.');
                redirect('contas_receber.php');
            }

            if ($acao === 'aprovar') {
                Permissao::requer('aprovar', 'contas_receber.php');
                if ($conta['status'] !== 'pendente') {
                    Flash::set('erro', 'Só é possível aprovar contas pendentes.');
                    redirect('contas_receber.php');
                }
                $stmtU = $db->prepare('UPDATE contas_receber SET status="aprovada", usuario_aprovacao_id=?, data_aprovacao=NOW() WHERE id=?');
                $stmtU->execute([$usuarioId, $id]);
                Flash::set('sucesso', 'Conta aprovada.');
            } elseif ($acao === 'receber') {
                Permissao::requer('receber', 'contas_receber.php');
                if (!in_array($conta['status'], ['pendente', 'aprovada'], true)) {
                    Flash::set('erro', 'Só é possível receber contas pendentes ou aprovadas.');
                    redirect('contas_receber.php');
                }

                $contaBancariaId = (int)($_POST['conta_bancaria_id'] ?? 0);
                if ($contaBancariaId <= 0) {
                    Flash::set('erro', 'Selecione a conta bancária que recebeu o valor.');
                    redirect("conta_receber_form.php?id=$id");
                }
                $dataRecebimento = $_POST['data_recebimento'] ?? date('Y-m-d');
                $valorRecebido   = (float)str_replace(',', '.', $_POST['valor_recebido'] ?? $conta['valor']);

                // Atualiza conta
                $stmtU = $db->prepare('
                    UPDATE contas_receber SET
                        status="recebida",
                        data_recebimento=:data_recebimento,
                        valor_recebido=:valor_recebido,
                        conta_bancaria_id=:conta_bancaria_id,
                        usuario_recebimento_id=:usuario_id
                    WHERE id=:id
                ');
                $stmtU->execute([
                    'data_recebimento'  => $dataRecebimento,
                    'valor_recebido'    => $valorRecebido,
                    'conta_bancaria_id' => $contaBancariaId,
                    'usuario_id'        => $usuarioId,
                    'id'                => $id,
                ]);

                // Gera movimentação automática de entrada
                MovimentacoesController::lancar(
                    $empresaId,
                    $contaBancariaId,
                    $dataRecebimento,
                    'entrada',
                    'conta_receber',
                    $valorRecebido,
                    'Recebimento: ' . $conta['descricao'],
                    $usuarioId,
                    null,
                    $id,
                    null
                );

                // Fase 2.7: se a CR veio de uma fatura (numero_documento='FAT-{id}'),
                // atualiza a fatura automaticamente para status='paga'
                $hookResult = $this->atualizarFaturaPorContaReceber($id, $empresaId, $dataRecebimento, $valorRecebido);
                if ($hookResult['fatura_id']) {
                    // Log no error_log pra audit (em prod) e no Flash pro user
                    error_log('[ContasReceber Fase 2.7] ' . $hookResult['msg']);
                    $msgAtual = $_SESSION['flash_sucesso'] ?? '';
                    $_SESSION['flash_sucesso'] = $msgAtual . ' | ' . $hookResult['msg'];
                }

                Flash::set('sucesso', 'Recebimento registrado e lançado no extrato.');
            } elseif ($acao === 'cancelar') {
                if (in_array($conta['status'], ['recebida'], true)) {
                    Flash::set('erro', 'Conta já recebida. Use estorno se necessário.');
                    redirect('contas_receber.php');
                }
                $stmtU = $db->prepare('UPDATE contas_receber SET status="cancelada" WHERE id=?');
                $stmtU->execute([$id]);
                Flash::set('sucesso', 'Conta cancelada.');
            } elseif ($acao === 'excluir') {
                Permissao::requer('excluir', 'contas_receber.php');
                if ($conta['status'] === 'recebida') {
                    Flash::set('erro', 'Não é possível excluir conta recebida. Estorne o recebimento primeiro.');
                    redirect('contas_receber.php');
                }

                // Bloqueia se tem movimentação bancária
                $stmtMov = $db->prepare('SELECT COUNT(*) AS total FROM movimentacoes_bancarias WHERE conta_receber_id = ? AND empresa_id = ?');
                $stmtMov->execute([$id, $empresaId]);
                if ((int)$stmtMov->fetchColumn() > 0) {
                    Flash::set('erro', 'Conta possui movimentação bancária vinculada. Estorne o recebimento antes de excluir.');
                    redirect('contas_receber.php');
                }

                // Bloqueia se for conta-pai com parcelas filhas
                $stmtParc = $db->prepare('SELECT COUNT(*) AS total FROM contas_receber WHERE conta_pai_id = ? AND id != ? AND empresa_id = ?');
                $stmtParc->execute([$id, $id, $empresaId]);
                if ((int)$stmtParc->fetchColumn() > 0) {
                    Flash::set('erro', 'Esta conta é a "pai" de um parcelamento. Exclua primeiro as parcelas filhas, ou cancele esta conta em vez de excluir.');
                    redirect('contas_receber.php');
                }

                $stmtD = $db->prepare('DELETE FROM contas_receber WHERE id = ? AND empresa_id = ?');
                $stmtD->execute([$id, $empresaId]);
                Flash::set('sucesso', 'Conta excluída definitivamente.');
            } else {
                Flash::set('erro', 'Ação inválida.');
            }

            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('[ContasReceber] Erro na ação: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao executar ação.');
        }

        redirect('contas_receber.php');
    }

    /**
     * GET /editar_recebimento.php?id=N
     * Form para editar recebimento de uma conta a receber já RECEBIDA.
     */
    public function editarRecebimento(): void
    {
        Auth::require();
        Permissao::requer('criar', 'contas_receber.php');
        $id = (int)($_GET['id'] ?? 0);
        $empresaId = Auth::user()['empresa_id'];

        if ($id <= 0) {
            redirect('contas_receber.php');
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('
            SELECT cr.*, c.razao_social AS cliente_nome,
                   cb.descricao AS conta_bancaria_descricao
            FROM contas_receber cr
            JOIN clientes c ON c.id = cr.cliente_id
            LEFT JOIN contas_bancarias cb ON cb.id = cr.conta_bancaria_id
            WHERE cr.id = ? AND cr.empresa_id = ?
        ');
        $stmt->execute([$id, $empresaId]);
        $conta = $stmt->fetch();

        if (!$conta) {
            Flash::set('erro', 'Conta não encontrada.');
            redirect('contas_receber.php');
        }

        if ($conta['status'] !== 'recebida') {
            Flash::set('erro', 'Só é possível editar recebimento de contas já recebidas. Status atual: ' . $conta['status']);
            redirect("conta_receber_detalhe.php?id=$id");
        }

        // Lista contas bancárias ativas
        $stmtCb = $db->prepare('
            SELECT id, descricao, tipo, banco FROM contas_bancarias
            WHERE empresa_id = ? AND ativo = 1
            ORDER BY descricao
        ');
        $stmtCb->execute([$empresaId]);
        $contasBanco = $stmtCb->fetchAll();

        layout('Editar Recebimento', 'contas_receber/editar_recebimento.php', [
            'conta'       => $conta,
            'contasBanco' => $contasBanco,
        ]);
    }

    /**
     * POST /editar_recebimento.php (Contas a Receber)
     * Salva edição do recebimento (data, valor, conta, forma).
     * Atualiza também a movimentação bancária correspondente.
     */
    public function salvarEdicaoRecebimento(): void
    {
        Auth::require();
        Permissao::requer('criar', 'contas_receber.php');
        $id = (int)($_POST['id'] ?? 0);
        $empresaId = Auth::user()['empresa_id'];

        if ($id <= 0) {
            Flash::set('erro', 'ID inválido.');
            redirect('contas_receber.php');
        }

        $novaData     = $_POST['data_recebimento'] ?? '';
        $novoValor    = (float)str_replace(',', '.', $_POST['valor_recebido'] ?? 0);
        $novaContaId  = (int)($_POST['conta_bancaria_id'] ?? 0);
        $novaForma    = $_POST['forma_recebimento'] ?? '';

        if (empty($novaData) || $novoValor <= 0 || $novaContaId <= 0) {
            Flash::set('erro', 'Preencha data, valor e conta bancária.');
            redirect("editar_recebimento.php?id=$id");
        }
        if (!in_array($novaForma, ['boleto','pix','transferencia','dinheiro','cartao','cheque','outros'], true)) {
            Flash::set('erro', 'Forma de recebimento inválida.');
            redirect("editar_recebimento.php?id=$id");
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            // Carrega a conta
            $stmt = $db->prepare('SELECT * FROM contas_receber WHERE id = ? AND empresa_id = ? FOR UPDATE');
            $stmt->execute([$id, $empresaId]);
            $conta = $stmt->fetch();

            if (!$conta) {
                throw new \RuntimeException('Conta não encontrada.');
            }
            if ($conta['status'] !== 'recebida') {
                throw new \RuntimeException('Só é possível editar recebimento de contas já recebidas.');
            }

            // Verifica conta bancária
            $stmtCb = $db->prepare('SELECT id FROM contas_bancarias WHERE id = ? AND empresa_id = ?');
            $stmtCb->execute([$novaContaId, $empresaId]);
            if (!$stmtCb->fetch()) {
                throw new \RuntimeException('Conta bancária inválida.');
            }

            // Atualiza a conta
            $stmtU = $db->prepare('
                UPDATE contas_receber SET
                    data_recebimento = :data_recebimento,
                    valor_recebido = :valor_recebido,
                    conta_bancaria_id = :conta_bancaria_id,
                    forma_recebimento = :forma_recebimento
                WHERE id = :id AND empresa_id = :empresa_id
            ');
            $stmtU->execute([
                'data_recebimento'  => $novaData,
                'valor_recebido'    => $novoValor,
                'conta_bancaria_id' => $novaContaId,
                'forma_recebimento' => $novaForma,
                'id'                => $id,
                'empresa_id'        => $empresaId,
            ]);

            // Atualiza movimentação bancária (se vinculada)
            $stmtM = $db->prepare('
                UPDATE movimentacoes_bancarias SET
                    conta_bancaria_id = :conta_bancaria_id,
                    data_movimento = :data_movimento,
                    valor = :valor,
                    descricao = :descricao
                WHERE conta_receber_id = :id AND empresa_id = :empresa_id
            ');
            $stmtM->execute([
                'conta_bancaria_id' => $novaContaId,
                'data_movimento'    => $novaData,
                'valor'             => $novoValor,
                'descricao'         => 'Recebimento: ' . $conta['descricao'],
                'id'                => $id,
                'empresa_id'        => $empresaId,
            ]);

            $db->commit();
            Flash::set('sucesso', 'Recebimento editado (conta e movimentação bancária atualizadas).');
            redirect("conta_receber_detalhe.php?id=$id");
        } catch (\Throwable $e) {
            $db->rollBack();
            error_log('[ContasReceber] Erro ao editar recebimento: ' . $e->getMessage());
            Flash::set('erro', 'Erro: ' . $e->getMessage());
            redirect("editar_recebimento.php?id=$id");
        }
    }

    public function detalhe(): void
    {
        Auth::require();
        $id = (int)($_GET['id'] ?? 0);
        $empresaId = Auth::user()['empresa_id'];

        if ($id <= 0) {
            redirect('contas_receber.php');
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('
            SELECT cr.*,
                   c.razao_social AS cliente_nome, c.cpf_cnpj AS cliente_doc,
                   cat.nome AS categoria_nome, cat.cor AS categoria_cor,
                   cb.descricao AS conta_bancaria_descricao,
                   u1.nome AS usuario_criacao_nome,
                   u2.nome AS usuario_aprovacao_nome,
                   u3.nome AS usuario_recebimento_nome
            FROM contas_receber cr
            JOIN clientes c ON c.id = cr.cliente_id
            JOIN categorias cat ON cat.id = cr.categoria_id
            LEFT JOIN contas_bancarias cb ON cb.id = cr.conta_bancaria_id
            JOIN usuarios u1 ON u1.id = cr.usuario_criacao_id
            LEFT JOIN usuarios u2 ON u2.id = cr.usuario_aprovacao_id
            LEFT JOIN usuarios u3 ON u3.id = cr.usuario_recebimento_id
            WHERE cr.id = ? AND cr.empresa_id = ?
        ');
        $stmt->execute([$id, $empresaId]);
        $conta = $stmt->fetch();

        if (!$conta) {
            Flash::set('erro', 'Conta não encontrada.');
            redirect('contas_receber.php');
        }

        // Anexos
        $stmtA = $db->prepare('SELECT * FROM anexos WHERE tipo_origem = "conta_receber" AND origem_id = ? ORDER BY data_upload DESC');
        $stmtA->execute([$id]);
        $anexos = $stmtA->fetchAll();

        // Parcelas relacionadas
        $parcelas = [];
        if ($conta['conta_pai_id']) {
            $stmtP = $db->prepare('SELECT * FROM contas_receber WHERE (id = ? OR conta_pai_id = ?) AND id != ? ORDER BY parcela_atual');
            $stmtP->execute([$conta['conta_pai_id'], $conta['conta_pai_id'], $id]);
            $parcelas = $stmtP->fetchAll();
        }

        layout('Detalhes da Conta a Receber', 'contas_receber/detalhe.php', [
            'conta'    => $conta,
            'anexos'   => $anexos,
            'parcelas' => $parcelas,
        ]);
    }

    private function coletarDadosFormulario(int $empresaId): array
    {
        return [
            'cliente_id'          => (int)$_POST['cliente_id'],
            'categoria_id'        => (int)$_POST['categoria_id'],
            'descricao'           => trim($_POST['descricao']),
            'numero_documento'    => trim($_POST['numero_documento'] ?? '') ?: null,
            'valor'               => (float)str_replace(',', '.', $_POST['valor']),
            'data_emissao'        => $_POST['data_emissao'] ?? date('Y-m-d'),
            'data_vencimento'     => $_POST['data_vencimento'],
            'forma_recebimento'   => $_POST['forma_recebimento'] ?? 'boleto',
            'observacoes'         => trim($_POST['observacoes'] ?? '') ?: null,
        ];
    }

    private function listarClientes(int $empresaId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id, razao_social, cpf_cnpj, pix_chave, pix_tipo FROM clientes WHERE empresa_id = ? AND ativo = 1 ORDER BY razao_social');
        $stmt->execute([$empresaId]);
        return $stmt->fetchAll();
    }

    private function listarCategorias(int $empresaId, bool $receita = false): array
    {
        $db = Database::getConnection();
        $sql = 'SELECT id, nome, cor FROM categorias WHERE empresa_id = ? AND ativo = 1';
        if ($receita) {
            $sql .= ' AND tipo IN ("receita", "ambos")';
        }
        $sql .= ' ORDER BY nome';
        $stmt = $db->prepare($sql);
        $stmt->execute([$empresaId]);
        return $stmt->fetchAll();
    }

    private function listarContasBancarias(int $empresaId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id, descricao, tipo, banco FROM contas_bancarias WHERE empresa_id = ? AND ativo = 1 ORDER BY descricao');
        $stmt->execute([$empresaId]);
        return $stmt->fetchAll();
    }

    private function calcularResumo(int $empresaId): array
    {
        $db = Database::getConnection();
        $hoje = date('Y-m-d');
        $semana = date('Y-m-d', strtotime('+7 days'));

        $stmt = $db->prepare('
            SELECT
                SUM(CASE WHEN status IN ("pendente","aprovada") AND data_vencimento < ? THEN valor ELSE 0 END) AS atrasadas,
                SUM(CASE WHEN status IN ("pendente","aprovada") AND data_vencimento BETWEEN ? AND ? THEN valor ELSE 0 END) AS proximos_7_dias,
                SUM(CASE WHEN status IN ("pendente","aprovada") THEN valor ELSE 0 END) AS total_pendente,
                SUM(CASE WHEN status = "recebida" AND MONTH(data_recebimento) = MONTH(?) AND YEAR(data_recebimento) = YEAR(?) THEN valor_recebido ELSE 0 END) AS recebido_mes
            FROM contas_receber
            WHERE empresa_id = ?
        ');
        $stmt->execute([$hoje, $hoje, $semana, $hoje, $hoje, $empresaId]);
        return $stmt->fetch();
    }
}