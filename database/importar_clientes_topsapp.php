<?php
/**
 * Importador de clientes do CSV TopsApp para empresa GLOBALMT TELECON
 *
 * Lê: /home/sistema/financeiro/database/clientes_topsapp.csv
 * Formato: CodCliente;Nome;Sexo;Email;Telefone;Plano;Valor;Vencimento;...
 *
 * Cria:
 *   - Categoria "Mensalidades Internet" (se não existir)
 *   - 1 cliente por CodCliente (deduplica por CodCliente + nome)
 *   - 1 conta_receber por linha do CSV
 *
 * Empresa alvo: GLOBALMT TELECON (id=3)
 *
 * Uso:
 *   php database/importar_clientes_topsapp.php
 */

declare(strict_types=1);

require __DIR__ . '/../src/lib/Helper.php';

$empresaId = 3; // GLOBALMT TELECON
$arquivoCsv = __DIR__ . '/clientes_topsapp.csv';

if (!file_exists($arquivoCsv)) {
    die("ERRO: arquivo CSV não encontrado em $arquivoCsv\n");
}

echo "=== Importador de Clientes TopsApp → GLOBALMT TELECON ===\n\n";

$db = Database::getConnection();

// PASSO 1: Garantir categoria "Mensalidades Internet" (receita)
$stmtCat = $db->prepare('SELECT id FROM categorias WHERE empresa_id = ? AND nome = ?');
$stmtCat->execute([$empresaId, 'Mensalidades Internet']);
$categoriaId = $stmtCat->fetchColumn();

if (!$categoriaId) {
    $db->prepare('
        INSERT INTO categorias (empresa_id, nome, tipo, cor, descricao, ativo)
        VALUES (?, ?, ?, ?, ?, 1)
    ')->execute([$empresaId, 'Mensalidades Internet', 'receita', '#16a34a', 'Mensalidades de internet importadas do TopsApp']);
    $categoriaId = (int)$db->lastInsertId();
    echo "✓ Categoria 'Mensalidades Internet' criada (id=$categoriaId)\n";
} else {
    echo "• Categoria 'Mensalidades Internet' já existe (id=$categoriaId)\n";
}

echo "\nLendo CSV: $arquivoCsv\n";
$conteudo = file_get_contents($arquivoCsv);
$linhas = array_filter(explode("\n", $conteudo), 'trim');
$cabecalho = str_getcsv(array_shift($linhas), ';');
echo "Cabeçalho: " . count($cabecalho) . " colunas\n\n";

$stats = [
    'clientes_criados' => 0,
    'clientes_atualizados' => 0,
    'clientes_ignorados' => 0,
    'contas_criadas' => 0,
    'erros' => 0,
];

$clientesCache = []; // cod_cliente => id

$usuarioId = 1; // joao.batista (admin)

foreach ($linhas as $i => $linha) {
    $linhaNum = $i + 2; // +2 porque header é linha 1
    $cols = str_getcsv($linha, ';');

    if (count($cols) < 15) {
        echo "  ⚠ Linha $linhaNum ignorada: poucas colunas\n";
        $stats['clientes_ignorados']++;
        continue;
    }

    $codCliente = trim($cols[0]);
    $nome       = trim($cols[1]);
    $sexo       = trim($cols[2]);
    $email      = trim($cols[3]);
    $telefone   = trim($cols[4]);
    $plano      = trim($cols[5]);
    $valor      = (float)str_replace(',', '.', $cols[6]);
    $diaVenc    = (int)$cols[7];
    $endereco   = trim($cols[8]);
    $numero     = trim($cols[9]);
    $bairro     = trim($cols[10]);
    $celular    = trim($cols[11]);
    $telefone2  = trim($cols[12]);
    $cidade     = trim($cols[13]);
    $dataCad    = trim($cols[14]);
    $grupo      = trim($cols[15]);

    if (empty($nome)) {
        $stats['clientes_ignorados']++;
        continue;
    }

    // Tipo pessoa: FISICA → F, JURIDICA → J, indefinido → J (default seguro p/ empresa)
    $tipoPessoa = ($grupo === 'FISICA') ? 'F' : 'J';

    // Telefone preferido: celular se tiver, senão telefone
    $telFinal = !empty($celular) ? $celular : (!empty($telefone2) ? $telefone2 : $telefone);

    // Email: pega o primeiro se tiver vários separados por ;
    $emailFinal = '';
    if (!empty($email)) {
        $parts = preg_split('/[;,]/', $email);
        $emailFinal = trim($parts[0]);
    }

    // CPF/CNPJ: não temos, deixa vazio (o CSV não tem)
    $cpfCnpj = '';

    // Endereço completo
    $endCompleto = $endereco;
    if (!empty($numero) && $numero !== '0') $endCompleto .= ', ' . $numero;
    if (!empty($bairro) && $bairro !== 'ZONA RURAL') $endCompleto .= ' - ' . $bairro;

    // Observações (campo 17, se existir)
    $obs = isset($cols[17]) ? trim($cols[17]) : '';

    try {
        $db->beginTransaction();

        // === PASSO A: Criar/atualizar cliente (deduplicado por CodCliente) ===
        if (!isset($clientesCache[$codCliente])) {
            // Procura cliente existente por cod_cliente armazenado em observacoes OU por email (se houver)
            $searchObs = '%[CodCliente:' . $codCliente . ']%';
            if (!empty($emailFinal)) {
                $stmtFind = $db->prepare('
                    SELECT id FROM clientes
                    WHERE empresa_id = :empresa_id
                      AND (observacoes LIKE :search_obs OR email = :email)
                    LIMIT 1
                ');
                $stmtFind->execute([
                    'empresa_id' => $empresaId,
                    'search_obs' => $searchObs,
                    'email'      => $emailFinal,
                ]);
            } else {
                $stmtFind = $db->prepare('
                    SELECT id FROM clientes
                    WHERE empresa_id = :empresa_id AND observacoes LIKE :search_obs
                    LIMIT 1
                ');
                $stmtFind->execute([
                    'empresa_id' => $empresaId,
                    'search_obs' => $searchObs,
                ]);
            }
            $clienteExistenteId = $stmtFind->fetchColumn();

            if ($clienteExistenteId) {
                // Atualiza
                $stmtUp = $db->prepare('
                    UPDATE clientes SET
                        razao_social=:nome, nome_fantasia=:fantasia, cpf_cnpj=:cpf_cnpj,
                        tipo_pessoa=:tipo_pessoa, endereco=:endereco, cidade=:cidade,
                        telefone=:telefone, email=:email, contato=:contato,
                        observacoes=:obs
                    WHERE id=:id
                ');
                $stmtUp->execute([
                    'nome' => $nome,
                    'fantasia' => null,
                    'cpf_cnpj' => $cpfCnpj,
                    'tipo_pessoa' => $tipoPessoa,
                    'endereco' => $endereco ?: null,
                    'cidade' => $cidade ?: null,
                    'telefone' => $telFinal ?: null,
                    'email' => $emailFinal ?: null,
                    'contato' => null,
                    'obs' => '[CodCliente:' . $codCliente . '] ' . $obs,
                    'id' => $clienteExistenteId,
                ]);
                $clientesCache[$codCliente] = (int)$clienteExistenteId;
                $stats['clientes_atualizados']++;
            } else {
                // Cria novo
                $stmtIns = $db->prepare('
                    INSERT INTO clientes
                        (empresa_id, razao_social, nome_fantasia, cpf_cnpj, tipo_pessoa,
                         endereco, cidade, telefone, email, contato, observacoes, ativo)
                    VALUES
                        (:empresa_id, :nome, NULL, :cpf_cnpj, :tipo_pessoa,
                         :endereco, :cidade, :telefone, :email, NULL, :obs, 1)
                ');
                $stmtIns->execute([
                    'empresa_id'  => $empresaId,
                    'nome'        => $nome,
                    'cpf_cnpj'    => $cpfCnpj,
                    'tipo_pessoa' => $tipoPessoa,
                    'endereco'    => $endereco ?: null,
                    'cidade'      => $cidade ?: null,
                    'telefone'    => $telFinal ?: null,
                    'email'       => $emailFinal ?: null,
                    'obs'         => '[CodCliente:' . $codCliente . '] ' . $obs,
                ]);
                $clientesCache[$codCliente] = (int)$db->lastInsertId();
                $stats['clientes_criados']++;
            }
        }

        $clienteId = $clientesCache[$codCliente];

        // === PASSO B: Calcular data de vencimento ===
        // Pega o próximo dia $diaVenc >= hoje
        $hoje = new DateTime();
        $vencimento = new DateTime();
        $vencimento->setDate(
            (int)$hoje->format('Y'),
            (int)$hoje->format('m'),
            min($diaVenc, (int)$vencimento->format('t'))
        );
        if ($vencimento < $hoje) {
            $vencimento->modify('+1 month');
            $vencimento->setDate(
                (int)$vencimento->format('Y'),
                (int)$vencimento->format('m'),
                min($diaVenc, (int)$vencimento->format('t'))
            );
        }
        $vencimentoStr = $vencimento->format('Y-m-d');

        // === PASSO C: Criar conta a receber ===
        $descricao = $plano ?: 'Mensalidade Internet';
        // Se nome tem FAZ/etc, mantém completo pra rastreabilidade
        $descricaoFull = $descricao . ' - ' . $nome;

        $stmtCR = $db->prepare('
            INSERT INTO contas_receber
                (empresa_id, cliente_id, categoria_id, descricao, numero_documento, valor,
                 data_emissao, data_vencimento, forma_recebimento, status,
                 parcelas, parcela_atual, usuario_criacao_id)
            VALUES
                (:empresa_id, :cliente_id, :categoria_id, :descricao, :numero_doc, :valor,
                 :data_emissao, :data_vencimento, :forma_recebimento, "pendente",
                 1, 1, :usuario_id)
        ');
        $stmtCR->execute([
            'empresa_id'       => $empresaId,
            'cliente_id'       => $clienteId,
            'categoria_id'     => $categoriaId,
            'descricao'        => $descricaoFull,
            'numero_doc'       => 'TOP-' . $codCliente,
            'valor'            => $valor,
            'data_emissao'     => date('Y-m-d'),
            'data_vencimento'  => $vencimentoStr,
            'forma_recebimento'=> 'boleto',
            'usuario_id'       => $usuarioId,
        ]);

        $stats['contas_criadas']++;

        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        echo "  ✗ ERRO linha $linhaNum ($nome): " . $e->getMessage() . "\n";
        $stats['erros']++;
    }
}

echo "\n=== RESULTADO ===\n";
echo "Clientes criados:        {$stats['clientes_criados']}\n";
echo "Clientes atualizados:    {$stats['clientes_atualizados']}\n";
echo "Linhas ignoradas:        {$stats['clientes_ignorados']}\n";
echo "Contas a receber criadas:{$stats['contas_criadas']}\n";
echo "Erros:                   {$stats['erros']}\n";
echo "\nVerificação no banco:\n";
echo "  Total clientes (GLOBALMT TELECON): ";
$totalClientes = $db->query('SELECT COUNT(*) FROM clientes WHERE empresa_id = 3')->fetchColumn();
echo "$totalClientes\n";
echo "  Total contas a receber: ";
$totalCR = $db->query('SELECT COUNT(*) FROM contas_receber WHERE empresa_id = 3')->fetchColumn();
echo "$totalCR\n";
