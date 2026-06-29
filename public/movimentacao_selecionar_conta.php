<?php
/**
 * Tela intermediária: seleciona em qual conta lançar uma movimentação manual.
 *
 * Por que existe: o MovimentacoesController::form() exige conta_id. Quando o
 * usuário clica em "Lançamento Manual" no dropdown da lista de contas
 * bancárias, ainda não há uma conta específica — ele escolhe aqui.
 */

require __DIR__ . '/bootstrap.php';
Auth::require();

$empresaId = Auth::user()['empresa_id'];
$db = Database::getConnection();

$stmt = $db->prepare('
    SELECT id, descricao, tipo, banco, agencia, numero_conta, digito, titular,
           saldo_inicial, ativo
    FROM contas_bancarias
    WHERE empresa_id = ?
    ORDER BY ativo DESC, descricao
');
$stmt->execute([$empresaId]);
$contas = $stmt->fetchAll();

// Calcular saldo atual de cada uma
foreach ($contas as &$c) {
    $c['saldo_atual'] = ContasBancariasController::calcularSaldo((int)$c['id']);
    $c['qtd_movs'] = (int)$db->query(
        "SELECT COUNT(*) FROM movimentacoes_bancarias WHERE conta_bancaria_id = " . (int)$c['id']
    )->fetchColumn();
}
unset($c);

layout('Lançamento Manual - Selecionar Conta', 'movimentacoes/selecionar_conta.php', [
    'contas' => $contas,
]);