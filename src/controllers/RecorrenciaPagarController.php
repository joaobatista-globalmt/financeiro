<?php
/**
 * RecorrenciaPagarController - Templates de contas a pagar recorrentes
 *
 * Ex: aluguel mensal, internet, contador — geram uma conta_pagar todo mês.
 */

declare(strict_types=1);

final class RecorrenciaPagarController
{
    public function index(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];

        $db = Database::getConnection();
        $stmt = $db->prepare('
            SELECT r.*, f.razao_social AS fornecedor_nome, cat.nome AS categoria_nome
            FROM contas_pagar_recorrencia r
            JOIN fornecedores f ON f.id = r.fornecedor_id
            JOIN categorias cat ON cat.id = r.categoria_id
            WHERE r.empresa_id = ?
            ORDER BY r.ativa DESC, r.descricao
        ');
        $stmt->execute([$empresaId]);
        $recorrencias = $stmt->fetchAll();

        layout('Recorrências (Pagar)', 'contas_pagar/recorrencia.php', [
            'recorrencias' => $recorrencias,
            'tipo'         => 'pagar',
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
            $stmt = $db->prepare('SELECT * FROM contas_pagar_recorrencia WHERE id = ? AND empresa_id = ?');
            $stmt->execute([$id, $empresaId]);
            $rec = $stmt->fetch();

            if (!$rec) {
                Flash::set('erro', 'Recorrência não encontrada.');
                redirect('recorrencia_pagar.php');
            }
        }

        $fornecedores = (new FornecedorController)->index;
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id, razao_social FROM fornecedores WHERE empresa_id = ? AND ativo = 1 ORDER BY razao_social');
        $stmt->execute([$empresaId]);
        $fornecedores = $stmt->fetchAll();

        $stmtC = $db->prepare('SELECT id, nome FROM categorias WHERE empresa_id = ? AND ativo = 1 AND tipo IN ("despesa","ambos") ORDER BY nome');
        $stmtC->execute([$empresaId]);
        $categorias = $stmtC->fetchAll();

        layout($rec ? 'Editar Recorrência' : 'Nova Recorrência', 'contas_pagar/recorrencia_form.php', [
            'rec'          => $rec,
            'fornecedores' => $fornecedores,
            'categorias'   => $categorias,
            'tipo'         => 'pagar',
        ]);
    }

    public function salvar(): void
    {
        Auth::require();
        Permissao::requer('criar', 'recorrencia_pagar.php');

        $empresaId = Auth::user()['empresa_id'];
        $id = (int)($_POST['id'] ?? 0);

        if (empty(trim($_POST['descricao'] ?? ''))) {
            Flash::set('erro', 'Descrição é obrigatória.');
            redirect('recorrencia_pagar.php');
        }
        $dia = (int)($_POST['dia_vencimento'] ?? 0);
        if ($dia < 1 || $dia > 31) {
            Flash::set('erro', 'Dia de vencimento deve ser entre 1 e 31.');
            redirect('recorrencia_pagar.php');
        }

        $dados = [
            'fornecedor_id'    => (int)$_POST['fornecedor_id'],
            'categoria_id'     => (int)$_POST['categoria_id'],
            'descricao'        => trim($_POST['descricao']),
            'valor'            => (float)str_replace(',', '.', $_POST['valor']),
            'dia_vencimento'   => $dia,
            'forma_pagamento'  => $_POST['forma_pagamento'] ?? 'boleto',
            'data_inicio'      => $_POST['data_inicio'] ?? date('Y-m-d'),
            'data_fim'         => $_POST['data_fim'] ?? null ?: null,
            'observacoes'      => trim($_POST['observacoes'] ?? '') ?: null,
            'ativa'            => isset($_POST['ativa']) ? 1 : 1,
        ];

        $db = Database::getConnection();

        try {
            if ($id > 0) {
                $dados['ativa'] = isset($_POST['ativa']) ? 1 : 0;
                $stmt = $db->prepare('
                    UPDATE contas_pagar_recorrencia SET
                        fornecedor_id=:fornecedor_id, categoria_id=:categoria_id, descricao=:descricao,
                        valor=:valor, dia_vencimento=:dia_vencimento, forma_pagamento=:forma_pagamento,
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
                // Próxima geração = primeiro dia de vencimento >= hoje
                $dados['proxima_geracao'] = self::calcularProximaGeracao($dados['data_inicio'], $dia);
                $stmt = $db->prepare('
                    INSERT INTO contas_pagar_recorrencia
                        (empresa_id, fornecedor_id, categoria_id, descricao, valor, dia_vencimento,
                         forma_pagamento, ativa, data_inicio, data_fim, proxima_geracao, observacoes, usuario_criacao_id)
                    VALUES
                        (:empresa_id, :fornecedor_id, :categoria_id, :descricao, :valor, :dia_vencimento,
                         :forma_pagamento, :ativa, :data_inicio, :data_fim, :proxima_geracao, :observacoes, :usuario_criacao_id)
                ');
                $stmt->execute($dados);
                Flash::set('sucesso', 'Recorrência criada. Próxima geração em ' . $dados['proxima_geracao']);
            }
        } catch (PDOException $e) {
            error_log('[RecorrenciaPagar] Erro: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao salvar recorrência.');
        }

        redirect('recorrencia_pagar.php');
    }

    public function gerar(): void
    {
        Auth::require();
        Permissao::requer('criar', 'recorrencia_pagar.php');

        $empresaId = Auth::user()['empresa_id'];
        $hoje = date('Y-m-d');
        $db = Database::getConnection();

        // Buscar recorrências ativas com data de geração <= hoje
        $stmt = $db->prepare('
            SELECT * FROM contas_pagar_recorrencia
            WHERE empresa_id = ? AND ativa = 1 AND proxima_geracao <= ?
              AND (data_fim IS NULL OR data_fim >= ?)
        ');
        $stmt->execute([$empresaId, $hoje, $hoje]);
        $recorrencias = $stmt->fetchAll();

        $geradas = 0;

        try {
            $db->beginTransaction();

            foreach ($recorrencias as $rec) {
                // Anti-duplicação: verifica se já existe conta_pagar gerada neste mês
                $stmtCheck = $db->prepare('
                    SELECT COUNT(*) AS total FROM contas_pagar
                    WHERE empresa_id = ? AND fornecedor_id = ? AND descricao = ?
                      AND YEAR(data_vencimento) = YEAR(?) AND MONTH(data_vencimento) = MONTH(?)
                ');
                $stmtCheck->execute([$empresaId, $rec['fornecedor_id'], $rec['descricao'], $rec['proxima_geracao'], $rec['proxima_geracao']]);
                $jaExiste = $stmtCheck->fetch()['total'] > 0;

                if ($jaExiste) {
                    // Avança a próxima geração pra próximo mês
                    $proxima = self::calcularProximaGeracao($rec['proxima_geracao'], $rec['dia_vencimento']);
                    $stmtU = $db->prepare('UPDATE contas_pagar_recorrencia SET proxima_geracao = ? WHERE id = ?');
                    $stmtU->execute([$proxima, $rec['id']]);
                    continue;
                }

                // Cria a conta
                $stmtC = $db->prepare('
                    INSERT INTO contas_pagar
                        (empresa_id, fornecedor_id, categoria_id, descricao, valor,
                         data_emissao, data_vencimento, forma_pagamento, status, usuario_criacao_id)
                    VALUES
                        (:empresa_id, :fornecedor_id, :categoria_id, :descricao, :valor,
                         :data_emissao, :data_vencimento, :forma_pagamento, "pendente", :usuario_id)
                ');
                $stmtC->execute([
                    'empresa_id'    => $empresaId,
                    'fornecedor_id' => $rec['fornecedor_id'],
                    'categoria_id'  => $rec['categoria_id'],
                    'descricao'     => $rec['descricao'],
                    'valor'         => $rec['valor'],
                    'data_emissao'  => $hoje,
                    'data_vencimento' => $rec['proxima_geracao'],
                    'forma_pagamento' => $rec['forma_pagamento'],
                    'usuario_id'    => Auth::user()['id'],
                ]);

                // Avança para o próximo mês
                $proxima = self::calcularProximaGeracao($rec['proxima_geracao'], $rec['dia_vencimento']);
                $stmtU = $db->prepare('UPDATE contas_pagar_recorrencia SET proxima_geracao = ? WHERE id = ?');
                $stmtU->execute([$proxima, $rec['id']]);

                $geradas++;
            }

            $db->commit();
            Flash::set('sucesso', "$geradas conta(s) gerada(s) a partir das recorrências.");
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('[RecorrenciaPagar] Erro ao gerar: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao gerar contas recorrentes.');
        }

        redirect('recorrencia_pagar.php');
    }

    public function acao(): void
    {
        Auth::require();
        Permissao::requer('excluir', 'recorrencia_pagar.php');

        $id = (int)($_POST['id'] ?? 0);
        $acao = $_POST['acao'] ?? '';
        $empresaId = Auth::user()['empresa_id'];

        if ($id <= 0) {
            Flash::set('erro', 'ID inválido.');
            redirect('recorrencia_pagar.php');
        }

        $db = Database::getConnection();

        try {
            if ($acao === 'ativar') {
                $stmt = $db->prepare('UPDATE contas_pagar_recorrencia SET ativa = 1 WHERE id = ? AND empresa_id = ?');
                $stmt->execute([$id, $empresaId]);
                Flash::set('sucesso', 'Recorrência ativada.');
            } elseif ($acao === 'desativar') {
                $stmt = $db->prepare('UPDATE contas_pagar_recorrencia SET ativa = 0 WHERE id = ? AND empresa_id = ?');
                $stmt->execute([$id, $empresaId]);
                Flash::set('sucesso', 'Recorrência desativada.');
            } elseif ($acao === 'excluir') {
                $stmt = $db->prepare('DELETE FROM contas_pagar_recorrencia WHERE id = ? AND empresa_id = ?');
                $stmt->execute([$id, $empresaId]);
                Flash::set('sucesso', 'Recorrência excluída.');
            }
        } catch (PDOException $e) {
            error_log('[RecorrenciaPagar] Erro: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao executar ação.');
        }

        redirect('recorrencia_pagar.php');
    }

    public static function calcularProximaGeracao(string $dataBase, int $dia): string
    {
        $base = new DateTime($dataBase);
        $hoje = new DateTime();

        // Avança para o próximo mês se a data base já passou
        while ($base < $hoje) {
            $base->modify('+1 month');
        }

        // Ajusta o dia (mês pode ter menos dias)
        $ano = (int)$base->format('Y');
        $mes = (int)$base->format('m');
        $ultimoDia = (int)(new DateTime("$ano-$mes-01"))->format('t');
        $diaEfetivo = min($dia, $ultimoDia);

        return (new DateTime("$ano-$mes-" . str_pad((string)$diaEfetivo, 2, '0', STR_PAD_LEFT)))->format('Y-m-d');
    }
}