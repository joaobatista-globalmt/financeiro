<?php
/**
 * CnaeServicoController - Tipos de Serviços por CNAE
 *
 * Cadastro dos serviços que a empresa oferece/toma, classificados
 * por CNAE fiscal. Usado futuramente pra NF-e e relatórios.
 *
 * Rotas:
 *   GET  /cnae_servicos_listar.php  → Lista serviços (filtro ?cnae=)
 */

declare(strict_types=1);

final class CnaeServicoController
{
    /**
     * Lista todos os serviços da empresa atual, opcionalmente filtrados por CNAE.
     * Suporta filtros via GET:
     *   ?cnae=61.10-8-03     → só esse CNAE
     *   ?categoria=telecom   → só essa categoria
     *   ?apenas_ativos=1     → só serviços ativos (default: 1)
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

        layout('CNAE — Tipos de Serviços', 'cnae/servicos_listar.php', [
            'servicos'      => $servicos,
            'cnae'          => $cnae,
            'categoria'     => $categoria,
            'apenasAtivos'  => $apenasAtivos,
            'total'         => count($servicos),
        ]);
    }
}
