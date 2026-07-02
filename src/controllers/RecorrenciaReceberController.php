<?php
/**
 * RecorrenciaReceberController - Templates de contas a receber recorrentes
 *
 * Espelho do RecorrenciaPagarController, mas para clientes/receitas.
 */

declare(strict_types=1);

final class RecorrenciaReceberController
{
    public function index(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];

        $db = Database::getConnection();
        $stmt = $db->prepare('
            SELECT r.*, c.razao_social AS cliente_nome, cat.nome AS categoria_nome
            FROM contas_receber_recorrencia r
            JOIN clientes c ON c.id = r.cliente_id
            JOIN categorias cat ON cat.id = r.categoria_id
            WHERE r.empresa_id = ?
            ORDER BY r.ativa DESC, r.descricao
        ');
        $stmt->execute([$empresaId]);
        $recorrencias = $stmt->fetchAll();

        layout('Recorrências (Receber)', 'contas_receber/recorrencia.php', [
            'recorrencias' => $recorrencias,
            'tipo'         => 'receber',
        ]);
    }

    public function form(): void
    {
        Auth::require();
        $id = (int)($_GET['id'] ?? 0);
        $rec = null;
        $empresaId = Auth::user()['empresa_id'];

        if ($id > 0) {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT * FROM contas_receber_recorrencia WHERE id = ? AND empresa_id = ?');
            $stmt->execute([$id, $empresaId]);
            $rec = $stmt->fetch();

            if (!$rec) {
                Flash::set('erro', 'Recorrência não encontrada.');
                redirect('recorrencia_receber.php');
            }
        }

        $db = Database::getConnection();
        $stmtC = $db->prepare('SELECT id, razao_social FROM clientes WHERE empresa_id = ? AND ativo = 1 ORDER BY razao_social');
        $stmtC->execute([$empresaId]);
        $clientes = $stmtC->fetchAll();

        $stmtCat = $db->prepare('SELECT id, nome FROM categorias WHERE empresa_id = ? AND ativo = 1 AND tipo IN ("receita","ambos") ORDER BY nome');
        $stmtCat->execute([$empresaId]);
        $categorias = $stmtCat->fetchAll();

        layout($rec ? 'Editar Recorrência' : 'Nova Recorrência', 'contas_receber/recorrencia_form.php', [
            'rec'        => $rec,
            'clientes'   => $clientes,
            'categorias' => $categorias,
            'tipo'       => 'receber',
        ]);
    }

    public function salvar(): void
    {
        Auth::require();
        Permissao::requer('criar', 'recorrencia_receber.php');

        $empresaId = Auth::user()['empresa_id'];
        $id = (int)($_POST['id'] ?? 0);

        if (empty(trim($_POST['descricao'] ?? ''))) {
            Flash::set('erro', 'Descrição é obrigatória.');
            redirect('recorrencia_receber.php');
        }
        $dia = (int)($_POST['dia_vencimento'] ?? 0);
        if ($dia < 1 || $dia > 31) {
            Flash::set('erro', 'Dia de vencimento deve ser entre 1 e 31.');
            redirect('recorrencia_receber.php');
        }

        $dados = [
            'cliente_id'        => (int)$_POST['cliente_id'],
            'categoria_id'      => (int)$_POST['categoria_id'],
            'descricao'         => trim($_POST['descricao']),
            'valor'             => (float)str_replace(',', '.', $_POST['valor']),
            'dia_vencimento'    => $dia,
            'forma_recebimento' => $_POST['forma_recebimento'] ?? 'boleto',
            'data_inicio'       => $_POST['data_inicio'] ?? date('Y-m-d'),
            'data_fim'          => $_POST['data_fim'] ?? null ?: null,
            'observacoes'       => trim($_POST['observacoes'] ?? '') ?: null,
            'ativa'             => 1,
        ];

        $db = Database::getConnection();

        try {
            if ($id > 0) {
                $dados['ativa'] = isset($_POST['ativa']) ? 1 : 0;
                $stmt = $db->prepare('
                    UPDATE contas_receber_recorrencia SET
                        cliente_id=:cliente_id, categoria_id=:categoria_id, descricao=:descricao,
                        valor=:valor, dia_vencimento=:dia_vencimento, forma_recebimento=:forma_recebimento,
                        data_inicio=:data_inicio, data_fim=:data_fim, observacoes=:observacoes,
                        ativa=:ativa
                    WHERE id=:id AND empresa_id=:empresa_id
                ');
                $dados['id'] = $id;
                $dados['empresa_id'] = $empresaId;
                $stmt->execute($dados);
                Flash::set('sucesso', 'Recorrência atualizada.');
            } else {
                $dados['empresa_id'] = $empresaId;
                $dados['usuario_criacao_id'] = Auth::user()['id'];
                $dados['proxima_geracao'] = RecorrenciaPagarController::calcularProximaGeracao($dados['data_inicio'], $dia);
                $stmt = $db->prepare('
                    INSERT INTO contas_receber_recorrencia
                        (empresa_id, cliente_id, categoria_id, descricao, valor, dia_vencimento,
                         forma_recebimento, ativa, data_inicio, data_fim, proxima_geracao, observacoes, usuario_criacao_id)
                    VALUES
                        (:empresa_id, :cliente_id, :categoria_id, :descricao, :valor, :dia_vencimento,
                         :forma_recebimento, :ativa, :data_inicio, :data_fim, :proxima_geracao, :observacoes, :usuario_criacao_id)
                ');
                $stmt->execute($dados);
                Flash::set('sucesso', 'Recorrência criada.');
            }
        } catch (PDOException $e) {
            error_log('[RecorrenciaReceber] Erro: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao salvar recorrência.');
        }

        redirect('recorrencia_receber.php');
    }

    public function gerar(): void
    {
        Auth::require();
        Permissao::requer('criar', 'recorrencia_receber.php');

        $empresaId = Auth::user()['empresa_id'];
        $hoje = date('Y-m-d');
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT * FROM contas_receber_recorrencia
            WHERE empresa_id = ? AND ativa = 1 AND proxima_geracao <= ?
              AND (data_fim IS NULL OR data_fim >= ?)
        ');
        $stmt->execute([$empresaId, $hoje, $hoje]);
        $recorrencias = $stmt->fetchAll();

        $geradas = 0;

        try {
            $db->beginTransaction();

            foreach ($recorrencias as $rec) {
                $stmtCheck = $db->prepare('
                    SELECT COUNT(*) AS total FROM contas_receber
                    WHERE empresa_id = ? AND cliente_id = ? AND descricao = ?
                      AND YEAR(data_vencimento) = YEAR(?) AND MONTH(data_vencimento) = MONTH(?)
                ');
                $stmtCheck->execute([$empresaId, $rec['cliente_id'], $rec['descricao'], $rec['proxima_geracao'], $rec['proxima_geracao']]);
                if ($stmtCheck->fetch()['total'] > 0) {
                    $proxima = RecorrenciaPagarController::calcularProximaGeracao($rec['proxima_geracao'], $rec['dia_vencimento']);
                    $stmtU = $db->prepare('UPDATE contas_receber_recorrencia SET proxima_geracao = ? WHERE id = ?');
                    $stmtU->execute([$proxima, $rec['id']]);
                    continue;
                }

                $stmtC = $db->prepare('
                    INSERT INTO contas_receber
                        (empresa_id, cliente_id, categoria_id, descricao, valor,
                         data_emissao, data_vencimento, forma_recebimento, status, usuario_criacao_id)
                    VALUES
                        (:empresa_id, :cliente_id, :categoria_id, :descricao, :valor,
                         :data_emissao, :data_vencimento, :forma_recebimento, "pendente", :usuario_id)
                ');
                $stmtC->execute([
                    'empresa_id'        => $empresaId,
                    'cliente_id'        => $rec['cliente_id'],
                    'categoria_id'      => $rec['categoria_id'],
                    'descricao'         => $rec['descricao'],
                    'valor'             => $rec['valor'],
                    'data_emissao'      => $hoje,
                    'data_vencimento'   => $rec['proxima_geracao'],
                    'forma_recebimento' => $rec['forma_recebimento'],
                    'usuario_id'        => Auth::user()['id'],
                ]);

                $proxima = RecorrenciaPagarController::calcularProximaGeracao($rec['proxima_geracao'], $rec['dia_vencimento']);
                $stmtU = $db->prepare('UPDATE contas_receber_recorrencia SET proxima_geracao = ? WHERE id = ?');
                $stmtU->execute([$proxima, $rec['id']]);

                $geradas++;
            }

            $db->commit();
            Flash::set('sucesso', "$geradas conta(s) gerada(s).");
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('[RecorrenciaReceber] Erro: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao gerar contas.');
        }

        redirect('recorrencia_receber.php');
    }

    public function acao(): void
    {
        Auth::require();
        Permissao::requer('excluir', 'recorrencia_receber.php');

        $id = (int)($_POST['id'] ?? 0);
        $acao = $_POST['acao'] ?? '';
        $empresaId = Auth::user()['empresa_id'];

        if ($id <= 0) {
            Flash::set('erro', 'ID inválido.');
            redirect('recorrencia_receber.php');
        }

        $db = Database::getConnection();

        try {
            if ($acao === 'ativar') {
                $stmt = $db->prepare('UPDATE contas_receber_recorrencia SET ativa = 1 WHERE id = ? AND empresa_id = ?');
                $stmt->execute([$id, $empresaId]);
                Flash::set('sucesso', 'Recorrência ativada.');
            } elseif ($acao === 'desativar') {
                $stmt = $db->prepare('UPDATE contas_receber_recorrencia SET ativa = 0 WHERE id = ? AND empresa_id = ?');
                $stmt->execute([$id, $empresaId]);
                Flash::set('sucesso', 'Recorrência desativada.');
            } elseif ($acao === 'excluir') {
                $stmt = $db->prepare('DELETE FROM contas_receber_recorrencia WHERE id = ? AND empresa_id = ?');
                $stmt->execute([$id, $empresaId]);
                Flash::set('sucesso', 'Recorrência excluída.');
            }
        } catch (PDOException $e) {
            error_log('[RecorrenciaReceber] Erro: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao executar ação.');
        }

        redirect('recorrencia_receber.php');
    }
}