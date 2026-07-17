<?php
/**
 * ClienteServicoController - CRUD de servicos contratados pelo cliente
 *
 * Gerencia os servicos mensais que cada cliente tem contratado.
 * Esses servicos serao usados na geracao de faturas mensais (Fase 1.2).
 *
 * Padrao segue ClientesController:
 *  - Auth::require() + Permissao::requer()
 *  - Multi-tenant via empresa_id
 *  - Layout centralizado em src/views/cliente_servicos/
 */

declare(strict_types=1);

final class ClienteServicoController
{
    /**
     * Lista os servicos contratados de um cliente especifico.
     * GET cliente_servico_index.php?cliente_id=N
     * Retorna JSON (chamado via AJAX pela aba "Servicos" do cliente).
     */
    public function index(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];
        $clienteId = (int)($_GET['cliente_id'] ?? 0);
        if ($clienteId <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['erro' => 'cliente_id obrigatorio']);
            return;
        }
        $db = Database::getConnection();

        // Verifica que o cliente pertence a empresa
        $stmt = $db->prepare('SELECT id, razao_social FROM clientes WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$clienteId, $empresaId]);
        $cliente = $stmt->fetch();
        if (!$cliente) {
            header('Content-Type: application/json');
            echo json_encode(['erro' => 'Cliente nao encontrado ou sem permissao']);
            return;
        }

        // Lista servicos do cliente + dados do CNAE fiscal (para NFSe futura)
        $stmt = $db->prepare('
            SELECT cs.*,
                   s.codigo   AS servico_codigo,
                   s.descricao AS servico_descricao,
                   cs.cnae_servico_id,
                   c.cnae              AS cnae_codigo,
                   c.codigo_servico    AS cnae_codigo_servico,
                   c.descricao         AS cnae_descricao,
                   c.categoria         AS cnae_categoria,
                   c.nbs               AS cnae_nbs,
                   c.lc116_item        AS cnae_lc116,
                   c.regime_ibs        AS cnae_regime_ibs,
                   c.local_operacao    AS cnae_local_operacao
            FROM cliente_servicos cs
            LEFT JOIN servicos      s ON s.id = cs.servico_id
            LEFT JOIN cnae_servicos c ON c.id = cs.cnae_servico_id
            WHERE cs.empresa_id = ? AND cs.cliente_id = ?
            ORDER BY cs.ativo DESC, cs.data_inicio DESC, cs.id DESC
        ');
        $stmt->execute([$empresaId, $clienteId]);
        $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode([
            'cliente'  => $cliente,
            'servicos' => $servicos,
            'qtd'      => count($servicos),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Formulario de criacao/edicao de um servico contratado.
     * GET cliente_servico_form.php?cliente_id=N&id=M (id=0 = novo)
     */
    public function form(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];
        $clienteId = (int)($_GET['cliente_id'] ?? 0);
        $id         = (int)($_GET['id'] ?? 0);
        if ($clienteId <= 0) {
            header('Location: clientes.php');
            exit;
        }
        $db = Database::getConnection();

        // Carrega cliente (para breadcrumb / seguranca)
        $stmt = $db->prepare('SELECT id, razao_social FROM clientes WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$clienteId, $empresaId]);
        $cliente = $stmt->fetch();
        if (!$cliente) {
            header('Location: clientes.php');
            exit;
        }

        // Carrega servico (se edicao)
        $servico = null;
        if ($id > 0) {
            $stmt = $db->prepare('SELECT * FROM cliente_servicos WHERE id = ? AND empresa_id = ? AND cliente_id = ?');
            $stmt->execute([$id, $empresaId, $clienteId]);
            $servico = $stmt->fetch();
            if (!$servico) {
                header('Location: cliente_form.php?id=' . $clienteId);
                exit;
            }
        }

        // Lista catalogo de CNAE / tipos de servico fiscal (para dropdown)
        // Tabela servicos (legada) foi descontinuada — cnae_servicos eh a fonte oficial (NFSe)
        $stmt = $db->prepare('
            SELECT id, cnae, codigo_servico, descricao, categoria, nbs, lc116_item
            FROM cnae_servicos
            WHERE empresa_id = ? AND ativo = 1
            ORDER BY cnae, codigo_servico
        ');
        $stmt->execute([$empresaId]);
        $catalogo = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Lista contas bancarias ativas (para dropdown de conta de recebimento do boleto)
        $stmt = $db->prepare('
            SELECT id, descricao, banco, agencia, numero_conta, digito, titular, tipo
            FROM contas_bancarias
            WHERE empresa_id = ? AND ativo = 1
            ORDER BY descricao
        ');
        $stmt->execute([$empresaId]);
        $contas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        layout(($id > 0 ? 'Editar' : 'Novo') . ' Servico - ' . $cliente['razao_social'],
               'cliente_servicos/form.php', [
            'cliente' => $cliente,
            'servico' => $servico,
            'catalogo'=> $catalogo,
            'contas'  => $contas,
        ]);
    }

    /**
     * Salva (insert/update) um servico contratado.
     * POST cliente_servico_salvar.php
     */
    public function salvar(): void
    {
        Auth::require();
        Permissao::requer('criar', 'clientes.php');
        $empresaId = Auth::user()['empresa_id'];
        $usuarioId = Auth::user()['id'];
        $clienteId = (int)($_POST['cliente_id'] ?? 0);
        $id         = (int)($_POST['id'] ?? 0);
        if ($clienteId <= 0) {
            header('Location: clientes.php');
            exit;
        }

        // Coleta e valida campos
        $descricao   = trim((string)($_POST['descricao'] ?? ''));
        $valorStr    = str_replace(['.', ','], ['', '.'], (string)($_POST['valor_mensal'] ?? '0'));
        $valorMensal = (float)$valorStr;
        $dataInicio  = $_POST['data_inicio'] ?? date('Y-m-d');
        $dataFim     = $_POST['data_fim'] ?? null;
        $dataFim     = ($dataFim === '' ? null : $dataFim);
        $diaVenc     = $_POST['dia_vencimento'] ?? null;
        $diaVenc     = ($diaVenc === '' ? null : (int)$diaVenc);
        $tipoVenc    = $_POST['tipo_vencimento'] ?? null;
        $tipoVenc    = ($tipoVenc === '' ? null : $tipoVenc);
        $ativo       = isset($_POST['ativo']) ? 1 : 0;
        $servicoId     = (int)($_POST['servico_id'] ?? 0);
        $servicoId     = ($servicoId <= 0 ? null : $servicoId);
        $cnaeServicoId = (int)($_POST['cnae_servico_id'] ?? 0);
        $cnaeServicoId = ($cnaeServicoId <= 0 ? null : $cnaeServicoId);
        $observ        = trim((string)($_POST['observacoes'] ?? ''));

        // Campos de boleto (Fase 1)
        $tipoCobranca       = $_POST['tipo_cobranca'] ?? null;
        $tipoCobranca       = ($tipoCobranca === '' ? null : $tipoCobranca);
        $multaStr           = str_replace(['.', ','], ['', '.'], (string)($_POST['multa_percentual'] ?? ''));
        $multaPercentual    = ($multaStr === '' ? null : (float)$multaStr);
        $jurosStr           = str_replace(['.', ','], ['', '.'], (string)($_POST['juros_mensal_percentual'] ?? ''));
        $jurosMensalPerc    = ($jurosStr === '' ? null : (float)$jurosStr);
        $instrLinha1        = trim((string)($_POST['instrucoes_boleto_linha1'] ?? ''));
        $instrLinha1        = ($instrLinha1 === '' ? null : $instrLinha1);
        $instrLinha2        = trim((string)($_POST['instrucoes_boleto_linha2'] ?? ''));
        $instrLinha2        = ($instrLinha2 === '' ? null : $instrLinha2);
        $contaBancariaId    = (int)($_POST['conta_bancaria_id'] ?? 0);
        $contaBancariaId    = ($contaBancariaId <= 0 ? null : $contaBancariaId);
        $numeroContrato     = trim((string)($_POST['numero_contrato'] ?? ''));
        $numeroContrato     = ($numeroContrato === '' ? null : $numeroContrato);
        $tipoBoleto         = $_POST['tipo_boleto'] ?? 'sem_registro';
        $tipoBoleto         = ($tipoBoleto === '' ? 'sem_registro' : $tipoBoleto);
        // Validar ENUMs (defesa em profundidade)
        if ($tipoCobranca !== null && !in_array($tipoCobranca, ['mensal_fixa', 'medicao'], true)) {
            $tipoCobranca = null;
        }
        if (!in_array($tipoBoleto, ['sem_registro', 'com_registro'], true)) {
            $tipoBoleto = 'sem_registro';
        }

        if ($descricao === '' || $valorMensal <= 0 || !$dataInicio) {
            $_SESSION['flash_erro'] = 'Preencha descricao, valor mensal (>0) e data de inicio.';
            $redir = 'cliente_servico_form.php?cliente_id=' . $clienteId . ($id > 0 ? '&id=' . $id : '');
            header('Location: ' . $redir);
            exit;
        }

        $db = Database::getConnection();

        if ($id > 0) {
            // UPDATE
            $stmt = $db->prepare('
                UPDATE cliente_servicos SET
                    servico_id = ?, cnae_servico_id = ?, descricao = ?, valor_mensal = ?,
                    data_inicio = ?, data_fim = ?,
                    dia_vencimento = ?, tipo_vencimento = ?,
                    tipo_cobranca = ?, multa_percentual = ?, juros_mensal_percentual = ?,
                    instrucoes_boleto_linha1 = ?, instrucoes_boleto_linha2 = ?,
                    conta_bancaria_id = ?, numero_contrato = ?, tipo_boleto = ?,
                    ativo = ?, observacoes = ?
                WHERE id = ? AND empresa_id = ? AND cliente_id = ?
            ');
            $stmt->execute([
                $servicoId, $cnaeServicoId, $descricao, $valorMensal,
                $dataInicio, $dataFim,
                $diaVenc, $tipoVenc,
                $tipoCobranca, $multaPercentual, $jurosMensalPerc,
                $instrLinha1, $instrLinha2,
                $contaBancariaId, $numeroContrato, $tipoBoleto,
                $ativo, $observ,
                $id, $empresaId, $clienteId
            ]);
            $_SESSION['flash_ok'] = 'Servico atualizado com sucesso.';
        } else {
            // INSERT
            $stmt = $db->prepare('
                INSERT INTO cliente_servicos
                    (empresa_id, cliente_id, servico_id, cnae_servico_id, descricao, valor_mensal,
                     data_inicio, data_fim, dia_vencimento, tipo_vencimento,
                     tipo_cobranca, multa_percentual, juros_mensal_percentual,
                     instrucoes_boleto_linha1, instrucoes_boleto_linha2,
                     conta_bancaria_id, numero_contrato, tipo_boleto,
                     ativo, observacoes, usuario_criacao_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $empresaId, $clienteId, $servicoId, $cnaeServicoId, $descricao, $valorMensal,
                $dataInicio, $dataFim, $diaVenc, $tipoVenc,
                $tipoCobranca, $multaPercentual, $jurosMensalPerc,
                $instrLinha1, $instrLinha2,
                $contaBancariaId, $numeroContrato, $tipoBoleto,
                $ativo, $observ, $usuarioId
            ]);
            $_SESSION['flash_ok'] = 'Servico contratado cadastrado com sucesso.';
        }

        header('Location: cliente_form.php?id=' . $clienteId . '#servicos');
        exit;
    }

    /**
     * Acao (excluir / ativar-desativar).
     * POST cliente_servico_acao.php
     */
    public function acao(): void
    {
        Auth::require();
        Permissao::requer('excluir', 'clientes.php');
        $empresaId = Auth::user()['empresa_id'];
        $clienteId = (int)($_POST['cliente_id'] ?? $_GET['cliente_id'] ?? 0);
        $id        = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $acao      = $_POST['acao'] ?? $_GET['acao'] ?? '';
        if ($id <= 0 || $clienteId <= 0) {
            header('Location: clientes.php');
            exit;
        }

        $db = Database::getConnection();

        if ($acao === 'excluir') {
            $stmt = $db->prepare('DELETE FROM cliente_servicos WHERE id = ? AND empresa_id = ? AND cliente_id = ?');
            $stmt->execute([$id, $empresaId, $clienteId]);
            $_SESSION['flash_ok'] = 'Servico excluido.';
        } elseif ($acao === 'toggle_ativo') {
            $stmt = $db->prepare('UPDATE cliente_servicos SET ativo = 1 - ativo WHERE id = ? AND empresa_id = ? AND cliente_id = ?');
            $stmt->execute([$id, $empresaId, $clienteId]);
            $_SESSION['flash_ok'] = 'Status do servico alterado.';
        }

        header('Location: cliente_form.php?id=' . $clienteId . '#servicos');
        exit;
    }
}
