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
                break;
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

        switch ($tipo) {
            case 'periodo':     $dados = $this->relatorioPeriodo($empresaId, $dataInicio, $dataFim); break;
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

    private function relatorioCategoria(int $empresaId, string $dataInicio, string $dataFim): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT cat.nome AS categoria, cat.cor,
                   "pagar" AS tipo,
                   COUNT(*) AS qtd,
                   SUM(cp.valor) AS total_pendente,
                   SUM(CASE WHEN cp.status="paga" THEN cp.valor_pago ELSE 0 END) AS total_pago
            FROM contas_pagar cp
            JOIN categorias cat ON cat.id = cp.categoria_id
            WHERE cp.empresa_id = ? AND cp.data_vencimento BETWEEN ? AND ?
            GROUP BY cat.id
            UNION ALL
            SELECT cat.nome AS categoria, cat.cor,
                   "receber" AS tipo,
                   COUNT(*) AS qtd,
                   SUM(cr.valor) AS total_pendente,
                   SUM(CASE WHEN cr.status="recebida" THEN cr.valor_recebido ELSE 0 END) AS total_pago
            FROM contas_receber cr
            JOIN categorias cat ON cat.id = cr.categoria_id
            WHERE cr.empresa_id = ? AND cr.data_vencimento BETWEEN ? AND ?
            GROUP BY cat.id
            ORDER BY tipo, categoria
        ');
        $stmt->execute([$empresaId, $dataInicio, $dataFim, $empresaId, $dataInicio, $dataFim]);
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
            'titulo'  => 'Por Categoria',
            'headers' => ['Tipo', 'Categoria', 'Qtd', 'Pendente', 'Pago/Recebido'],
            'rows'    => array_map(function ($r) {
                return [
                    ucfirst($r['tipo']),
                    $r['categoria'],
                    $r['qtd'],
                    number_format((float)$r['total_pendente'], 2, ',', '.'),
                    number_format((float)$r['total_pago'], 2, ',', '.'),
                ];
            }, $rows),
            'totais'  => [
                'label' => 'TOTAL',
                'cells' => [
                    'TOTAL',
                    'Σ ' . count($rows) . ' categorias',
                    (string)$totalQtd,
                    number_format($totalPendente, 2, ',', '.'),
                    number_format($totalPago, 2, ',', '.'),
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

    private function relatorioAtrasadas(int $empresaId): array
    {
        $db = Database::getConnection();
        $hoje = date('Y-m-d');

        $stmt = $db->prepare('
            SELECT "Pagar" AS tipo, cp.data_vencimento, cp.descricao,
                   f.razao_social AS entidade, cat.nome AS categoria,
                   cp.valor, DATEDIFF(?, cp.data_vencimento) AS dias_atraso
            FROM contas_pagar cp
            JOIN fornecedores f ON f.id = cp.fornecedor_id
            JOIN categorias cat ON cat.id = cp.categoria_id
            WHERE cp.empresa_id = ? AND cp.status IN ("pendente","aprovada") AND cp.data_vencimento < ?
            UNION ALL
            SELECT "Receber" AS tipo, cr.data_vencimento, cr.descricao,
                   c.razao_social AS entidade, cat.nome AS categoria,
                   cr.valor, DATEDIFF(?, cr.data_vencimento) AS dias_atraso
            FROM contas_receber cr
            JOIN clientes c ON c.id = cr.cliente_id
            JOIN categorias cat ON cat.id = cr.categoria_id
            WHERE cr.empresa_id = ? AND cr.status IN ("pendente","aprovada") AND cr.data_vencimento < ?
            ORDER BY dias_atraso DESC
        ');
        $stmt->execute([$hoje, $empresaId, $hoje, $hoje, $empresaId, $hoje]);
        $rows = $stmt->fetchAll();

        // Totais: qtd + valor
        $totalValor   = 0.0;
        $maxAtraso    = 0;
        foreach ($rows as $r) {
            $totalValor += (float)$r['valor'];
            if ((int)$r['dias_atraso'] > $maxAtraso) {
                $maxAtraso = (int)$r['dias_atraso'];
            }
        }

        return [
            'titulo'  => 'Contas Atrasadas',
            'headers' => ['Tipo', 'Vencimento', 'Descrição', 'Entidade', 'Categoria', 'Valor', 'Dias Atraso'],
            'rows'    => array_map(function ($r) {
                return [
                    $r['tipo'],
                    dataIsoParaBr($r['data_vencimento']),
                    $r['descricao'],
                    $r['entidade'],
                    $r['categoria'],
                    number_format((float)$r['valor'], 2, ',', '.'),
                    $r['dias_atraso'],
                ];
            }, $rows),
            'totais'  => [
                'label' => 'TOTAL ATRASADO',
                'cells' => [
                    'TOTAL',
                    '', // Vencimento
                    'Σ ' . count($rows) . ' contas',
                    '', // Entidade
                    '', // Categoria
                    number_format($totalValor, 2, ',', '.'),
                    'máx: ' . $maxAtraso,
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
        // Para o relatório de Período, usa a view customizada (agrupada por data)
        if ($tipo === 'periodo') {
            return $this->gerarHtmlRelatorioPeriodo($dados, $dataInicio, $dataFim);
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
}