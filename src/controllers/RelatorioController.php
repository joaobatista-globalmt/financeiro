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
                break;
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
            default:
                Flash::set('erro', 'Tipo inválido.');
                redirect('relatorios.php');
        }

        if ($formato === 'csv') {
            $this->exportarCsv($tipo, $dados);
        } elseif ($formato === 'pdf') {
            $this->exportarPdf($tipo, $dados, $dataInicio, $dataFim);
        } else {
            redirect('relatorios.php');
        }
    }

    // ============================================================
    // RELATÓRIOS (cada um retorna ['headers' => [...], 'rows' => [...], 'titulo' => '...'])
    // ============================================================

    private function relatorioPeriodo(int $empresaId, string $dataInicio, string $dataFim): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT cp.data_vencimento, cp.descricao, f.razao_social AS entidade,
                   cat.nome AS categoria, cp.valor, cp.valor_pago, cp.status
            FROM contas_pagar cp
            JOIN fornecedores f ON f.id = cp.fornecedor_id
            JOIN categorias cat ON cat.id = cp.categoria_id
            WHERE cp.empresa_id = ? AND cp.data_vencimento BETWEEN ? AND ?
            UNION ALL
            SELECT cr.data_vencimento, cr.descricao, c.razao_social AS entidade,
                   cat.nome AS categoria, cr.valor, cr.valor_recebido AS valor_pago, cr.status
            FROM contas_receber cr
            JOIN clientes c ON c.id = cr.cliente_id
            JOIN categorias cat ON cat.id = cr.categoria_id
            WHERE cr.empresa_id = ? AND cr.data_vencimento BETWEEN ? AND ?
            ORDER BY data_vencimento
        ');
        $stmt->execute([$empresaId, $dataInicio, $dataFim, $empresaId, $dataInicio, $dataFim]);
        $rows = $stmt->fetchAll();

        return [
            'titulo'  => 'Contas por Período',
            'headers' => ['Vencimento', 'Descrição', 'Entidade', 'Categoria', 'Tipo', 'Valor', 'Valor Pago/Recebido', 'Status'],
            'rows'    => array_map(function ($r) {
                $tipo = in_array($r['status'], ['paga', 'recebida'], true) ? 'Realizado' : 'Pendente';
                return [
                    dataIsoParaBr($r['data_vencimento']),
                    $r['descricao'],
                    $r['entidade'],
                    $r['categoria'],
                    $tipo,
                    number_format((float)$r['valor'], 2, ',', '.'),
                    number_format((float)($r['valor_pago'] ?? 0), 2, ',', '.'),
                    $r['status'],
                ];
            }, $rows),
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
        foreach ($rows as &$r) {
            $saldoAcumulado += (float)$r['saldo_dia'];
            $r['saldo_acumulado'] = $saldoAcumulado;
        }

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
        ];
    }

    // ============================================================
    // EXPORTAÇÕES
    // ============================================================

    private function exportarCsv(string $tipo, array $dados): void
    {
        $slug = preg_replace('/[^a-z0-9]/', '_', strtolower($tipo));
        CsvExporter::download("relatorio_$slug", $dados['headers'], $dados['rows']);
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

        $html .= '</tbody></table></body></html>';

        return $html;
    }
}