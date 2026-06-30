<?php
/**
 * CnaeServicoController - Tipos de Serviços por CNAE
 *
 * Cadastro dos serviços que a empresa oferece/toma, classificados
 * por CNAE fiscal. CRUD completo (criar/editar/excluir/toggle ativo).
 */

declare(strict_types=1);

final class CnaeServicoController
{
    /**
     * Lista todos os serviços da empresa atual, opcionalmente filtrados por CNAE.
     */
    public function listar(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];
        $db = Database::getConnection();

        $cnae       = trim($_GET['cnae'] ?? '');
        $categoria  = trim($_GET['categoria'] ?? '');
        $apenasAtivos = ($_GET['apenas_ativos'] ?? '1') !== '0';

        $sql = '
            SELECT id, cnae, codigo_servico, descricao, categoria, ativo,
                   DATE_FORMAT(created_at, "%Y-%m-%d %H:%i") AS criado_em
            FROM cnae_servicos
            WHERE empresa_id = ?
        ';
        $params = [$empresaId];

        if ($cnae !== '') {
            $sql .= ' AND cnae = ?';
            $params[] = $cnae;
        }
        if ($categoria !== '' && in_array($categoria, ['telecom', 'ti', 'dados', 'info'], true)) {
            $sql .= ' AND categoria = ?';
            $params[] = $categoria;
        }
        if ($apenasAtivos) {
            $sql .= ' AND ativo = 1';
        }

        $sql .= ' ORDER BY cnae, codigo_servico';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        layout('CNAE — Tipos de Serviços', 'cnae/listar.php', [
            'servicos'      => $servicos,
            'cnae'          => $cnae,
            'categoria'     => $categoria,
            'apenasAtivos'  => $apenasAtivos,
            'total'         => count($servicos),
        ]);
    }

    /**
     * Form de criar/editar serviço.
     * GET ?id=N para editar; sem id para criar novo.
     */
    public function form(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];
        $db = Database::getConnection();
        $id = (int)($_GET['id'] ?? 0);

        $servico = [
            'id'             => 0,
            'cnae'           => '',
            'codigo_servico' => '',
            'descricao'      => '',
            'categoria'      => 'telecom',
            'ativo'          => 1,
        ];

        if ($id > 0) {
            $stmt = $db->prepare('SELECT * FROM cnae_servicos WHERE id = ? AND empresa_id = ?');
            $stmt->execute([$id, $empresaId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                redirect('cnae_servicos_listar.php', 'erro', 'Serviço não encontrado.');
            }
            $servico = $row;
        }

        layout($id > 0 ? 'Editar Serviço CNAE' : 'Novo Serviço CNAE', 'cnae/form.php', [
            'servico' => $servico,
        ]);
    }

    /**
     * Salvar (POST) - criar novo ou atualizar existente.
     */
    public function salvar(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];
        $db = Database::getConnection();

        $id            = (int)($_POST['id'] ?? 0);
        $cnae          = trim((string)($_POST['cnae'] ?? ''));
        $codigoServico = trim((string)($_POST['codigo_servico'] ?? ''));
        $descricao     = trim((string)($_POST['descricao'] ?? ''));
        $categoria     = trim((string)($_POST['categoria'] ?? ''));
        $ativo         = isset($_POST['ativo']) ? 1 : 0;

        // Validação
        $erros = [];
        if (!preg_match('/^\d{2}\.\d{2}-\d-\d{2}$/', $cnae)) {
            $erros[] = 'CNAE inválido (formato esperado: XX.XX-X-XX).';
        }
        if (empty($descricao)) {
            $erros[] = 'Descrição é obrigatória.';
        } elseif (strlen($descricao) > 255) {
            $erros[] = 'Descrição muito longa (max 255 chars).';
        }
        if (!in_array($categoria, ['telecom', 'ti', 'dados', 'info'], true)) {
            $erros[] = 'Categoria inválida.';
        }
        if (!empty($erros)) {
            redirect('cnae_servico_form.php' . ($id ? '?id=' . $id : ''), 'erro', implode(' ', $erros));
        }

        if ($id > 0) {
            // UPDATE
            $stmt = $db->prepare('
                UPDATE cnae_servicos
                SET cnae = ?, codigo_servico = ?, descricao = ?, categoria = ?, ativo = ?
                WHERE id = ? AND empresa_id = ?
            ');
            $stmt->execute([$cnae, $codigoServico ?: null, $descricao, $categoria, $ativo, $id, $empresaId]);
            redirect('cnae_servicos_listar.php', 'ok', 'Serviço atualizado com sucesso.');
        } else {
            // INSERT
            $stmt = $db->prepare('
                INSERT INTO cnae_servicos (empresa_id, cnae, codigo_servico, descricao, categoria, ativo)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([$empresaId, $cnae, $codigoServico ?: null, $descricao, $categoria, $ativo]);
            redirect('cnae_servicos_listar.php', 'ok', 'Serviço criado com sucesso.');
        }
    }

    /**
     * Excluir (POST com acao=excluir) - hard delete.
     */
    public function excluir(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];
        $db = Database::getConnection();
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            redirect('cnae_servicos_listar.php', 'erro', 'ID inválido.');
        }

        $stmt = $db->prepare('DELETE FROM cnae_servicos WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);

        if ($stmt->rowCount() > 0) {
            redirect('cnae_servicos_listar.php', 'ok', 'Serviço excluído.');
        } else {
            redirect('cnae_servicos_listar.php', 'erro', 'Serviço não encontrado ou sem permissão.');
        }
    }

    /**
     * Toggle ativo/inativo (POST com acao=toggle).
     */
    public function toggleAtivo(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];
        $db = Database::getConnection();
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            redirect('cnae_servicos_listar.php', 'erro', 'ID inválido.');
        }

        $stmt = $db->prepare('UPDATE cnae_servicos SET ativo = NOT ativo WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);

        if ($stmt->rowCount() > 0) {
            redirect('cnae_servicos_listar.php', 'ok', 'Status alterado.');
        } else {
            redirect('cnae_servicos_listar.php', 'erro', 'Serviço não encontrado.');
        }
    }
}
