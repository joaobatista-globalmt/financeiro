<?php
/**
 * RelatorioController - Relatórios e exportações
 *
 * Tipos:
 *   1. Por período (contas a pagar e receber, pagas/recebidas e pendentes)
 *   2. Por categoria (consolidado Pagar + Receber)
 *   3. Por fornecedor / cliente
 *   4. Fluxo de caixa (entradas vs saídas por dia)
 *   5. Atrasadas (vencidas, não pagas/recebidas)
 *   6. Extrato de conta bancária (movimentações filtradas por conta + saldo)
 *
 * Exportação CSV (BOM UTF-8 + ;) ou PDF (wkhtmltopdf).
 */

declare(strict_types=1);

final class RelatorioController
{
    public function index(): void
    {
        Auth::require();
        layout('Relatórios', 'relatorios/index.php', []);
    }

    /**
     * Renderiza o relatório e a view de impressão.
     *
     * Para tipo 'extrato_conta' exige conta_id; usa view dedicada com cards
     * de saldo, totais e atalhos pra CSV/PDF.
     */
    public function show(): void
    {
        Auth::require();
        $tipo = $_GET['tipo'] ?? '';
        $empresaId = Auth::user()['empresa_id'];

        $dataInicio = $_GET['data_inicio'] ?? date('Y-m-01', strtotime('-2 months'));
        $dataFim    = $_GET['data_fim']    ?? date('Y-m-t');
        $statusFiltro = $_GET['status'] ?? []; // array, validado contra ENUM
        if (!is_array($statusFiltro)) {
            $statusFiltro = [$statusFiltro];
        }

        switch ($tipo) {
            case 'periodo':
                $dados = $this->relatorioPeriodo($empresaId, $dataInicio, $dataFim);
                layout('Relatório: ' . $tipo, 'relatorios/show_periodo.php', [
                    'tipo'       => $tipo,
                    'dados'      => $dados,
                    'dataInicio' => $dataInicio,
                    'dataFim'    => $dataFim,
                ]);
                return;
            case 'contas_pagar':
                $dados = $this->relatorioContasPagar($empresaId, $dataInicio, $dataFim, $statusFiltro);
                layout('Relatório: Contas a Pagar', 'relatorios/show_contas_pagar.php', [
                    'tipo'         => $tipo,
                    'dados'        => $dados,
                    'dataInicio'   => $dataInicio,
                    'dataFim'      => $dataFim,
                    'statusFiltro' => $statusFiltro,
                ]);
                return;
            case 'contas_receber':
                $dados = $this->relatorioContasReceber($empresaId, $dataInicio, $dataFim, $statusFiltro);
                layout('Relatório: Contas a Receber', 'relatorios/show_contas_receber.php', [
                    'tipo'         => $tipo,
                    'dados'        => $dados,
                    'dataInicio'   => $dataInicio,
                    'dataFim'      => $dataFim,
                    'statusFiltro' => $statusFiltro,
                ]);
                return;
            case 'categoria':
                $dados = $this->relatorioCategoria($empresaId, $dataInicio, $dataFim);
                layout('Relatório: ' . $tipo, 'relatorios/show_categoria.php', [
                    'tipo'       => $tipo,
                    'dados'      => $dados,
                    'dataInicio' => $dataInicio,
                    'dataFim'    => $dataFim,
                ]);
                return;
            case 'categoria':
                $dados = $this->relatorioCategoria($empresaId, $dataInicio, $dataFim);
                break;
            case 'fornecedor':
                $dados = $this->relatorioFornecedor($empresaId, $dataInicio, $dataFim);
                break;
            case 'cliente':
                $dados = $this->relatorioCliente($empresaId, $dataInicio, $dataFim);
                break;
            case 'fluxo_caixa':
                $dados = $this->relatorioFluxoCaixa($empresaId, $dataInicio, $dataFim);
                break;
            case 'atrasadas':
                $dados = $this->relatorioAtrasadas($empresaId);
                layout('Relatório: ' . $tipo, 'relatorios/show_atrasadas.php', [
                    'tipo'       => $tipo,
                    'dados'      => $dados,
                    'dataInicio' => $dataInicio,
                    'dataFim'    => $dataFim,
                ]);
                return;
            case 'extrato_conta':
                $contaId = (int)($_GET['conta_id'] ?? 0);
                if ($contaId <= 0) {
                    Flash::set('erro', 'Selecione uma conta bancária.');
                    redirect('relatorio_extrato_conta_form.php');
                }
                $conta = $this->carregarConta($empresaId, $contaId);
                if (!$conta) {
                    Flash::set('erro', 'Conta bancária não encontrada.');
                    redirect('relatorio_extrato_conta_form.php');
                }
                $dados = $this->relatorioExtratoConta($empresaId, $contaId, $dataInicio, $dataFim);
                layout('Extrato: ' . $conta['descricao'], 'relatorios/extrato_conta.php', [
                    'tipo'       => $tipo,
                    'dados'      => $dados,
                    'dataInicio' => $dataInicio,
                    'dataFim'    => $dataFim,
                    'conta'      => $conta,
                    'contaId'    => $contaId,
                ]);
                return;
            default:
                Flash::set('erro', 'Tipo de relatório inválido.');
                redirect('relatorios.php');
        }

        layout('Relatório: ' . $tipo, 'relatorios/show.php', [
            'tipo'       => $tipo,
            'dados'      => $dados,
            'dataInicio' => $dataInicio,
            'dataFim'    => $dataFim,
        ]);
    }

    /**
     * Formulário de seleção de conta + filtros para o relatório de extrato.
     */
    public function extratoContaForm(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT id, descricao, tipo, banco, agencia, numero_conta, digito
            FROM contas_bancarias
            WHERE empresa_id = ? AND ativo = 1
            ORDER BY descricao
        ');
        $stmt->execute([$empresaId]);
        $contas = $stmt->fetchAll();

        layout('Extrato de Conta Bancária', 'relatorios/extrato_conta_form.php', [
            'contas'     => $contas,
            'contaId'    => (int)($_GET['conta_id'] ?? 0),
            'dataInicio' => $_GET['data_inicio'] ?? date('Y-m-01'),
            'dataFim'    => $_GET['data_fim']    ?? date('Y-m-t'),
        ]);
    }

    /**
     * Carrega uma conta bancária validando que pertence à empresa do usuário.
     * Retorna null se não encontrada.
     */
    private function carregarConta(int $empresaId, int $contaId): ?array
    {
        if ($contaId <= 0) {
            return null;
        }
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM contas_bancarias WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$contaId, $empresaId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Exporta o relatório em CSV ou PDF.
     */
    public function exportar(): void
    {
        Auth::require();
        $tipo = $_GET['tipo'] ?? '';
        $formato = $_GET['formato'] ?? 'csv';
        $empresaId = Auth::user()['empresa_id'];

        $dataInicio = $_GET['data_inicio'] ?? date('Y-m-01', strtotime('-2 months'));
        $dataFim    = $_GET['data_fim']    ?? date('Y-m-t');
        $statusFiltro = $_GET['status'] ?? [];
        if (!is_array($statusFiltro)) {
            $statusFiltro = [$statusFiltro];
        }

        switch ($tipo) {
            case 'periodo':     $dados = $this->relatorioPeriodo($empresaId, $dataInicio, $dataFim); break;
            case 'contas_pagar':   $dados = $this->relatorioContasPagar($empresaId, $dataInicio, $dataFim, $statusFiltro); break;
            case 'contas_receber': $dados = $this->relatorioContasReceber($empresaId, $dataInicio, $dataFim, $statusFiltro); break;
            case 'categoria':   $dados = $this->relatorioCategoria($empresaId, $dataInicio, $dataFim); break;
            case 'fornecedor':  $dados = $this->relatorioFornecedor($empresaId, $dataInicio, $dataFim); break;
            case 'cliente':     $dados = $this->relatorioCliente($empresaId, $dataInicio, $dataFim); break;
            case 'fluxo_caixa': $dados = $this->relatorioFluxoCaixa($empresaId, $dataInicio, $dataFim); break;
            case 'atrasadas':   $dados = $this->relatorioAtrasadas($empresaId); break;
            case 'extrato_conta':
                $contaId = (int)($_GET['conta_id'] ?? 0);
                if ($contaId <= 0) {
                    Flash::set('erro', 'Conta bancária não informada.');
                    redirect('relatorio_extrato_conta_form.php');
                }
                $dados = $this->relatorioExtratoConta($empresaId, $contaId, $dataInicio, $dataFim);
                break;
            default:
                Flash::set('erro', 'Tipo inválido.');
                redirect('relatorios.php');
        }

        if ($formato === 'csv') {
            $this->exportarCsv($tipo, $dados);
        } elseif ($formato === 'pdf') {
            // Para extrato_conta, injeta header com dados da conta no PDF
            if ($tipo === 'extrato_conta') {
                $this->exportarPdfExtratoConta($dados, $dataInicio, $dataFim, (int)$_GET['conta_id']);
            } else {
                $this->exportarPdf($tipo, $dados, $dataInicio, $dataFim);
            }
        } else {
            redirect('relatorios.php');
        }
    }

    // ============================================================
    // RELATÓRIOS (cada um retorna ['headers' => [...], 'rows' => [...], 'titulo' => '...'])
    // ============================================================

    /**
     * Relatório de Contas por Período - Pagar e Receber SEPARADOS, com subtotal por data.
     *
     * Estrutura do retorno:
     *  - headers  : ['Vencimento', 'Descrição', 'Entidade', 'Categoria', 'Tipo', 'Valor', 'Valor Pago/Recebido', 'Status']
     *  - rows     : cada registro (com flag __subtotal__ na 1ª coluna das linhas de subtotal por dia)
     *  - totais   : total GERAL (Pagar + Receber somados)
     *  - totais_separados: { pagar: [...], receber: [...] } - totais de cada lado
     *  - subtotais_por_data: { 'YYYY-MM-DD': { qtd, valor, pago, label } } - usado pela view pra renderizar
     *  - tem_datas: lista de datas distintas (pra ajudar a view a iterar)
     */
    private function relatorioPeriodo(int $empresaId, string $dataInicio, string $dataFim): array
    {
        $db = Database::getConnection();

        $stmtPagar = $db->prepare('
            SELECT cp.data_vencimento, cp.descricao, f.razao_social AS entidade,
                   cat.nome AS categoria, cat.cor AS categoria_cor,
                   cp.valor, cp.valor_pago, cp.status,
                   "pagar" AS tipo
            FROM contas_pagar cp
            JOIN fornecedores f ON f.id = cp.fornecedor_id
            JOIN categorias cat ON cat.id = cp.categoria_id
            WHERE cp.empresa_id = ? AND cp.data_vencimento BETWEEN ? AND ?
            ORDER BY cp.data_vencimento, cp.id
        ');
        $stmtPagar->execute([$empresaId, $dataInicio, $dataFim]);
        $rowsPagar = $stmtPagar->fetchAll();

        $stmtReceber = $db->prepare('
            SELECT cr.data_vencimento, cr.descricao, c.razao_social AS entidade,
                   cat.nome AS categoria, cat.cor AS categoria_cor,
                   cr.valor, cr.valor_recebido AS valor_pago, cr.status,
                   "receber" AS tipo
            FROM contas_receber cr
            JOIN clientes c ON c.id = cr.cliente_id
            JOIN categorias cat ON cat.id = cr.categoria_id
            WHERE cr.empresa_id = ? AND cr.data_vencimento BETWEEN ? AND ?
            ORDER BY cr.data_vencimento, cr.id
        ');
        $stmtReceber->execute([$empresaId, $dataInicio, $dataFim]);
        $rowsReceber = $stmtReceber->fetchAll();

        // Mescla Pagar + Receber ordenado por data
        $rows = array_merge($rowsPagar, $rowsReceber);
        usort($rows, function ($a, $b) {
            return strcmp($a['data_vencimento'], $b['data_vencimento']);
        });

        // Subtotais por data
        $subtotaisPorData = [];
        $totaisPagar = ['qtd' => 0, 'valor' => 0.0, 'pago' => 0.0];
        $totaisReceber = ['qtd' => 0, 'valor' => 0.0, 'pago' => 0.0];

        foreach ($rows as $r) {
            $data = $r['data_vencimento'];
            $valor = (float)$r['valor'];
            $pago  = (float)($r['valor_pago'] ?? 0);
            if (!isset($subtotaisPorData[$data])) {
                $subtotaisPorData[$data] = ['qtd' => 0, 'valor' => 0.0, 'pago' => 0.0];
            }
            $subtotaisPorData[$data]['qtd']++;
            $subtotaisPorData[$data]['valor'] += $valor;
            $subtotaisPorData[$data]['pago'] += $pago;

            if ($r['tipo'] === 'pagar') {
                $totaisPagar['qtd']++;
                $totaisPagar['valor'] += $valor;
                $totaisPagar['pago'] += $pago;
            } else {
                $totaisReceber['qtd']++;
                $totaisReceber['valor'] += $valor;
                $totaisReceber['pago'] += $pago;
            }
        }

        // Totais gerais
        $totalValor = $totaisPagar['valor'] + $totaisReceber['valor'];
        $totalPago  = $totaisPagar['pago']  + $totaisReceber['pago'];
        $totalQtd   = count($rows);

        return [
            'titulo'  => 'Contas por Período',
            'headers' => ['Vencimento', 'Descrição', 'Entidade', 'Categoria', 'Tipo', 'Valor', 'Valor Pago/Recebido', 'Status'],
            'rows'    => array_map(function ($r) {
                return [
                    dataIsoParaBr($r['data_vencimento']),
                    $r['descricao'],
                    $r['entidade'],
                    $r['categoria'],
                    $r['tipo'] === 'pagar' ? 'A Pagar' : 'A Receber',
                    number_format((float)$r['valor'], 2, ',', '.'),
                    number_format((float)($r['valor_pago'] ?? 0), 2, ',', '.'),
                    $r['status'],
                    // Campos extras (ignorados pela view genérica, usados pela view periodo):
                    '__raw__' => $r,
                ];
            }, $rows),
            'subtotais_por_data' => $subtotaisPorData,
            'totais_separados'   => [
                'pagar' => [
                    'qtd'   => $totaisPagar['qtd'],
                    'valor' => $totaisPagar['valor'],
                    'pago'  => $totaisPagar['pago'],
                ],
                'receber' => [
                    'qtd'   => $totaisReceber['qtd'],
                    'valor' => $totaisReceber['valor'],
                    'pago'  => $totaisReceber['pago'],
                ],
            ],
            'totais'  => [
                'label' => 'TOTAL GERAL',
                'cells' => [
                    'TOTAL GERAL',
                    'Σ ' . $totalQtd . ' contas (' . $totaisPagar['qtd'] . ' pagar + ' . $totaisReceber['qtd'] . ' receber)',
                    '', '', '',
                    number_format($totalValor, 2, ',', '.'),
                    number_format($totalPago, 2, ',', '.'),
                    '',
                ],
            ],
        ];
    }

    /**
     * Relatório de Contas a PAGAR com filtros de data e status.
     *
     * Estrutura do retorno:
     *  - headers  : ['Vencimento', 'Descrição', 'Fornecedor', 'Categoria', 'Valor', 'Valor Pago', 'Status', 'Forma Pagamento', 'Nº Documento']
     *  - rows     : cada registro (com __raw__ no fim)
     *  - subtotais_por_data: { 'YYYY-MM-DD': { qtd, valor, pago } }
     *  - totais   : { qtd, valor, pago, pendente }
     *  - status_filtrado: array de status aplicados (vazio = todos)
     *  - totais_por_status: [ status => { qtd, valor, pago } ]
     */
    private function relatorioContasPagar(int $empresaId, string $dataInicio, string $dataFim, array $statusFiltro = []): array
    {
        $db = Database::getConnection();

        $statusValidos = ['pendente', 'aprovada', 'paga', 'cancelada'];
        $statusAplicado = array_values(array_intersect($statusFiltro, $statusValidos));

        $sql = '
            SELECT cp.id, cp.data_emissao, cp.data_vencimento, cp.data_pagamento,
                   cp.descricao, cp.numero_documento, cp.valor, cp.valor_pago,
                   cp.forma_pagamento, cp.status, cp.parcelas, cp.parcela_atual,
                   cp.observacoes,
                   f.razao_social AS entidade, f.nome_fantasia AS entidade_fantasia,
                   cat.nome AS categoria, cat.cor AS categoria_cor
            FROM contas_pagar cp
            JOIN fornecedores f ON f.id = cp.fornecedor_id
            JOIN categorias cat ON cat.id = cp.categoria_id
            WHERE cp.empresa_id = ? AND cp.data_vencimento BETWEEN ? AND ?
        ';
        $params = [$empresaId, $dataInicio, $dataFim];

        if (!empty($statusAplicado)) {
            $placeholders = implode(',', array_fill(0, count($statusAplicado), '?'));
            $sql .= " AND cp.status IN ($placeholders)";
            foreach ($statusAplicado as $s) $params[] = $s;
        }

        $sql .= ' ORDER BY cp.data_vencimento, cp.id';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Subtotais por data
        $subtotaisPorData = [];
        $totais = ['qtd' => 0, 'valor' => 0.0, 'pago' => 0.0, 'pendente' => 0.0];
        $totaisPorStatus = [
            'pendente'  => ['qtd' => 0, 'valor' => 0.0, 'pago' => 0.0],
            'aprovada'  => ['qtd' => 0, 'valor' => 0.0, 'pago' => 0.0],
            'paga'      => ['qtd' => 0, 'valor' => 0.0, 'pago' => 0.0],
            'cancelada' => ['qtd' => 0, 'valor' => 0.0, 'pago' => 0.0],
        ];

        foreach ($rows as $r) {
            $data = $r['data_vencimento'];
            $valor = (float)$r['valor'];
            $pago  = (float)($r['valor_pago'] ?? 0);
            $pendente = $valor - $pago;

            if (!isset($subtotaisPorData[$data])) {
                $subtotaisPorData[$data] = ['qtd' => 0, 'valor' => 0.0, 'pago' => 0.0, 'pendente' => 0.0];
            }
            $subtotaisPorData[$data]['qtd']++;
            $subtotaisPorData[$data]['valor'] += $valor;
            $subtotaisPorData[$data]['pago'] += $pago;
            $subtotaisPorData[$data]['pendente'] += $pendente;

            $totais['qtd']++;
            $totais['valor'] += $valor;
            $totais['pago'] += $pago;
            $totais['pendente'] += $pendente;

            $st = $r['status'];
            if (isset($totaisPorStatus[$st])) {
                $totaisPorStatus[$st]['qtd']++;
                $totaisPorStatus[$st]['valor'] += $valor;
                $totaisPorStatus[$st]['pago'] += $pago;
            }
        }

        return [
            'titulo'  => 'Contas a Pagar',
            'headers' => ['Vencimento', 'Descrição', 'Fornecedor', 'Categoria', 'Valor', 'Valor Pago', 'Status', 'Forma Pgto.', 'Nº Documento'],
            'rows'    => array_map(function ($r) {
                return [
                    dataIsoParaBr($r['data_vencimento']),
                    $r['descricao'],
                    $r['entidade_fantasia'] ?: $r['entidade'],
                    $r['categoria'],
                    number_format((float)$r['valor'], 2, ',', '.'),
                    number_format((float)($r['valor_pago'] ?? 0), 2, ',', '.'),
                    ucfirst($r['status']),
                    $r['forma_pagamento'] ?? '—',
                    $r['numero_documento'] ?? '—',
                    '__raw__' => $r,
                ];
            }, $rows),
            'subtotais_por_data' => $subtotaisPorData,
            'totais'  => $totais,
            'totais_por_status' => $totaisPorStatus,
            'status_filtrado'   => $statusAplicado,
        ];
    }

    /**
     * Relatório de Contas a RECEBER com filtros de data e status.
     *
     * Estrutura do retorno:
     *  - headers  : ['Vencimento', 'Descrição', 'Cliente', 'Categoria', 'Valor', 'Valor Recebido', 'Status', 'Forma Recebimento', 'Nº Documento']
     *  - rows     : cada registro (com __raw__ no fim)
     *  - subtotais_por_data: { 'YYYY-MM-DD': { qtd, valor, recebido } }
     *  - totais   : { qtd, valor, recebido, pendente }
     *  - status_filtrado: array de status aplicados (vazio = todos)
     *  - totais_por_status: [ status => { qtd, valor, recebido } ]
     */
    private function relatorioContasReceber(int $empresaId, string $dataInicio, string $dataFim, array $statusFiltro = []): array
    {
        $db = Database::getConnection();

        $statusValidos = ['pendente', 'aprovada', 'recebida', 'cancelada'];
        $statusAplicado = array_values(array_intersect($statusFiltro, $statusValidos));

        $sql = '
            SELECT cr.id, cr.data_emissao, cr.data_vencimento, cr.data_recebimento,
                   cr.descricao, cr.numero_documento, cr.valor, cr.valor_recebido,
                   cr.forma_recebimento, cr.status, cr.parcelas, cr.parcela_atual,
                   cr.observacoes,
                   c.razao_social AS entidade, c.nome_fantasia AS entidade_fantasia,
                   cat.nome AS categoria, cat.cor AS categoria_cor
            FROM contas_receber cr
            JOIN clientes c ON c.id = cr.cliente_id
            JOIN categorias cat ON cat.id = cr.categoria_id
            WHERE cr.empresa_id = ? AND cr.data_vencimento BETWEEN ? AND ?
        ';
        $params = [$empresaId, $dataInicio, $dataFim];

        if (!empty($statusAplicado)) {
            $placeholders = implode(',', array_fill(0, count($statusAplicado), '?'));
            $sql .= " AND cr.status IN ($placeholders)";
            foreach ($statusAplicado as $s) $params[] = $s;
        }

        $sql .= ' ORDER BY cr.data_vencimento, cr.id';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Subtotais por data
        $subtotaisPorData = [];
        $totais = ['qtd' => 0, 'valor' => 0.0, 'recebido' => 0.0, 'pendente' => 0.0];
        $totaisPorStatus = [
            'pendente'  => ['qtd' => 0, 'valor' => 0.0, 'recebido' => 0.0],
            'aprovada'  => ['qtd' => 0, 'valor' => 0.0, 'recebido' => 0.0],
            'recebida'  => ['qtd' => 0, 'valor' => 0.0, 'recebido' => 0.0],
            'cancelada' => ['qtd' => 0, 'valor' => 0.0, 'recebido' => 0.0],
        ];

        foreach ($rows as $r) {
            $data = $r['data_vencimento'];
            $valor = (float)$r['valor'];
            $recebido = (float)($r['valor_recebido'] ?? 0);
            $pendente = $valor - $recebido;

            if (!isset($subtotaisPorData[$data])) {
                $subtotaisPorData[$data] = ['qtd' => 0, 'valor' => 0.0, 'recebido' => 0.0, 'pendente' => 0.0];
            }
            $subtotaisPorData[$data]['qtd']++;
            $subtotaisPorData[$data]['valor'] += $valor;
            $subtotaisPorData[$data]['recebido'] += $recebido;
            $subtotaisPorData[$data]['pendente'] += $pendente;

            $totais['qtd']++;
            $totais['valor'] += $valor;
            $totais['recebido'] += $recebido;
            $totais['pendente'] += $pendente;

            $st = $r['status'];
            if (isset($totaisPorStatus[$st])) {
                $totaisPorStatus[$st]['qtd']++;
                $totaisPorStatus[$st]['valor'] += $valor;
                $totaisPorStatus[$st]['recebido'] += $recebido;
            }
        }

        return [
            'titulo'  => 'Contas a Receber',
            'headers' => ['Vencimento', 'Descrição', 'Cliente', 'Categoria', 'Valor', 'Valor Recebido', 'Status', 'Forma Recb.', 'Nº Documento'],
            'rows'    => array_map(function ($r) {
                return [
                    dataIsoParaBr($r['data_vencimento']),
                    $r['descricao'],
                    $r['entidade_fantasia'] ?: $r['entidade'],
                    $r['categoria'],
                    number_format((float)$r['valor'], 2, ',', '.'),
                    number_format((float)($r['valor_recebido'] ?? 0), 2, ',', '.'),
                    ucfirst($r['status']),
                    $r['forma_recebimento'] ?? '—',
                    $r['numero_documento'] ?? '—',
                    '__raw__' => $r,
                ];
            }, $rows),
            'subtotais_por_data' => $subtotaisPorData,
            'totais'  => $totais,
            'totais_por_status' => $totaisPorStatus,
            'status_filtrado'   => $statusAplicado,
        ];
    }

    /**
     * Relatório por Categoria - Pagar e Receber SEPARADOS, com agrupamento por categoria
     * e ordem cronológica dentro de cada categoria.
     *
     * Estrutura do retorno:
     *  - headers: ['Vencimento', 'Descrição', 'Entidade', 'Tipo', 'Valor', 'Valor Pago/Recebido', 'Status']
     *  - rows: cada registro (com __raw__ e __categoria_id__ no fim)
     *  - grupos: [categoria_id => ['nome', 'cor', 'qtd', 'valor', 'pago', 'rows_idx' => []]]
     *  - totais_separados: { pagar: [...], receber: [...] }
     *  - totais: total geral (mantido pra compat com show.php e CSV)
     */
    private function relatorioCategoria(int $empresaId, string $dataInicio, string $dataFim): array
    {
        $db = Database::getConnection();

        $stmtPagar = $db->prepare('
            SELECT cp.id, cp.data_vencimento, cp.descricao, f.razao_social AS entidade,
                   cp.valor, cp.valor_pago, cp.status,
                   cat.id AS categoria_id, cat.nome AS categoria, cat.cor AS categoria_cor,
                   "pagar" AS tipo
            FROM contas_pagar cp
            JOIN fornecedores f ON f.id = cp.fornecedor_id
            JOIN categorias cat ON cat.id = cp.categoria_id
            WHERE cp.empresa_id = ? AND cp.data_vencimento BETWEEN ? AND ?
            ORDER BY cat.nome, cp.data_vencimento, cp.id
        ');
        $stmtPagar->execute([$empresaId, $dataInicio, $dataFim]);
        $rowsPagar = $stmtPagar->fetchAll();

        $stmtReceber = $db->prepare('
            SELECT cr.id, cr.data_vencimento, cr.descricao, c.razao_social AS entidade,
                   cr.valor, cr.valor_recebido AS valor_pago, cr.status,
                   cat.id AS categoria_id, cat.nome AS categoria, cat.cor AS categoria_cor,
                   "receber" AS tipo
            FROM contas_receber cr
            JOIN clientes c ON c.id = cr.cliente_id
            JOIN categorias cat ON cat.id = cr.categoria_id
            WHERE cr.empresa_id = ? AND cr.data_vencimento BETWEEN ? AND ?
            ORDER BY cat.nome, cr.data_vencimento, cr.id
        ');
        $stmtReceber->execute([$empresaId, $dataInicio, $dataFim]);
        $rowsReceber = $stmtReceber->fetchAll();

        $rows = array_merge($rowsPagar, $rowsReceber);
        // Ordena por categoria (alfabético) e dentro por data crescente
        usort($rows, function ($a, $b) {
            $c = strcmp($a['categoria'], $b['categoria']);
            if ($c !== 0) return $c;
            $c = strcmp($a['data_vencimento'], $b['data_vencimento']);
            if ($c !== 0) return $c;
            return $a['id'] - $b['id'];
        });

        // Agrupa por categoria
        $grupos = [];
        $totaisPagar = ['qtd' => 0, 'valor' => 0.0, 'pago' => 0.0];
        $totaisReceber = ['qtd' => 0, 'valor' => 0.0, 'pago' => 0.0];

        foreach ($rows as $idx => $r) {
            $catId = (int)$r['categoria_id'];
            $valor = (float)$r['valor'];
            $pago  = (float)($r['valor_pago'] ?? 0);
            if (!isset($grupos[$catId])) {
                $grupos[$catId] = [
                    'id'      => $catId,
                    'nome'    => $r['categoria'],
                    'cor'     => $r['categoria_cor'] ?? '#6c757d',
                    'qtd'     => 0,
                    'valor'   => 0.0,
                    'pago'    => 0.0,
                    'pagar'   => 0.0,
                    'receber' => 0.0,
                    'rows_idx' => [],
                ];
            }
            $grupos[$catId]['qtd']++;
            $grupos[$catId]['valor'] += $valor;
            $grupos[$catId]['pago'] += $pago;
            $grupos[$catId]['rows_idx'][] = $idx;
            if ($r['tipo'] === 'pagar') {
                $grupos[$catId]['pagar'] += $valor;
                $totaisPagar['qtd']++;
                $totaisPagar['valor'] += $valor;
                $totaisPagar['pago'] += $pago;
            } else {
                $grupos[$catId]['receber'] += $valor;
                $totaisReceber['qtd']++;
                $totaisReceber['valor'] += $valor;
                $totaisReceber['pago'] += $pago;
            }
        }

        $totalValor = $totaisPagar['valor'] + $totaisReceber['valor'];
        $totalPago  = $totaisPagar['pago']  + $totaisReceber['pago'];
        $totalQtd   = count($rows);

        return [
            'titulo'  => 'Contas por Categoria',
            'headers' => ['Vencimento', 'Descrição', 'Entidade', 'Tipo', 'Valor', 'Valor Pago/Recebido', 'Status'],
            'rows'    => array_map(function ($r) {
                return [
                    dataIsoParaBr($r['data_vencimento']),
                    $r['descricao'],
                    $r['entidade'],
                    $r['tipo'] === 'pagar' ? 'A Pagar' : 'A Receber',
                    number_format((float)$r['valor'], 2, ',', '.'),
                    number_format((float)($r['valor_pago'] ?? 0), 2, ',', '.'),
                    $r['status'],
                    '__raw__' => $r,
                ];
            }, $rows),
            'grupos' => $grupos,
            'totais_separados' => [
                'pagar' => [
                    'qtd'   => $totaisPagar['qtd'],
                    'valor' => $totaisPagar['valor'],
                    'pago'  => $totaisPagar['pago'],
                ],
                'receber' => [
                    'qtd'   => $totaisReceber['qtd'],
                    'valor' => $totaisReceber['valor'],
                    'pago'  => $totaisReceber['pago'],
                ],
            ],
            'totais' => [
                'label' => 'TOTAL GERAL',
                'cells' => [
                    'TOTAL GERAL',
                    'Σ ' . $totalQtd . ' contas (' . $totaisPagar['qtd'] . ' pagar + ' . $totaisReceber['qtd'] . ' receber) em ' . count($grupos) . ' categorias',
                    '', '', '',
                    number_format($totalValor, 2, ',', '.'),
                    number_format($totalPago, 2, ',', '.'),
                    '',
                ],
            ],
        ];
    }

    private function relatorioFornecedor(int $empresaId, string $dataInicio, string $dataFim): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT f.razao_social AS entidade, f.cnpj,
                   COUNT(*) AS qtd,
                   SUM(cp.valor) AS total_pendente,
                   SUM(CASE WHEN cp.status="paga" THEN cp.valor_pago ELSE 0 END) AS total_pago
            FROM contas_pagar cp
            JOIN fornecedores f ON f.id = cp.fornecedor_id
            WHERE cp.empresa_id = ? AND cp.data_vencimento BETWEEN ? AND ?
            GROUP BY f.id
            ORDER BY total_pago DESC
        ');
        $stmt->execute([$empresaId, $dataInicio, $dataFim]);
        $rows = $stmt->fetchAll();

        // Totais
        $totalQtd      = 0;
        $totalPendente = 0.0;
        $totalPago     = 0.0;
        foreach ($rows as $r) {
            $totalQtd      += (int)$r['qtd'];
            $totalPendente += (float)$r['total_pendente'];
            $totalPago     += (float)$r['total_pago'];
        }

        return [
            'titulo'  => 'Por Fornecedor',
            'headers' => ['Fornecedor', 'CNPJ', 'Qtd', 'Pendente', 'Pago'],
            'rows'    => array_map(function ($r) {
                return [
                    $r['entidade'],
                    $r['cnpj'],
                    $r['qtd'],
                    number_format((float)$r['total_pendente'], 2, ',', '.'),
                    number_format((float)$r['total_pago'], 2, ',', '.'),
                ];
            }, $rows),
            'totais'  => [
                'label' => 'TOTAL',
                'cells' => [
                    'TOTAL',
                    'Σ ' . count($rows) . ' fornecedores',
                    (string)$totalQtd,
                    number_format($totalPendente, 2, ',', '.'),
                    number_format($totalPago, 2, ',', '.'),
                ],
            ],
        ];
    }

    private function relatorioCliente(int $empresaId, string $dataInicio, string $dataFim): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT c.razao_social AS entidade, c.cpf_cnpj,
                   COUNT(*) AS qtd,
                   SUM(cr.valor) AS total_pendente,
                   SUM(CASE WHEN cr.status="recebida" THEN cr.valor_recebido ELSE 0 END) AS total_recebido
            FROM contas_receber cr
            JOIN clientes c ON c.id = cr.cliente_id
            WHERE cr.empresa_id = ? AND cr.data_vencimento BETWEEN ? AND ?
            GROUP BY c.id
            ORDER BY total_recebido DESC
        ');
        $stmt->execute([$empresaId, $dataInicio, $dataFim]);
        $rows = $stmt->fetchAll();

        // Totais
        $totalQtd       = 0;
        $totalPendente  = 0.0;
        $totalRecebido  = 0.0;
        foreach ($rows as $r) {
            $totalQtd      += (int)$r['qtd'];
            $totalPendente += (float)$r['total_pendente'];
            $totalRecebido += (float)$r['total_recebido'];
        }

        return [
            'titulo'  => 'Por Cliente',
            'headers' => ['Cliente', 'CPF/CNPJ', 'Qtd', 'Pendente', 'Recebido'],
            'rows'    => array_map(function ($r) {
                return [
                    $r['entidade'],
                    $r['cpf_cnpj'],
                    $r['qtd'],
                    number_format((float)$r['total_pendente'], 2, ',', '.'),
                    number_format((float)$r['total_recebido'], 2, ',', '.'),
                ];
            }, $rows),
            'totais'  => [
                'label' => 'TOTAL',
                'cells' => [
                    'TOTAL',
                    'Σ ' . count($rows) . ' clientes',
                    (string)$totalQtd,
                    number_format($totalPendente, 2, ',', '.'),
                    number_format($totalRecebido, 2, ',', '.'),
                ],
            ],
        ];
    }

    private function relatorioFluxoCaixa(int $empresaId, string $dataInicio, string $dataFim): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT data_movimento,
                   SUM(CASE WHEN tipo="entrada" THEN valor ELSE 0 END) AS entradas,
                   SUM(CASE WHEN tipo="saida" THEN valor ELSE 0 END) AS saidas,
                   SUM(CASE WHEN tipo="entrada" THEN valor ELSE -valor END) AS saldo_dia
            FROM movimentacoes_bancarias
            WHERE empresa_id = ? AND data_movimento BETWEEN ? AND ?
            GROUP BY data_movimento
            ORDER BY data_movimento
        ');
        $stmt->execute([$empresaId, $dataInicio, $dataFim]);
        $rows = $stmt->fetchAll();

        $saldoAcumulado = 0;
        $totalEntradas  = 0.0;
        $totalSaidas    = 0.0;
        foreach ($rows as &$r) {
            $saldoAcumulado += (float)$r['saldo_dia'];
            $r['saldo_acumulado'] = $saldoAcumulado;
            $totalEntradas += (float)$r['entradas'];
            $totalSaidas   += (float)$r['saidas'];
        }
        unset($r);
        $saldoPeriodo = $totalEntradas - $totalSaidas;

        return [
            'titulo'  => 'Fluxo de Caixa',
            'headers' => ['Data', 'Entradas', 'Saídas', 'Saldo do Dia', 'Saldo Acumulado'],
            'rows'    => array_map(function ($r) {
                return [
                    dataIsoParaBr($r['data_movimento']),
                    number_format((float)$r['entradas'], 2, ',', '.'),
                    number_format((float)$r['saidas'], 2, ',', '.'),
                    number_format((float)$r['saldo_dia'], 2, ',', '.'),
                    number_format((float)$r['saldo_acumulado'], 2, ',', '.'),
                ];
            }, $rows),
            'totais'  => [
                'label' => 'TOTAL DO PERÍODO',
                'cells' => [
                    'TOTAL',
                    'Σ ' . count($rows) . ' dias',
                    number_format($totalEntradas, 2, ',', '.'),
                    number_format($totalSaidas, 2, ',', '.'),
                    number_format($saldoPeriodo, 2, ',', '.'),
                    number_format($saldoAcumulado, 2, ',', '.'),
                ],
            ],
        ];
    }

    /**
     * Relatório de Contas Atrasadas - Pagar e Receber SEPARADOS.
     *
     * Estrutura do retorno:
     *  - titulo: 'Contas Atrasadas'
     *  - headers: ['Vencimento', 'Descrição', 'Entidade', 'Categoria', 'Valor', 'Dias Atraso']
     *  - rows_pagar: lista de contas a pagar atrasadas
     *  - rows_receber: lista de contas a receber atrasadas
     *  - totais_separados: { pagar: {qtd, valor, max_atraso}, receber: {qtd, valor, max_atraso} }
     *  - totais: total geral (compat com show.php e CSV - uma linha só)
     */
    private function relatorioAtrasadas(int $empresaId): array
    {
        $db = Database::getConnection();
        $hoje = date('Y-m-d');

        // ---- CONTAS A PAGAR ATRASADAS ----
        $stmtPagar = $db->prepare('
            SELECT cp.data_vencimento, cp.descricao,
                   f.razao_social AS entidade, cat.nome AS categoria, cat.cor AS categoria_cor,
                   cp.valor, DATEDIFF(?, cp.data_vencimento) AS dias_atraso
            FROM contas_pagar cp
            JOIN fornecedores f ON f.id = cp.fornecedor_id
            JOIN categorias cat ON cat.id = cp.categoria_id
            WHERE cp.empresa_id = ? AND cp.status IN ("pendente","aprovada") AND cp.data_vencimento < ?
            ORDER BY dias_atraso DESC
        ');
        $stmtPagar->execute([$hoje, $empresaId, $hoje]);
        $rowsPagar = $stmtPagar->fetchAll();

        // ---- CONTAS A RECEBER ATRASADAS ----
        $stmtReceber = $db->prepare('
            SELECT cr.data_vencimento, cr.descricao,
                   c.razao_social AS entidade, cat.nome AS categoria, cat.cor AS categoria_cor,
                   cr.valor, DATEDIFF(?, cr.data_vencimento) AS dias_atraso
            FROM contas_receber cr
            JOIN clientes c ON c.id = cr.cliente_id
            JOIN categorias cat ON cat.id = cr.categoria_id
            WHERE cr.empresa_id = ? AND cr.status IN ("pendente","aprovada") AND cr.data_vencimento < ?
            ORDER BY dias_atraso DESC
        ');
        $stmtReceber->execute([$hoje, $empresaId, $hoje]);
        $rowsReceber = $stmtReceber->fetchAll();

        // ---- TOTAIS SEPARADOS ----
        $totalPagar = 0.0;
        $maxAtrasoPagar = 0;
        foreach ($rowsPagar as $r) {
            $totalPagar += (float)$r['valor'];
            if ((int)$r['dias_atraso'] > $maxAtrasoPagar) $maxAtrasoPagar = (int)$r['dias_atraso'];
        }

        $totalReceber = 0.0;
        $maxAtrasoReceber = 0;
        foreach ($rowsReceber as $r) {
            $totalReceber += (float)$r['valor'];
            if ((int)$r['dias_atraso'] > $maxAtrasoReceber) $maxAtrasoReceber = (int)$r['dias_atraso'];
        }

        $totalGeral  = $totalPagar + $totalReceber;
        $totalQtd    = count($rowsPagar) + count($rowsReceber);
        $maxAtrasoGeral = max($maxAtrasoPagar, $maxAtrasoReceber);

        // ---- HELPERS DE FORMATAÇÃO ----
        $formatRow = function ($r) {
            return [
                dataIsoParaBr($r['data_vencimento']),
                $r['descricao'],
                $r['entidade'],
                $r['categoria'],
                number_format((float)$r['valor'], 2, ',', '.'),
                $r['dias_atraso'],
                '__raw__' => $r,
            ];
        };

        return [
            'titulo'  => 'Contas Atrasadas',
            'headers' => ['Vencimento', 'Descrição', 'Entidade', 'Categoria', 'Valor', 'Dias Atraso'],
            'rows_pagar'   => array_map($formatRow, $rowsPagar),
            'rows_receber' => array_map($formatRow, $rowsReceber),
            'totais_separados' => [
                'pagar'   => [
                    'qtd'        => count($rowsPagar),
                    'valor'      => $totalPagar,
                    'max_atraso' => $maxAtrasoPagar,
                ],
                'receber' => [
                    'qtd'        => count($rowsReceber),
                    'valor'      => $totalReceber,
                    'max_atraso' => $maxAtrasoReceber,
                ],
            ],
            // Mantido pra compat com CSV genérico (lista flat com Tipo no início)
            'rows'    => array_merge(
                array_map(function ($r) use ($formatRow) {
                    $f = $formatRow($r);
                    return array_merge(['Pagar'], $f);
                }, $rowsPagar),
                array_map(function ($r) use ($formatRow) {
                    $f = $formatRow($r);
                    return array_merge(['Receber'], $f);
                }, $rowsReceber)
            ),
            'totais'  => [
                'label' => 'TOTAL ATRASADO',
                'cells' => [
                    'TOTAL',
                    '', // Vencimento
                    'Σ ' . $totalQtd . ' contas (' . count($rowsPagar) . ' pagar + ' . count($rowsReceber) . ' receber)',
                    '', // Entidade
                    '', // Categoria
                    number_format($totalGeral, 2, ',', '.'),
                    'máx: ' . $maxAtrasoGeral,
                ],
            ],
        ];
    }

    // ============================================================
    // RELATÓRIO: Extrato de Conta Bancária (específico por conta)
    // ============================================================

    private function relatorioExtratoConta(int $empresaId, int $contaId, string $dataInicio, string $dataFim): array
    {
        $db = Database::getConnection();

        // Carrega dados da conta (saldo inicial + titular + banco)
        $stmtC = $db->prepare('
            SELECT id, descricao, tipo, banco, agencia, numero_conta, digito,
                   titular, saldo_inicial, data_saldo_inicial
            FROM contas_bancarias
            WHERE id = ? AND empresa_id = ?
        ');
        $stmtC->execute([$contaId, $empresaId]);
        $conta = $stmtC->fetch();

        if (!$conta) {
            return [
                'titulo'  => 'Extrato de Conta Bancária',
                'headers' => [],
                'rows'    => [],
                'conta'   => null,
                'resumo'  => ['saldo_inicial' => 0, 'saldo_periodo' => 0, 'saldo_atual' => 0,
                              'total_entradas' => 0, 'total_saidas' => 0],
            ];
        }

        // Movimentações do período
        $stmt = $db->prepare('
            SELECT m.data_movimento, m.tipo, m.origem, m.valor, m.descricao,
                   u.nome AS usuario_nome
            FROM movimentacoes_bancarias m
            JOIN usuarios u ON u.id = m.usuario_id
            WHERE m.empresa_id = ?
              AND m.conta_bancaria_id = ?
              AND m.data_movimento BETWEEN ? AND ?
            ORDER BY m.data_movimento ASC, m.id ASC
        ');
        $stmt->execute([$empresaId, $contaId, $dataInicio, $dataFim]);
        $rows = $stmt->fetchAll();

        // Calcula saldo anterior ao período e saldo acumulado linha a linha
        $saldoAnterior = $this->calcularSaldoAteData($empresaId, $contaId, $dataInicio, (float)$conta['saldo_inicial'], (string)$conta['data_saldo_inicial']);
        $saldoAcumulado = $saldoAnterior;

        $totalEntradas = 0.0;
        $totalSaidas   = 0.0;
        $rowsFormatados = [];

        // Linha inicial: saldo anterior
        $rowsFormatados[] = [
            dataIsoParaBr($dataInicio),
            '(saldo anterior)',
            '',
            '',
            '',
            '',
            number_format($saldoAnterior, 2, ',', '.'),
        ];

        foreach ($rows as $r) {
            if ($r['tipo'] === 'entrada') {
                $saldoAcumulado += (float)$r['valor'];
                $totalEntradas += (float)$r['valor'];
                $valorStr = '+ R$ ' . number_format((float)$r['valor'], 2, ',', '.');
                $tipoBadge = '↗ Entrada';
            } else {
                $saldoAcumulado -= (float)$r['valor'];
                $totalSaidas += (float)$r['valor'];
                $valorStr = '- R$ ' . number_format((float)$r['valor'], 2, ',', '.');
                $tipoBadge = '↘ Saída';
            }

            $rowsFormatados[] = [
                dataIsoParaBr($r['data_movimento']),
                $r['descricao'],
                $tipoBadge,
                $r['origem'],
                $r['usuario_nome'],
                $valorStr,
                number_format($saldoAcumulado, 2, ',', '.'),
            ];
        }

        // Saldo atual real (calculado direto, pode divergir do saldo_final_calculado
        // se houver movimentações manuais fora do período filtrado — mas o usuário
        // quer o saldo "vivo" da conta, então mostramos o saldo_periodo e o atual).
        $saldoAtual = ContasBancariasController::calcularSaldo($contaId);
        $saldoPeriodo = $saldoAcumulado;

        return [
            'titulo'  => 'Extrato: ' . $conta['descricao'],
            'headers' => ['Data', 'Descrição', 'Tipo', 'Origem', 'Usuário', 'Valor', 'Saldo'],
            'rows'    => $rowsFormatados,
            'conta'   => $conta,
            'resumo'  => [
                'saldo_inicial'  => (float)$conta['saldo_inicial'],
                'saldo_anterior' => $saldoAnterior,
                'saldo_periodo'  => $saldoPeriodo,
                'saldo_atual'    => $saldoAtual,
                'total_entradas' => $totalEntradas,
                'total_saidas'   => $totalSaidas,
            ],
        ];
    }

    /**
     * Calcula o saldo da conta imediatamente ANTES da data de início do período.
     * saldo_inicial + SUM(entradas) - SUM(saidas) para movimentações com
     * data entre data_saldo_inicial e (dataInicio - 1 dia).
     */
    private function calcularSaldoAteData(int $empresaId, int $contaId, string $dataInicio, float $saldoInicial, string $dataSaldoInicial): float
    {
        $db = Database::getConnection();

        // Movs a partir de data_saldo_inicial e até (dataInicio - 1 dia)
        $ateData = date('Y-m-d', strtotime($dataInicio . ' -1 day'));

        $stmt = $db->prepare('
            SELECT
                COALESCE(SUM(CASE WHEN tipo="entrada" THEN valor ELSE 0 END), 0) AS entradas,
                COALESCE(SUM(CASE WHEN tipo="saida"   THEN valor ELSE 0 END), 0) AS saidas
            FROM movimentacoes_bancarias
            WHERE empresa_id = ?
              AND conta_bancaria_id = ?
              AND data_movimento >= ?
              AND data_movimento <= ?
        ');
        $stmt->execute([$empresaId, $contaId, $dataSaldoInicial, $ateData]);

        $r = $stmt->fetch();
        if (!$r) {
            return $saldoInicial;
        }
        return $saldoInicial + (float)$r['entradas'] - (float)$r['saidas'];
    }

    /**
     * Exportação PDF dedicada para extrato de conta — inclui header com dados
     * da conta (banco, agência, conta, titular) e cards resumo (saldo anterior,
     * entradas, saídas, saldo final).
     */
    private function exportarPdfExtratoConta(array $dados, string $dataInicio, string $dataFim, int $contaId): void
    {
        $empresaId = Auth::user()['empresa_id'];
        $conta = $this->carregarConta($empresaId, $contaId);
        $resumo = $dados['resumo'] ?? [];

        $empresa = Auth::user();
        $empresaNome = '';
        foreach (($_SESSION['empresas'] ?? []) as $emp) {
            if ((int)$emp['empresa_id'] === (int)$empresa['empresa_id']) {
                $empresaNome = $emp['nome_fantasia'] ?: $emp['razao_social'];
                break;
            }
        }

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            body { font-family: Arial, sans-serif; font-size: 11px; }
            h1 { font-size: 18px; margin-bottom: 5px; }
            .subtitulo { color: #666; margin-bottom: 10px; font-size: 10px; }
            .info-conta { background: #f5f5f5; padding: 8px; margin-bottom: 12px; border: 1px solid #ddd; font-size: 11px; }
            .info-conta strong { display: inline-block; min-width: 80px; }
            .resumo { margin: 10px 0 16px 0; }
            .resumo table { width: 100%; border-collapse: collapse; }
            .resumo td { padding: 6px 8px; border: 1px solid #ccc; }
            .resumo .label { background: #f5f5f5; font-weight: bold; width: 25%; }
            .resumo .positivo { color: #16a34a; }
            .resumo .negativo { color: #dc2626; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #ccc; padding: 5px; text-align: left; }
            th { background: #f5f5f5; font-weight: bold; }
            tr:nth-child(even) { background: #fafafa; }
            .text-right { text-align: right; }
        </style></head><body>';

        $html .= '<h1>' . htmlspecialchars($dados['titulo']) . '</h1>';
        $html .= '<div class="subtitulo">' . htmlspecialchars($empresaNome);
        $html .= ' | Período: ' . dataIsoParaBr($dataInicio) . ' a ' . dataIsoParaBr($dataFim);
        $html .= ' | Gerado em ' . date('d/m/Y H:i') . '</div>';

        if ($conta) {
            $html .= '<div class="info-conta">';
            $html .= '<strong>Conta:</strong> ' . htmlspecialchars($conta['descricao']) . ' &nbsp;&nbsp;';
            if (!empty($conta['banco'])) {
                $html .= '<strong>Banco:</strong> ' . htmlspecialchars($conta['banco']) . ' &nbsp;&nbsp;';
            }
            if (!empty($conta['agencia'])) {
                $html .= '<strong>Agência:</strong> ' . htmlspecialchars($conta['agencia']) . ' &nbsp;&nbsp;';
            }
            if (!empty($conta['numero_conta'])) {
                $num = $conta['numero_conta'] . (!empty($conta['digito']) ? '-' . $conta['digito'] : '');
                $html .= '<strong>Conta:</strong> ' . htmlspecialchars($num) . ' &nbsp;&nbsp;';
            }
            if (!empty($conta['titular'])) {
                $html .= '<br><strong>Titular:</strong> ' . htmlspecialchars($conta['titular']);
            }
            $html .= '</div>';
        }

        $html .= '<div class="resumo"><table>';
        $html .= '<tr>';
        $html .= '<td class="label">Saldo Anterior</td>';
        $html .= '<td>R$ ' . number_format((float)($resumo['saldo_anterior'] ?? 0), 2, ',', '.') . '</td>';
        $html .= '<td class="label">Entradas</td>';
        $html .= '<td class="positivo">+ R$ ' . number_format((float)($resumo['total_entradas'] ?? 0), 2, ',', '.') . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td class="label">Saídas</td>';
        $html .= '<td class="negativo">- R$ ' . number_format((float)($resumo['total_saidas'] ?? 0), 2, ',', '.') . '</td>';
        $html .= '<td class="label">Saldo em ' . dataIsoParaBr($dataFim) . '</td>';
        $saldoFim = (float)($resumo['saldo_periodo'] ?? 0);
        $cls = $saldoFim < 0 ? 'negativo' : 'positivo';
        $html .= '<td class="' . $cls . '">R$ ' . number_format($saldoFim, 2, ',', '.') . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td class="label">Saldo Atual (vivo)</td>';
        $saldoAtual = (float)($resumo['saldo_atual'] ?? 0);
        $cls = $saldoAtual < 0 ? 'negativo' : 'positivo';
        $html .= '<td class="' . $cls . '">R$ ' . number_format($saldoAtual, 2, ',', '.') . '</td>';
        $html .= '<td colspan="2"></td>';
        $html .= '</tr>';
        $html .= '</table></div>';

        $html .= '<table><thead><tr>';
        foreach ($dados['headers'] as $h) {
            $html .= '<th>' . htmlspecialchars($h) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        if (empty($dados['rows'])) {
            $html .= '<tr><td colspan="' . count($dados['headers']) . '" style="text-align:center; color:#999;">Nenhuma movimentação no período.</td></tr>';
        } else {
            foreach ($dados['rows'] as $row) {
                $html .= '<tr>';
                foreach ($row as $cell) {
                    $html .= '<td>' . htmlspecialchars((string)$cell) . '</td>';
                }
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table></body></html>';

        $tmpHtml = tempnam(sys_get_temp_dir(), 'rel_') . '.html';
        file_put_contents($tmpHtml, $html);

        $tmpPdf = tempnam(sys_get_temp_dir(), 'rel_') . '.pdf';
        $cmd = sprintf(
            'wkhtmltopdf --quiet --orientation Landscape --margin-top 10mm --margin-bottom 10mm %s %s 2>&1',
            escapeshellarg($tmpHtml),
            escapeshellarg($tmpPdf)
        );
        exec($cmd, $output, $ret);

        unlink($tmpHtml);

        if ($ret !== 0 || !file_exists($tmpPdf)) {
            Flash::set('erro', 'Erro ao gerar PDF. Verifique se wkhtmltopdf está instalado.');
            redirect('relatorios.php');
        }

        $filename = "relatorio_extrato_" . date('Ymd_His') . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmpPdf));
        readfile($tmpPdf);
        unlink($tmpPdf);
        exit;
    }

    // ============================================================
    // EXPORTAÇÕES
    // ============================================================

    private function exportarCsv(string $tipo, array $dados): void
    {
        $slug = preg_replace('/[^a-z0-9]/', '_', strtolower($tipo));

        // Relatório de Atrasadas tem estrutura especial: headers sem "Tipo" mas rows com "Tipo" (7 colunas)
        // Aqui a gente re-monta o CSV com a coluna "Tipo" no início
        if ($tipo === 'atrasadas') {
            $csvHeaders = ['Tipo', 'Vencimento', 'Descrição', 'Entidade', 'Categoria', 'Valor', 'Dias Atraso'];
            $csvRows = [];
            foreach ($dados['rows_pagar'] ?? [] as $r) {
                $rowShow = $r;
                unset($rowShow['__raw__']);
                $csvRows[] = array_merge(['Pagar'], array_values($rowShow));
            }
            foreach ($dados['rows_receber'] ?? [] as $r) {
                $rowShow = $r;
                unset($rowShow['__raw__']);
                $csvRows[] = array_merge(['Receber'], array_values($rowShow));
            }
            // Linha em branco + total
            $csvRows[] = array_fill(0, count($csvHeaders), '');
            $sep = $dados['totais_separados'] ?? ['pagar' => ['qtd'=>0,'valor'=>0], 'receber' => ['qtd'=>0,'valor'=>0]];
            $linhaTotal = array_fill(0, count($csvHeaders), '');
            $linhaTotal[0] = 'TOTAL GERAL (Pagar + Receber)';
            $linhaTotal[5] = 'R$ ' . number_format(($sep['pagar']['valor'] ?? 0) + ($sep['receber']['valor'] ?? 0), 2, ',', '.');
            $linhaTotal[6] = (($sep['pagar']['qtd'] ?? 0) + ($sep['receber']['qtd'] ?? 0)) . ' contas';
            $csvRows[] = $linhaTotal;
            // Subtotais por tipo
            $linhaPg = array_fill(0, count($csvHeaders), '');
            $linhaPg[0] = 'Subtotal A Pagar';
            $linhaPg[5] = 'R$ ' . number_format($sep['pagar']['valor'] ?? 0, 2, ',', '.');
            $linhaPg[6] = ($sep['pagar']['qtd'] ?? 0) . ' contas';
            $csvRows[] = $linhaPg;
            $linhaRc = array_fill(0, count($csvHeaders), '');
            $linhaRc[0] = 'Subtotal A Receber';
            $linhaRc[5] = 'R$ ' . number_format($sep['receber']['valor'] ?? 0, 2, ',', '.');
            $linhaRc[6] = ($sep['receber']['qtd'] ?? 0) . ' contas';
            $csvRows[] = $linhaRc;
            CsvExporter::download("relatorio_$slug", $csvHeaders, $csvRows);
            return;
        }

        $rows = $dados['rows'];
        if (!empty($dados['totais'])) {
            $rows[] = array_fill(0, count($dados['headers']), '');
            $cells = $dados['totais']['cells'];
            $label = $dados['totais']['label'];
            array_shift($cells);
            $subLabel = '';
            $values = [];
            $firstValueIdx = -1;
            foreach ($cells as $i => $c) {
                $cv = (string)$c;
                if ($cv === '') continue;
                if ($firstValueIdx === -1) {
                    if (is_numeric(str_replace([',','.',' '], '', $cv))) {
                        $firstValueIdx = $i;
                        $values[] = $cv;
                    } else {
                        $subLabel = $cv;
                    }
                } else {
                    $values[] = $cv;
                }
            }
            $colspan = count($dados['headers']) - count($values);
            $colspan = max(1, $colspan);
            $linha = array_fill(0, count($dados['headers']), '');
            $linha[0] = $label . ($subLabel ? ' ' . $subLabel : '');
            for ($i = 0; $i < count($values) && ($colspan + $i) < count($linha); $i++) {
                $linha[$colspan + $i] = $values[$i];
            }
            $rows[] = $linha;
        }
        CsvExporter::download("relatorio_$slug", $dados['headers'], $rows);
    }

    private function exportarPdf(string $tipo, array $dados, string $dataInicio, string $dataFim): void
    {
        // Gera HTML temporário e converte via wkhtmltopdf
        $html = $this->gerarHtmlRelatorio($tipo, $dados, $dataInicio, $dataFim);
        $tmpHtml = tempnam(sys_get_temp_dir(), 'rel_') . '.html';
        file_put_contents($tmpHtml, $html);

        $tmpPdf = tempnam(sys_get_temp_dir(), 'rel_') . '.pdf';
        $cmd = sprintf(
            'wkhtmltopdf --quiet --orientation Landscape --margin-top 10mm --margin-bottom 10mm %s %s 2>&1',
            escapeshellarg($tmpHtml),
            escapeshellarg($tmpPdf)
        );
        exec($cmd, $output, $ret);

        unlink($tmpHtml);

        if ($ret !== 0 || !file_exists($tmpPdf)) {
            Flash::set('erro', 'Erro ao gerar PDF. Verifique se wkhtmltopdf está instalado.');
            redirect('relatorios.php');
        }

        $slug = preg_replace('/[^a-z0-9]/', '_', strtolower($tipo));
        $filename = "relatorio_{$slug}_" . date('Ymd_His') . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmpPdf));
        readfile($tmpPdf);
        unlink($tmpPdf);
        exit;
    }

    private function gerarHtmlRelatorio(string $tipo, array $dados, string $dataInicio, string $dataFim): string
    {
        // Relatórios com view customizada (agrupados):
        if ($tipo === 'periodo') {
            return $this->gerarHtmlRelatorioPeriodo($dados, $dataInicio, $dataFim);
        }
        if ($tipo === 'contas_pagar') {
            return $this->gerarHtmlRelatorioContasPagar($dados, $dataInicio, $dataFim);
        }
        if ($tipo === 'contas_receber') {
            return $this->gerarHtmlRelatorioContasReceber($dados, $dataInicio, $dataFim);
        }
        if ($tipo === 'categoria') {
            return $this->gerarHtmlRelatorioCategoria($dados, $dataInicio, $dataFim);
        }
        if ($tipo === 'atrasadas') {
            return $this->gerarHtmlRelatorioAtrasadas($dados, $dataInicio, $dataFim);
        }

        $empresa = Auth::user();
        $empresaNome = '';
        foreach (($_SESSION['empresas'] ?? []) as $emp) {
            if ((int)$emp['empresa_id'] === (int)$empresa['empresa_id']) {
                $empresaNome = $emp['nome_fantasia'] ?: $emp['razao_social'];
                break;
            }
        }

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            body { font-family: Arial, sans-serif; font-size: 11px; }
            h1 { font-size: 18px; margin-bottom: 5px; }
            .subtitulo { color: #666; margin-bottom: 20px; font-size: 10px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
            th { background: #f5f5f5; font-weight: bold; }
            tr:nth-child(even) { background: #fafafa; }
            .total-row th { background: #2563eb; color: #fff; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; }
        </style></head><body>';

        $html .= '<h1>' . htmlspecialchars($dados['titulo']) . '</h1>';
        $html .= '<div class="subtitulo">' . htmlspecialchars($empresaNome);
        $html .= ' | Período: ' . dataIsoParaBr($dataInicio) . ' a ' . dataIsoParaBr($dataFim);
        $html .= ' | Gerado em ' . date('d/m/Y H:i') . '</div>';

        $html .= '<table><thead><tr>';
        foreach ($dados['headers'] as $h) {
            $html .= '<th>' . htmlspecialchars($h) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        if (empty($dados['rows'])) {
            $html .= '<tr><td colspan="' . count($dados['headers']) . '" style="text-align:center; color:#999;">Nenhum registro encontrado.</td></tr>';
        } else {
            foreach ($dados['rows'] as $row) {
                $html .= '<tr>';
                foreach ($row as $cell) {
                    $html .= '<td>' . htmlspecialchars((string)$cell) . '</td>';
                }
                $html .= '</tr>';
            }
        }

        $html .= '</tbody>';

        if (!empty($dados['totais'])) {
            $cells = $dados['totais']['cells'];
            $label = $dados['totais']['label'];
            array_shift($cells);
            $subLabel = '';
            $values = [];
            $firstValueIdx = -1;
            foreach ($cells as $i => $c) {
                $cv = (string)$c;
                if ($cv === '') continue;
                if ($firstValueIdx === -1) {
                    if (is_numeric(str_replace([',','.',' '], '', $cv))) {
                        $firstValueIdx = $i;
                        $values[] = $cv;
                    } else {
                        $subLabel = $cv;
                    }
                } else {
                    $values[] = $cv;
                }
            }
            $colspan = count($dados['headers']) - count($values);
            $colspan = max(1, $colspan);
            
            $html .= '<tfoot><tr class="total-row">';
            $html .= '<th colspan="' . $colspan . '" style="text-align:left; padding-left:8px;">';
            $html .= htmlspecialchars($label);
            if ($subLabel !== '') {
                $html .= ' <span style="opacity:.75; font-weight:400; font-size:9px;">' . htmlspecialchars($subLabel) . '</span>';
            }
            $html .= '</th>';
            foreach ($values as $v) {
                $html .= '<th style="text-align:right;">' . htmlspecialchars($v) . '</th>';
            }
            $html .= '</tr></tfoot>';
        }

        $html .= '</table></body></html>';

        return $html;
    }

    /**
     * Gera HTML específico do relatório de Período (PDF-friendly):
     *  - 3 cards de resumo no topo (Pagar / Receber / Saldo)
     *  - Tabela agrupada por data com linha de cabeçalho da data
     *  - Linha de subtotal por dia
     *  - Total geral no rodapé
     */
    private function gerarHtmlRelatorioPeriodo(array $dados, string $dataInicio, string $dataFim): string
    {
        $empresa = Auth::user();
        $empresaNome = '';
        foreach (($_SESSION['empresas'] ?? []) as $emp) {
            if ((int)$emp['empresa_id'] === (int)$empresa['empresa_id']) {
                $empresaNome = $emp['nome_fantasia'] ?: $emp['razao_social'];
                break;
            }
        }

        $headers = $dados['headers'];
        $rows = $dados['rows'];
        $subtotaisPorData = $dados['subtotais_por_data'] ?? [];
        $sep = $dados['totais_separados'] ?? ['pagar' => ['qtd'=>0,'valor'=>0,'pago'=>0], 'receber' => ['qtd'=>0,'valor'=>0,'pago'=>0]];

        // Agrupa rows por data
        $rowsPorData = [];
        foreach ($rows as $row) {
            $raw = $row['__raw__'] ?? null;
            if (!$raw) continue;
            $dataIso = $raw['data_vencimento'];
            if (!isset($rowsPorData[$dataIso])) $rowsPorData[$dataIso] = [];
            $rowsPorData[$dataIso][] = $row;
        }
        ksort($rowsPorData);

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            body { font-family: Arial, sans-serif; font-size: 11px; }
            h1 { font-size: 18px; margin-bottom: 5px; }
            .subtitulo { color: #666; margin-bottom: 14px; font-size: 10px; }
            .cards { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
            .cards td { width: 33.33%; padding: 10px 12px; border: 1px solid #ddd; vertical-align: top; }
            .card-pagar   { border-left: 4px solid #dc2626 !important; background: #fef2f2; }
            .card-receber { border-left: 4px solid #16a34a !important; background: #f0fdf4; }
            .card-saldo   { border-left: 4px solid #2563eb !important; background: #eff6ff; }
            .card-label { font-size: 9px; text-transform: uppercase; font-weight: bold; }
            .card-pagar   .card-label { color: #991b1b; }
            .card-receber .card-label { color: #166534; }
            .card-saldo   .card-label { color: #1e40af; }
            .card-valor { font-size: 18px; font-weight: bold; margin-top: 4px; }
            .card-pagar   .card-valor { color: #dc2626; }
            .card-receber .card-valor { color: #16a34a; }
            .card-saldo   .card-valor { color: #2563eb; }
            .card-sub { font-size: 9px; color: #6b7280; margin-top: 2px; }
            table.dados { width: 100%; border-collapse: collapse; margin-top: 6px; }
            table.dados th, table.dados td { border: 1px solid #ccc; padding: 5px 7px; text-align: left; }
            table.dados th { background: #f5f5f5; font-weight: bold; font-size: 10px; }
            tr.data-header td { background: #e5e7eb; font-weight: bold; color: #374151; padding: 6px 8px; border-top: 2px solid #9ca3af; }
            tr.subtotal td { background: #fef9c3; color: #854d0e; font-weight: 600; border-bottom: 1px solid #facc15; }
            tr.subtotal td.valor { text-align: right; font-variant-numeric: tabular-nums; }
            tr.total-row th { background: #1e40af; color: #fff; font-size: 12px; padding: 8px 10px; }
            tr.total-row th.valor { text-align: right; font-variant-numeric: tabular-nums; }
        </style></head><body>';

        $html .= '<h1>' . htmlspecialchars($dados['titulo']) . '</h1>';
        $html .= '<div class="subtitulo">' . htmlspecialchars($empresaNome);
        $html .= ' | Período: ' . dataIsoParaBr($dataInicio) . ' a ' . dataIsoParaBr($dataFim);
        $html .= ' | Gerado em ' . date('d/m/Y H:i') . '</div>';

        // Cards de resumo
        $html .= '<table class="cards"><tr>';
        $html .= '<td class="card-pagar"><div class="card-label">🔴 A PAGAR</div>';
        $html .= '<div class="card-valor">R$ ' . number_format($sep['pagar']['valor'], 2, ',', '.') . '</div>';
        $html .= '<div class="card-sub">' . $sep['pagar']['qtd'] . ' conta(s) &middot; Pago: R$ ' . number_format($sep['pagar']['pago'], 2, ',', '.') . '</div></td>';
        $html .= '<td class="card-receber"><div class="card-label">🟢 A RECEBER</div>';
        $html .= '<div class="card-valor">R$ ' . number_format($sep['receber']['valor'], 2, ',', '.') . '</div>';
        $html .= '<div class="card-sub">' . $sep['receber']['qtd'] . ' conta(s) &middot; Recebido: R$ ' . number_format($sep['receber']['pago'], 2, ',', '.') . '</div></td>';
        $saldo = $sep['receber']['valor'] - $sep['pagar']['valor'];
        $html .= '<td class="card-saldo"><div class="card-label">💰 SALDO PREVISTO</div>';
        $html .= '<div class="card-valor">R$ ' . number_format($saldo, 2, ',', '.') . '</div>';
        $html .= '<div class="card-sub">Receber - Pagar &middot; ' . ($sep['pagar']['qtd'] + $sep['receber']['qtd']) . ' contas total</div></td>';
        $html .= '</tr></table>';

        // Tabela agrupada por data
        $html .= '<table class="dados">';
        $html .= '<colgroup>';
        $html .= '<col style="width:2.5cm;">';  // Vencimento
        $html .= '<col style="width:5cm;">';    // Descrição (-1cm)
        $html .= '<col style="width:4cm;">';    // Entidade
        $html .= '<col style="width:3cm;">';    // Categoria
        $html .= '<col style="width:2cm;">';    // Tipo (+1cm)
        $html .= '<col style="width:2.8cm;">';  // Valor
        $html .= '<col style="width:3cm;">';    // Valor Pago/Recebido
        $html .= '<col style="width:2cm;">';    // Status
        $html .= '</colgroup>';
        $html .= '<thead><tr>';
        foreach ($headers as $h) $html .= '<th>' . htmlspecialchars($h) . '</th>';
        $html .= '</tr></thead><tbody>';

        if (empty($rowsPorData)) {
            $html .= '<tr><td colspan="' . count($headers) . '" style="text-align:center; color:#999; padding:20px;">Nenhuma conta encontrada no período.</td></tr>';
        } else {
            foreach ($rowsPorData as $dataIso => $rowsData) {
                $sub = $subtotaisPorData[$dataIso] ?? null;
                // Cabeçalho da data
                $html .= '<tr class="data-header"><td colspan="' . count($headers) . '">';
                $html .= '📅 ' . dataIsoParaBr($dataIso) . ' (' . ($sub['qtd'] ?? 0) . ' conta(s))</td></tr>';
                // Linhas
                foreach ($rowsData as $row) {
                    $rowShow = $row;
                    unset($rowShow['__raw__']);
                    $rowShow[0] = ''; // Vencimento (já tá no cabeçalho da data)
                    $html .= '<tr>';
                    foreach ($rowShow as $cell) $html .= '<td>' . htmlspecialchars((string)$cell) . '</td>';
                    $html .= '</tr>';
                }
                // Subtotal
                if ($sub) {
                    $html .= '<tr class="subtotal">';
                    $html .= '<td colspan="5" style="text-align:right;">Subtotal ' . dataIsoParaBr($dataIso) . ':</td>';
                    $html .= '<td class="valor">R$ ' . number_format($sub['valor'], 2, ',', '.') . '</td>';
                    $html .= '<td class="valor">R$ ' . number_format($sub['pago'], 2, ',', '.') . '</td>';
                    $html .= '<td></td>';
                    $html .= '</tr>';
                }
            }
        }
        $html .= '</tbody>';

        // Total geral
        $html .= '<tfoot><tr class="total-row">';
        $html .= '<th colspan="5" style="text-align:right;">TOTAL GERAL (Pagar + Receber):</th>';
        $html .= '<th class="valor">R$ ' . number_format($sep['pagar']['valor'] + $sep['receber']['valor'], 2, ',', '.') . '</th>';
        $html .= '<th class="valor">R$ ' . number_format($sep['pagar']['pago'] + $sep['receber']['pago'], 2, ',', '.') . '</th>';
        $html .= '<th>' . ($sep['pagar']['qtd'] + $sep['receber']['qtd']) . ' contas</th>';
        $html .= '</tr></tfoot>';

        $html .= '</table></body></html>';
        return $html;
    }

    /**
     * Gera HTML específico do relatório por Categoria (PDF-friendly):
     *  - 3 cards de resumo no topo (Pagar / Receber / Saldo)
     *  - Tabela agrupada por categoria (com cor da categoria)
     *  - Ordem cronológica dentro de cada categoria
     *  - Subtotal por categoria
     *  - Total geral no rodapé
     */
    private function gerarHtmlRelatorioCategoria(array $dados, string $dataInicio, string $dataFim): string
    {
        $empresa = Auth::user();
        $empresaNome = '';
        foreach (($_SESSION['empresas'] ?? []) as $emp) {
            if ((int)$emp['empresa_id'] === (int)$empresa['empresa_id']) {
                $empresaNome = $emp['nome_fantasia'] ?: $emp['razao_social'];
                break;
            }
        }

        $headers = $dados['headers'];
        $rows = $dados['rows'];
        $grupos = $dados['grupos'] ?? [];
        $sep = $dados['totais_separados'] ?? ['pagar' => ['qtd'=>0,'valor'=>0,'pago'=>0], 'receber' => ['qtd'=>0,'valor'=>0,'pago'=>0]];

        uasort($grupos, function ($a, $b) { return strcmp($a['nome'], $b['nome']); });

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            body { font-family: Arial, sans-serif; font-size: 11px; }
            h1 { font-size: 18px; margin-bottom: 5px; }
            .subtitulo { color: #666; margin-bottom: 14px; font-size: 10px; }
            .cards { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
            .cards td { width: 33.33%; padding: 10px 12px; border: 1px solid #ddd; vertical-align: top; }
            .card-pagar   { border-left: 4px solid #dc2626 !important; background: #fef2f2; }
            .card-receber { border-left: 4px solid #16a34a !important; background: #f0fdf4; }
            .card-saldo   { border-left: 4px solid #2563eb !important; background: #eff6ff; }
            .card-label { font-size: 9px; text-transform: uppercase; font-weight: bold; }
            .card-pagar   .card-label { color: #991b1b; }
            .card-receber .card-label { color: #166534; }
            .card-saldo   .card-label { color: #1e40af; }
            .card-valor { font-size: 18px; font-weight: bold; margin-top: 4px; }
            .card-pagar   .card-valor { color: #dc2626; }
            .card-receber .card-valor { color: #16a34a; }
            .card-saldo   .card-valor { color: #2563eb; }
            .card-sub { font-size: 9px; color: #6b7280; margin-top: 2px; }
            table.dados { width: 100%; border-collapse: collapse; margin-top: 6px; }
            table.dados th, table.dados td { border: 1px solid #ccc; padding: 5px 7px; text-align: left; }
            table.dados th { background: #f5f5f5; font-weight: bold; font-size: 10px; }
            tr.cat-header td { font-weight: bold; padding: 6px 8px; border-top: 2px solid #888; }
            tr.subtotal td { background: #fef9c3; color: #854d0e; font-weight: 600; border-bottom: 1px solid #facc15; }
            tr.subtotal td.valor { text-align: right; font-variant-numeric: tabular-nums; }
            tr.total-row th { background: #1e40af; color: #fff; font-size: 12px; padding: 8px 10px; }
            tr.total-row th.valor { text-align: right; font-variant-numeric: tabular-nums; }
        </style></head><body>';

        $html .= '<h1>' . htmlspecialchars($dados['titulo']) . '</h1>';
        $html .= '<div class="subtitulo">' . htmlspecialchars($empresaNome);
        $html .= ' | Período: ' . dataIsoParaBr($dataInicio) . ' a ' . dataIsoParaBr($dataFim);
        $html .= ' | Gerado em ' . date('d/m/Y H:i') . '</div>';

        // Cards de resumo
        $html .= '<table class="cards"><tr>';
        $html .= '<td class="card-pagar"><div class="card-label">🔴 A PAGAR</div>';
        $html .= '<div class="card-valor">R$ ' . number_format($sep['pagar']['valor'], 2, ',', '.') . '</div>';
        $html .= '<div class="card-sub">' . $sep['pagar']['qtd'] . ' conta(s) &middot; Pago: R$ ' . number_format($sep['pagar']['pago'], 2, ',', '.') . '</div></td>';
        $html .= '<td class="card-receber"><div class="card-label">🟢 A RECEBER</div>';
        $html .= '<div class="card-valor">R$ ' . number_format($sep['receber']['valor'], 2, ',', '.') . '</div>';
        $html .= '<div class="card-sub">' . $sep['receber']['qtd'] . ' conta(s) &middot; Recebido: R$ ' . number_format($sep['receber']['pago'], 2, ',', '.') . '</div></td>';
        $saldo = $sep['receber']['valor'] - $sep['pagar']['valor'];
        $html .= '<td class="card-saldo"><div class="card-label">💰 SALDO PREVISTO</div>';
        $html .= '<div class="card-valor">R$ ' . number_format($saldo, 2, ',', '.') . '</div>';
        $html .= '<div class="card-sub">Receber - Pagar &middot; ' . count($grupos) . ' categorias</div></td>';
        $html .= '</tr></table>';

        // Tabela agrupada por categoria
        $html .= '<table class="dados">';
        $html .= '<colgroup>';
        $html .= '<col style="width:2.5cm;">';
        $html .= '<col style="width:7cm;">';
        $html .= '<col style="width:4cm;">';
        $html .= '<col style="width:2cm;">';
        $html .= '<col style="width:2.8cm;">';
        $html .= '<col style="width:3cm;">';
        $html .= '<col style="width:2cm;">';
        $html .= '</colgroup>';
        $html .= '<thead><tr>';
        foreach ($headers as $h) $html .= '<th>' . htmlspecialchars($h) . '</th>';
        $html .= '</tr></thead><tbody>';

        if (empty($grupos)) {
            $html .= '<tr><td colspan="' . count($headers) . '" style="text-align:center; color:#999; padding:20px;">Nenhuma conta encontrada no período.</td></tr>';
        } else {
            foreach ($grupos as $g) {
                $cor = !empty($g['cor']) && preg_match('/^#[0-9A-Fa-f]{6}$/', $g['cor']) ? $g['cor'] : '#6b7280';
                // Cabeçalho da categoria
                $html .= '<tr class="cat-header"><td colspan="' . count($headers) . '" style="background:' . $cor . '; color:#fff;">';
                $html .= '🏷️ ' . htmlspecialchars($g['nome']);
                $html .= ' <span style="opacity:.85; font-weight:400; font-size:9px;">(' . $g['qtd'] . ' conta(s) &middot; Pagar: R$ ' . number_format($g['pagar'], 2, ',', '.') . ' &middot; Receber: R$ ' . number_format($g['receber'], 2, ',', '.') . ')</span>';
                $html .= '</td></tr>';
                // Linhas (em ordem cronológica - já vem ordenado do controller)
                foreach ($g['rows_idx'] as $idx) {
                    $row = $rows[$idx];
                    $rowShow = $row;
                    unset($rowShow['__raw__']);
                    $html .= '<tr>';
                    foreach ($rowShow as $cell) $html .= '<td>' . htmlspecialchars((string)$cell) . '</td>';
                    $html .= '</tr>';
                }
                // Subtotal da categoria
                $html .= '<tr class="subtotal">';
                $html .= '<td colspan="4" style="text-align:right;">Subtotal ' . htmlspecialchars($g['nome']) . ':</td>';
                $html .= '<td class="valor">R$ ' . number_format($g['valor'], 2, ',', '.') . '</td>';
                $html .= '<td class="valor">R$ ' . number_format($g['pago'], 2, ',', '.') . '</td>';
                $html .= '<td></td>';
                $html .= '</tr>';
            }
        }
        $html .= '</tbody>';

        // Total geral
        $html .= '<tfoot><tr class="total-row">';
        $html .= '<th colspan="4" style="text-align:right;">TOTAL GERAL (' . count($grupos) . ' categorias):</th>';
        $html .= '<th class="valor">R$ ' . number_format($sep['pagar']['valor'] + $sep['receber']['valor'], 2, ',', '.') . '</th>';
        $html .= '<th class="valor">R$ ' . number_format($sep['pagar']['pago'] + $sep['receber']['pago'], 2, ',', '.') . '</th>';
        $html .= '<th>' . ($sep['pagar']['qtd'] + $sep['receber']['qtd']) . ' contas</th>';
        $html .= '</tr></tfoot>';

        $html .= '</table></body></html>';
        return $html;
    }

    /**
     * Gera HTML específico do relatório de CONTAS A PAGAR (PDF-friendly):
     *  - Cards de resumo (Total, Pago, Pendente, Qtd)
     *  - Filtros aplicados visíveis
     *  - Tabela agrupada por data
     *  - Subtotal por data
     *  - Total geral no rodapé
     */
    private function gerarHtmlRelatorioContasPagar(array $dados, string $dataInicio, string $dataFim): string
    {
        $empresa = Auth::user();
        $empresaNome = '';
        foreach (($_SESSION['empresas'] ?? []) as $emp) {
            if ((int)$emp['empresa_id'] === (int)$empresa['empresa_id']) {
                $empresaNome = $emp['nome_fantasia'] ?: $emp['razao_social'];
                break;
            }
        }

        $headers = $dados['headers'];
        $rows = $dados['rows'];
        $subtotaisPorData = $dados['subtotais_por_data'] ?? [];
        $totais = $dados['totais'] ?? ['qtd' => 0, 'valor' => 0.0, 'pago' => 0.0, 'pendente' => 0.0];
        $statusFiltrado = $dados['status_filtrado'] ?? [];

        // Agrupa rows por data
        $rowsPorData = [];
        foreach ($rows as $row) {
            $raw = $row['__raw__'] ?? null;
            if (!$raw) continue;
            $dataIso = $raw['data_vencimento'];
            if (!isset($rowsPorData[$dataIso])) $rowsPorData[$dataIso] = [];
            $rowsPorData[$dataIso][] = $row;
        }
        ksort($rowsPorData);

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            body { font-family: Arial, sans-serif; font-size: 11px; }
            h1 { font-size: 18px; margin-bottom: 5px; }
            .subtitulo { color: #666; margin-bottom: 14px; font-size: 10px; }
            .filtros { background: #fef2f2; border: 1px solid #fecaca; padding: 8px 12px; margin-bottom: 12px; font-size: 10px; }
            .filtros strong { color: #991b1b; }
            .cards { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
            .cards td { width: 25%; padding: 10px 12px; border: 1px solid #ddd; vertical-align: top; }
            .card-total     { border-left: 4px solid #dc2626 !important; background: #fef2f2; }
            .card-pago      { border-left: 4px solid #16a34a !important; background: #f0fdf4; }
            .card-pendente  { border-left: 4px solid #ea580c !important; background: #fff7ed; }
            .card-qtd       { border-left: 4px solid #2563eb !important; background: #eff6ff; }
            .card-label { font-size: 9px; text-transform: uppercase; font-weight: bold; }
            .card-total    .card-label { color: #991b1b; }
            .card-pago     .card-label { color: #166534; }
            .card-pendente .card-label { color: #9a3412; }
            .card-qtd      .card-label { color: #1e40af; }
            .card-valor { font-size: 16px; font-weight: bold; margin-top: 4px; }
            .card-total    .card-valor { color: #dc2626; }
            .card-pago     .card-valor { color: #16a34a; }
            .card-pendente .card-valor { color: #ea580c; }
            .card-qtd      .card-valor { color: #2563eb; }
            .card-sub { font-size: 9px; color: #6b7280; margin-top: 2px; }
            table.dados { width: 100%; border-collapse: collapse; margin-top: 6px; }
            table.dados th, table.dados td { border: 1px solid #ccc; padding: 5px 7px; text-align: left; }
            table.dados th { background: #f5f5f5; font-weight: bold; font-size: 10px; }
            tr.data-header td { background: #e5e7eb; font-weight: bold; color: #374151; padding: 6px 8px; border-top: 2px solid #9ca3af; }
            tr.subtotal td { background: #fef9c3; color: #854d0e; font-weight: 600; border-bottom: 1px solid #facc15; }
            tr.subtotal td.valor { text-align: right; font-variant-numeric: tabular-nums; }
            tr.total-row th { background: #1e40af; color: #fff; font-size: 12px; padding: 8px 10px; }
            tr.total-row th.valor { text-align: right; font-variant-numeric: tabular-nums; }
        </style></head><body>';

        $html .= '<h1>🔴 ' . htmlspecialchars($dados['titulo']) . '</h1>';
        $html .= '<div class="subtitulo">' . htmlspecialchars($empresaNome);
        $html .= ' | Período: ' . dataIsoParaBr($dataInicio) . ' a ' . dataIsoParaBr($dataFim);
        $html .= ' | Gerado em ' . date('d/m/Y H:i') . '</div>';

        // Filtros aplicados
        $html .= '<div class="filtros"><strong>Filtros:</strong> Tipo: <strong>PAGAR</strong>';
        if (!empty($statusFiltrado)) {
            $html .= ' | Status: <strong>' . htmlspecialchars(implode(', ', $statusFiltrado)) . '</strong>';
        } else {
            $html .= ' | Status: <strong>TODOS</strong>';
        }
        $html .= '</div>';

        // Cards de resumo
        $html .= '<table class="cards"><tr>';
        $html .= '<td class="card-total"><div class="card-label">🔴 TOTAL</div>';
        $html .= '<div class="card-valor">R$ ' . number_format($totais['valor'], 2, ',', '.') . '</div>';
        $html .= '<div class="card-sub">' . $totais['qtd'] . ' conta(s)</div></td>';
        $html .= '<td class="card-pago"><div class="card-label">🟢 PAGO</div>';
        $html .= '<div class="card-valor">R$ ' . number_format($totais['pago'], 2, ',', '.') . '</div>';
        $html .= '<div class="card-sub">Valor liquidado</div></td>';
        $html .= '<td class="card-pendente"><div class="card-label">🟠 PENDENTE</div>';
        $html .= '<div class="card-valor">R$ ' . number_format($totais['pendente'], 2, ',', '.') . '</div>';
        $html .= '<div class="card-sub">A pagar ainda</div></td>';
        $html .= '<td class="card-qtd"><div class="card-label">🔵 QTD</div>';
        $html .= '<div class="card-valor">' . $totais['qtd'] . '</div>';
        $html .= '<div class="card-sub">Contas no período</div></td>';
        $html .= '</tr></table>';

        // Tabela agrupada por data
        $html .= '<table class="dados">';
        $html .= '<colgroup>';
        $html .= '<col style="width:2.2cm;">';  // Vencimento
        $html .= '<col style="width:4.5cm;">';  // Descrição
        $html .= '<col style="width:3.5cm;">';  // Fornecedor
        $html .= '<col style="width:2.5cm;">';  // Categoria
        $html .= '<col style="width:2.3cm;">';  // Valor
        $html .= '<col style="width:2.3cm;">';  // Valor Pago
        $html .= '<col style="width:1.8cm;">';  // Status
        $html .= '<col style="width:1.8cm;">';  // Forma Pgto
        $html .= '<col style="width:2.3cm;">';  // Nº Documento
        $html .= '</colgroup>';
        $html .= '<thead><tr>';
        foreach ($headers as $h) $html .= '<th>' . htmlspecialchars($h) . '</th>';
        $html .= '</tr></thead><tbody>';

        if (empty($rowsPorData)) {
            $html .= '<tr><td colspan="' . count($headers) . '" style="text-align:center; color:#999; padding:20px;">Nenhuma conta a pagar encontrada no período/filtros.</td></tr>';
        } else {
            foreach ($rowsPorData as $dataIso => $rowsData) {
                $sub = $subtotaisPorData[$dataIso] ?? null;
                $html .= '<tr class="data-header"><td colspan="' . count($headers) . '">';
                $html .= '📅 ' . dataIsoParaBr($dataIso) . ' (' . ($sub['qtd'] ?? 0) . ' conta(s))</td></tr>';
                foreach ($rowsData as $row) {
                    $rowShow = $row;
                    unset($rowShow['__raw__']);
                    $rowShow[0] = ''; // Vencimento (já tá no cabeçalho da data)
                    $html .= '<tr>';
                    foreach ($rowShow as $cell) $html .= '<td>' . htmlspecialchars((string)$cell) . '</td>';
                    $html .= '</tr>';
                }
                if ($sub) {
                    $html .= '<tr class="subtotal">';
                    $html .= '<td colspan="4" style="text-align:right;">Subtotal ' . dataIsoParaBr($dataIso) . ':</td>';
                    $html .= '<td class="valor">R$ ' . number_format($sub['valor'], 2, ',', '.') . '</td>';
                    $html .= '<td class="valor">R$ ' . number_format($sub['pago'], 2, ',', '.') . '</td>';
                    $html .= '<td colspan="3"></td>';
                    $html .= '</tr>';
                }
            }
        }
        $html .= '</tbody>';

        // Total geral
        $html .= '<tfoot><tr class="total-row">';
        $html .= '<th colspan="4" style="text-align:right;">TOTAL GERAL:</th>';
        $html .= '<th class="valor">R$ ' . number_format($totais['valor'], 2, ',', '.') . '</th>';
        $html .= '<th class="valor">R$ ' . number_format($totais['pago'], 2, ',', '.') . '</th>';
        $html .= '<th colspan="3" style="text-align:left;">' . $totais['qtd'] . ' contas</th>';
        $html .= '</tr></tfoot>';

        $html .= '</table></body></html>';
        return $html;
    }

    /**
     * Gera HTML específico do relatório de CONTAS A RECEBER (PDF-friendly):
     *  - Cards de resumo (Total, Recebido, Pendente, Qtd)
     *  - Filtros aplicados visíveis
     *  - Tabela agrupada por data
     *  - Subtotal por data
     *  - Total geral no rodapé
     */
    private function gerarHtmlRelatorioContasReceber(array $dados, string $dataInicio, string $dataFim): string
    {
        $empresa = Auth::user();
        $empresaNome = '';
        foreach (($_SESSION['empresas'] ?? []) as $emp) {
            if ((int)$emp['empresa_id'] === (int)$empresa['empresa_id']) {
                $empresaNome = $emp['nome_fantasia'] ?: $emp['razao_social'];
                break;
            }
        }

        $headers = $dados['headers'];
        $rows = $dados['rows'];
        $subtotaisPorData = $dados['subtotais_por_data'] ?? [];
        $totais = $dados['totais'] ?? ['qtd' => 0, 'valor' => 0.0, 'recebido' => 0.0, 'pendente' => 0.0];
        $statusFiltrado = $dados['status_filtrado'] ?? [];

        // Agrupa rows por data
        $rowsPorData = [];
        foreach ($rows as $row) {
            $raw = $row['__raw__'] ?? null;
            if (!$raw) continue;
            $dataIso = $raw['data_vencimento'];
            if (!isset($rowsPorData[$dataIso])) $rowsPorData[$dataIso] = [];
            $rowsPorData[$dataIso][] = $row;
        }
        ksort($rowsPorData);

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            body { font-family: Arial, sans-serif; font-size: 11px; }
            h1 { font-size: 18px; margin-bottom: 5px; }
            .subtitulo { color: #666; margin-bottom: 14px; font-size: 10px; }
            .filtros { background: #f0fdf4; border: 1px solid #bbf7d0; padding: 8px 12px; margin-bottom: 12px; font-size: 10px; }
            .filtros strong { color: #166534; }
            .cards { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
            .cards td { width: 25%; padding: 10px 12px; border: 1px solid #ddd; vertical-align: top; }
            .card-total     { border-left: 4px solid #16a34a !important; background: #f0fdf4; }
            .card-recebido  { border-left: 4px solid #2563eb !important; background: #eff6ff; }
            .card-pendente  { border-left: 4px solid #ea580c !important; background: #fff7ed; }
            .card-qtd       { border-left: 4px solid #7c3aed !important; background: #faf5ff; }
            .card-label { font-size: 9px; text-transform: uppercase; font-weight: bold; }
            .card-total    .card-label { color: #166534; }
            .card-recebido .card-label { color: #1e40af; }
            .card-pendente .card-label { color: #9a3412; }
            .card-qtd      .card-label { color: #6b21a8; }
            .card-valor { font-size: 16px; font-weight: bold; margin-top: 4px; }
            .card-total    .card-valor { color: #16a34a; }
            .card-recebido .card-valor { color: #2563eb; }
            .card-pendente .card-valor { color: #ea580c; }
            .card-qtd      .card-valor { color: #7c3aed; }
            .card-sub { font-size: 9px; color: #6b7280; margin-top: 2px; }
            table.dados { width: 100%; border-collapse: collapse; margin-top: 6px; }
            table.dados th, table.dados td { border: 1px solid #ccc; padding: 5px 7px; text-align: left; }
            table.dados th { background: #f5f5f5; font-weight: bold; font-size: 10px; }
            tr.data-header td { background: #e5e7eb; font-weight: bold; color: #374151; padding: 6px 8px; border-top: 2px solid #9ca3af; }
            tr.subtotal td { background: #fef9c3; color: #854d0e; font-weight: 600; border-bottom: 1px solid #facc15; }
            tr.subtotal td.valor { text-align: right; font-variant-numeric: tabular-nums; }
            tr.total-row th { background: #1e40af; color: #fff; font-size: 12px; padding: 8px 10px; }
            tr.total-row th.valor { text-align: right; font-variant-numeric: tabular-nums; }
        </style></head><body>';

        $html .= '<h1>🟢 ' . htmlspecialchars($dados['titulo']) . '</h1>';
        $html .= '<div class="subtitulo">' . htmlspecialchars($empresaNome);
        $html .= ' | Período: ' . dataIsoParaBr($dataInicio) . ' a ' . dataIsoParaBr($dataFim);
        $html .= ' | Gerado em ' . date('d/m/Y H:i') . '</div>';

        // Filtros aplicados
        $html .= '<div class="filtros"><strong>Filtros:</strong> Tipo: <strong>RECEBER</strong>';
        if (!empty($statusFiltrado)) {
            $html .= ' | Status: <strong>' . htmlspecialchars(implode(', ', $statusFiltrado)) . '</strong>';
        } else {
            $html .= ' | Status: <strong>TODOS</strong>';
        }
        $html .= '</div>';

        // Cards de resumo
        $html .= '<table class="cards"><tr>';
        $html .= '<td class="card-total"><div class="card-label">🟢 TOTAL</div>';
        $html .= '<div class="card-valor">R$ ' . number_format($totais['valor'], 2, ',', '.') . '</div>';
        $html .= '<div class="card-sub">' . $totais['qtd'] . ' conta(s)</div></td>';
        $html .= '<td class="card-recebido"><div class="card-label">🔵 RECEBIDO</div>';
        $html .= '<div class="card-valor">R$ ' . number_format($totais['recebido'], 2, ',', '.') . '</div>';
        $html .= '<div class="card-sub">Valor liquidado</div></td>';
        $html .= '<td class="card-pendente"><div class="card-label">🟠 PENDENTE</div>';
        $html .= '<div class="card-valor">R$ ' . number_format($totais['pendente'], 2, ',', '.') . '</div>';
        $html .= '<div class="card-sub">A receber ainda</div></td>';
        $html .= '<td class="card-qtd"><div class="card-label">🟣 QTD</div>';
        $html .= '<div class="card-valor">' . $totais['qtd'] . '</div>';
        $html .= '<div class="card-sub">Contas no período</div></td>';
        $html .= '</tr></table>';

        // Tabela agrupada por data
        $html .= '<table class="dados">';
        $html .= '<colgroup>';
        $html .= '<col style="width:2.2cm;">';  // Vencimento
        $html .= '<col style="width:4.5cm;">';  // Descrição
        $html .= '<col style="width:3.5cm;">';  // Cliente
        $html .= '<col style="width:2.5cm;">';  // Categoria
        $html .= '<col style="width:2.3cm;">';  // Valor
        $html .= '<col style="width:2.5cm;">';  // Valor Recebido
        $html .= '<col style="width:1.8cm;">';  // Status
        $html .= '<col style="width:1.8cm;">';  // Forma Recb
        $html .= '<col style="width:2.3cm;">';  // Nº Documento
        $html .= '</colgroup>';
        $html .= '<thead><tr>';
        foreach ($headers as $h) $html .= '<th>' . htmlspecialchars($h) . '</th>';
        $html .= '</tr></thead><tbody>';

        if (empty($rowsPorData)) {
            $html .= '<tr><td colspan="' . count($headers) . '" style="text-align:center; color:#999; padding:20px;">Nenhuma conta a receber encontrada no período/filtros.</td></tr>';
        } else {
            foreach ($rowsPorData as $dataIso => $rowsData) {
                $sub = $subtotaisPorData[$dataIso] ?? null;
                $html .= '<tr class="data-header"><td colspan="' . count($headers) . '">';
                $html .= '📅 ' . dataIsoParaBr($dataIso) . ' (' . ($sub['qtd'] ?? 0) . ' conta(s))</td></tr>';
                foreach ($rowsData as $row) {
                    $rowShow = $row;
                    unset($rowShow['__raw__']);
                    $rowShow[0] = '';
                    $html .= '<tr>';
                    foreach ($rowShow as $cell) $html .= '<td>' . htmlspecialchars((string)$cell) . '</td>';
                    $html .= '</tr>';
                }
                if ($sub) {
                    $html .= '<tr class="subtotal">';
                    $html .= '<td colspan="4" style="text-align:right;">Subtotal ' . dataIsoParaBr($dataIso) . ':</td>';
                    $html .= '<td class="valor">R$ ' . number_format($sub['valor'], 2, ',', '.') . '</td>';
                    $html .= '<td class="valor">R$ ' . number_format($sub['recebido'], 2, ',', '.') . '</td>';
                    $html .= '<td colspan="3"></td>';
                    $html .= '</tr>';
                }
            }
        }
        $html .= '</tbody>';

        // Total geral
        $html .= '<tfoot><tr class="total-row">';
        $html .= '<th colspan="4" style="text-align:right;">TOTAL GERAL:</th>';
        $html .= '<th class="valor">R$ ' . number_format($totais['valor'], 2, ',', '.') . '</th>';
        $html .= '<th class="valor">R$ ' . number_format($totais['recebido'], 2, ',', '.') . '</th>';
        $html .= '<th colspan="3" style="text-align:left;">' . $totais['qtd'] . ' contas</th>';
        $html .= '</tr></tfoot>';

        $html .= '</table></body></html>';
        return $html;
    }

    /**
     * Gera HTML específico do relatório de Contas Atrasadas (PDF-friendly):
     *  - 3 cards de resumo no topo (Pagar / Receber / Saldo)
     *  - Tabela de CONTAS A PAGAR ATRASADAS (com subtotal)
     *  - Tabela de CONTAS A RECEBER ATRASADAS (com subtotal)
     *  - Total geral no rodapé
     */
    private function gerarHtmlRelatorioAtrasadas(array $dados, string $dataInicio, string $dataFim): string
    {
        $empresa = Auth::user();
        $empresaNome = '';
        foreach (($_SESSION['empresas'] ?? []) as $emp) {
            if ((int)$emp['empresa_id'] === (int)$empresa['empresa_id']) {
                $empresaNome = $emp['nome_fantasia'] ?: $emp['razao_social'];
                break;
            }
        }

        $headers = $dados['headers'];
        $rowsPagar   = $dados['rows_pagar']   ?? [];
        $rowsReceber = $dados['rows_receber'] ?? [];
        $sep = $dados['totais_separados'] ?? [
            'pagar'   => ['qtd' => 0, 'valor' => 0.0, 'max_atraso' => 0],
            'receber' => ['qtd' => 0, 'valor' => 0.0, 'max_atraso' => 0],
        ];
        $totalQtd = $sep['pagar']['qtd'] + $sep['receber']['qtd'];
        $maxAtrasoGeral = max($sep['pagar']['max_atraso'], $sep['receber']['max_atraso']);

        $hoje = dataIsoParaBr(date('Y-m-d'));

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            body { font-family: Arial, sans-serif; font-size: 11px; }
            h1 { font-size: 18px; margin-bottom: 5px; }
            h2 { font-size: 14px; margin: 18px 0 6px 0; }
            .subtitulo { color: #666; margin-bottom: 14px; font-size: 10px; }
            .cards { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
            .cards td { width: 33.33%; padding: 10px 12px; border: 1px solid #ddd; vertical-align: top; }
            .card-pagar   { border-left: 4px solid #dc2626 !important; background: #fef2f2; }
            .card-receber { border-left: 4px solid #16a34a !important; background: #f0fdf4; }
            .card-saldo   { border-left: 4px solid #2563eb !important; background: #eff6ff; }
            .card-label { font-size: 9px; text-transform: uppercase; font-weight: bold; }
            .card-pagar   .card-label { color: #991b1b; }
            .card-receber .card-label { color: #166534; }
            .card-saldo   .card-label { color: #1e40af; }
            .card-valor { font-size: 18px; font-weight: bold; margin-top: 4px; }
            .card-pagar   .card-valor { color: #dc2626; }
            .card-receber .card-valor { color: #16a34a; }
            .card-saldo   .card-valor { color: #2563eb; }
            .card-sub { font-size: 9px; color: #6b7280; margin-top: 2px; }
            table.dados { width: 100%; border-collapse: collapse; margin-top: 4px; }
            table.dados th, table.dados td { border: 1px solid #ccc; padding: 5px 7px; text-align: left; }
            table.dados th { background: #f5f5f5; font-weight: bold; font-size: 10px; }
            th.head-pagar   { background: #fee2e2 !important; color: #991b1b; }
            th.head-receber { background: #dcfce7 !important; color: #166534; }
            tr.subtotal-pagar   th { background: #991b1b; color: white; padding: 7px 10px; font-size: 11px; }
            tr.subtotal-receber th { background: #166534; color: white; padding: 7px 10px; font-size: 11px; }
            tr.subtotal th.valor { text-align: right; font-variant-numeric: tabular-nums; }
            .vazio { text-align: center; color: #999; padding: 16px; font-style: italic; }
            .total-geral {
                margin-top: 18px; padding: 14px 16px; background: #1e40af; color: white;
                border-radius: 6px; display: flex; justify-content: space-between; align-items: center;
            }
            .total-geral .label { font-size: 11px; text-transform: uppercase; opacity: 0.85; font-weight: 600; }
            .total-geral .valor { font-size: 22px; font-weight: 700; font-variant-numeric: tabular-nums; }
            .total-geral .sub { font-size: 10px; opacity: 0.75; margin-top: 2px; }
        </style></head><body>';

        $html .= '<h1>' . htmlspecialchars($dados['titulo']) . '</h1>';
        $html .= '<div class="subtitulo">' . htmlspecialchars($empresaNome);
        $html .= ' | Vencimentos anteriores a ' . $hoje;
        $html .= ' | Gerado em ' . date('d/m/Y H:i') . '</div>';

        // Cards de resumo
        $html .= '<table class="cards"><tr>';
        $html .= '<td class="card-pagar"><div class="card-label">🔴 A PAGAR (atrasado)</div>';
        $html .= '<div class="card-valor">R$ ' . number_format($sep['pagar']['valor'], 2, ',', '.') . '</div>';
        $html .= '<div class="card-sub">' . $sep['pagar']['qtd'] . ' conta(s) &middot; Maior atraso: ' . $sep['pagar']['max_atraso'] . ' dia(s)</div></td>';
        $html .= '<td class="card-receber"><div class="card-label">🟢 A RECEBER (atrasado)</div>';
        $html .= '<div class="card-valor">R$ ' . number_format($sep['receber']['valor'], 2, ',', '.') . '</div>';
        $html .= '<div class="card-sub">' . $sep['receber']['qtd'] . ' conta(s) &middot; Maior atraso: ' . $sep['receber']['max_atraso'] . ' dia(s)</div></td>';
        $saldo = $sep['receber']['valor'] - $sep['pagar']['valor'];
        $html .= '<td class="card-saldo"><div class="card-label">💰 SALDO PREVISTO</div>';
        $html .= '<div class="card-valor">R$ ' . number_format($saldo, 2, ',', '.') . '</div>';
        $html .= '<div class="card-sub">Receber - Pagar &middot; ' . $totalQtd . ' contas &middot; Máx: ' . $maxAtrasoGeral . ' dia(s)</div></td>';
        $html .= '</tr></table>';

        // --- Tabela PAGAR ---
        $html .= '<h2 style="color: #991b1b;">🔴 Contas a Pagar Atrasadas</h2>';
        $html .= '<table class="dados">';
        $html .= '<colgroup>';
        $html .= '<col style="width:2.5cm;">';
        $html .= '<col style="width:7cm;">';
        $html .= '<col style="width:4cm;">';
        $html .= '<col style="width:3cm;">';
        $html .= '<col style="width:2.8cm;">';
        $html .= '<col style="width:1.8cm;">';
        $html .= '</colgroup>';
        $html .= '<thead><tr>';
        foreach ($headers as $h) {
            $label = $h === 'Entidade' ? 'Fornecedor' : $h;
            $html .= '<th class="head-pagar">' . htmlspecialchars($label) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        if (empty($rowsPagar)) {
            $html .= '<tr><td colspan="' . count($headers) . '" class="vazio">Nenhuma conta a pagar atrasada.</td></tr>';
        } else {
            foreach ($rowsPagar as $row) {
                $rowShow = $row;
                unset($rowShow['__raw__']);
                $html .= '<tr>';
                foreach ($rowShow as $cell) $html .= '<td>' . htmlspecialchars((string)$cell) . '</td>';
                $html .= '</tr>';
            }
        }
        $html .= '</tbody>';
        if (!empty($rowsPagar)) {
            $html .= '<tfoot><tr class="subtotal-pagar">';
            $html .= '<th colspan="4" style="text-align:right;">SUBTOTAL A PAGAR (atrasado):</th>';
            $html .= '<th class="valor">R$ ' . number_format($sep['pagar']['valor'], 2, ',', '.') . '</th>';
            $html .= '<th>' . $sep['pagar']['qtd'] . ' conta(s)</th>';
            $html .= '</tr></tfoot>';
        }
        $html .= '</table>';

        // --- Tabela RECEBER ---
        $html .= '<h2 style="color: #166534;">🟢 Contas a Receber Atrasadas</h2>';
        $html .= '<table class="dados">';
        $html .= '<colgroup>';
        $html .= '<col style="width:2.5cm;">';
        $html .= '<col style="width:7cm;">';
        $html .= '<col style="width:4cm;">';
        $html .= '<col style="width:3cm;">';
        $html .= '<col style="width:2.8cm;">';
        $html .= '<col style="width:1.8cm;">';
        $html .= '</colgroup>';
        $html .= '<thead><tr>';
        foreach ($headers as $h) {
            $label = $h === 'Entidade' ? 'Cliente' : $h;
            $html .= '<th class="head-receber">' . htmlspecialchars($label) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        if (empty($rowsReceber)) {
            $html .= '<tr><td colspan="' . count($headers) . '" class="vazio">Nenhuma conta a receber atrasada.</td></tr>';
        } else {
            foreach ($rowsReceber as $row) {
                $rowShow = $row;
                unset($rowShow['__raw__']);
                $html .= '<tr>';
                foreach ($rowShow as $cell) $html .= '<td>' . htmlspecialchars((string)$cell) . '</td>';
                $html .= '</tr>';
            }
        }
        $html .= '</tbody>';
        if (!empty($rowsReceber)) {
            $html .= '<tfoot><tr class="subtotal-receber">';
            $html .= '<th colspan="4" style="text-align:right;">SUBTOTAL A RECEBER (atrasado):</th>';
            $html .= '<th class="valor">R$ ' . number_format($sep['receber']['valor'], 2, ',', '.') . '</th>';
            $html .= '<th>' . $sep['receber']['qtd'] . ' conta(s)</th>';
            $html .= '</tr></tfoot>';
        }
        $html .= '</table>';

        // --- TOTAL GERAL ---
        $html .= '<div class="total-geral">';
        $html .= '<div>';
        $html .= '<div class="label">📊 TOTAL GERAL ATRASADO</div>';
        $html .= '<div class="sub">' . $sep['pagar']['qtd'] . ' a pagar + ' . $sep['receber']['qtd'] . ' a receber = ' . $totalQtd . ' conta(s)</div>';
        $html .= '</div>';
        $html .= '<div style="text-align:right;">';
        $html .= '<div class="valor">R$ ' . number_format($sep['pagar']['valor'] + $sep['receber']['valor'], 2, ',', '.') . '</div>';
        $html .= '<div class="sub">Saldo previsto: R$ ' . number_format($saldo, 2, ',', '.') . '</div>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</body></html>';
        return $html;
    }
}