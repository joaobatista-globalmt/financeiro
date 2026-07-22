<?php
/**
 * ClientesController - CRUD de clientes (para Contas a Receber)
 *
 * Espelho do FornecedoresController, mas para a tabela `clientes`.
 * Inclui:
 *  - Campos de vencimento (dia + tipo)
 *  - Flags emite_nfse / emite_boleto
 *  - Listas de e-mails separados por tipo (cliente_emails_nfse / cliente_emails_boleto)
 */

declare(strict_types=1);

final class ClientesController
{
        public function index(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];

        // Le filtros da URL
        $fRazao   = trim((string)($_GET['razao_social']   ?? ''));
        $fFant    = trim((string)($_GET['nome_fantasia']  ?? ''));
        $fDoc     = trim((string)($_GET['cpf_cnpj']       ?? ''));
        $fTipo    = (string)($_GET['tipo']               ?? '');
        $fAtivo   = (string)($_GET['ativo']              ?? '');
        $fDia     = (string)($_GET['dia_vencimento']     ?? '');

        // Monta WHERE dinamico
        $where  = ['c.empresa_id = ?'];
        $params = [$empresaId];

        if ($fRazao !== '') {
            $where[] = 'c.razao_social LIKE ?';
            $params[] = '%' . $fRazao . '%';
        }
        if ($fFant !== '') {
            $where[] = 'c.nome_fantasia LIKE ?';
            $params[] = '%' . $fFant . '%';
        }
        if ($fDoc !== '') {
            // Limpa mascara: 10.915.101/0009-01 vira 10915101000901
            $docLimpo = preg_replace('/[^0-9]/', '', $fDoc);
            $where[] = 'REPLACE(REPLACE(REPLACE(REPLACE(c.cpf_cnpj, ".", ""), "/", ""), "-", ""), " ", "") LIKE ?';
            $params[] = '%' . $docLimpo . '%';
        }
        if ($fTipo === 'F' || $fTipo === 'J') {
            $where[] = 'c.tipo_pessoa = ?';
            $params[] = $fTipo;
        }
        if ($fAtivo === '0' || $fAtivo === '1') {
            $where[] = 'c.ativo = ?';
            $params[] = (int)$fAtivo;
        }
        if ($fDia !== '' && (int)$fDia >= 1 && (int)$fDia <= 31) {
            $where[] = 'c.dia_vencimento = ?';
            $params[] = (int)$fDia;
        }

        $whereSql = implode(' AND ', $where);

        $db = Database::getConnection();

        // Total sem filtro aplicado (para o contador "X de Y")
        $stmtTotal = $db->prepare('SELECT COUNT(*) FROM clientes c WHERE c.empresa_id = ?');
        $stmtTotal->execute([$empresaId]);
        $totalGeral = (int)$stmtTotal->fetchColumn();

        // Lista filtrada
        $stmt = $db->prepare("
            SELECT c.*,
                   (SELECT COUNT(*) FROM contas_receber WHERE cliente_id = c.id) AS total_contas,
                   (SELECT COUNT(*) FROM cliente_emails_nfse WHERE cliente_id = c.id) AS qtd_emails_nfse,
                   (SELECT COUNT(*) FROM cliente_emails_boleto WHERE cliente_id = c.id) AS qtd_emails_boleto
            FROM clientes c
            WHERE $whereSql
            ORDER BY c.ativo DESC, c.razao_social
        ");
        $stmt->execute($params);
        $clientes = $stmt->fetchAll();

        $filtrosAplicados = ($fRazao !== '' || $fFant !== '' || $fDoc !== ''
            || $fTipo !== '' || $fAtivo !== '' || $fDia !== '');

        layout('Clientes', 'clientes/index.php', [
            'clientes'          => $clientes,
            'filtros'           => [
                'razao_social'   => $fRazao,
                'nome_fantasia'  => $fFant,
                'cpf_cnpj'       => $fDoc,
                'tipo'           => $fTipo,
                'ativo'          => $fAtivo,
                'dia_vencimento' => $fDia,
            ],
            'filtrosAplicados'  => $filtrosAplicados,
            'totalGeral'        => $totalGeral,
        ]);
    }


    public function form(): void
    {
        Auth::require();
        $id = (int)($_GET['id'] ?? 0);
        $cliente = null;

        if ($id > 0) {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT * FROM clientes WHERE id = ? AND empresa_id = ?');
            $stmt->execute([$id, Auth::user()['empresa_id']]);
            $cliente = $stmt->fetch();

            if (!$cliente) {
                Flash::set('erro', 'Cliente não encontrado.');
                redirect('clientes.php');
            }
        }

        layout($cliente ? 'Editar Cliente' : 'Novo Cliente', 'clientes/form.php', [
            'cliente' => $cliente,
        ]);
    }

    public function salvar(): void
    {
        Auth::require();
        Permissao::requer('criar', 'clientes.php');

        $empresaId = Auth::user()['empresa_id'];
        $id = (int)($_POST['id'] ?? 0);

        // Suporte a retorno para tela de origem (ex: conta_receber_form.php) com seleção automática
        $returnTo = preg_match('/^[a-z0-9_]+$/', (string)($_GET['return'] ?? '')) ? $_GET['return'] : '';
        $returnSelect = preg_match('/^[a-z0-9_]+$/', (string)($_GET['select'] ?? '')) ? $_GET['select'] : '';

        // Validações
        if (empty(trim($_POST['razao_social'] ?? ''))) {
            Flash::set('erro', 'Razão social é obrigatória.');
            $back = $returnTo ? $returnTo : ($id > 0 ? "cliente_form.php?id=$id" : 'cliente_form.php');
            redirect($back);
        }
        if (!in_array($_POST['tipo_pessoa'] ?? '', ['F', 'J'], true)) {
            Flash::set('erro', 'Tipo de pessoa inválido.');
            $back = $returnTo ? $returnTo : ($id > 0 ? "cliente_form.php?id=$id" : 'cliente_form.php');
            redirect($back);
        }

        // Validação da chave PIX (se informada)
        $pixTipo  = $_POST['pix_tipo'] ?? '';
        $pixChave = trim((string)($_POST['pix_chave'] ?? ''));
        $tiposPixValidos = ['cpf', 'cnpj', 'email', 'telefone', 'aleatoria'];
        if ($pixTipo !== '' && !in_array($pixTipo, $tiposPixValidos, true)) {
            $pixTipo = '';
        }
        if ($pixChave === '' || $pixTipo === '') {
            $pixChave = '';
            $pixTipo = '';
        }

        // Coleta dados básicos
        $dados = [
            'razao_social'    => trim($_POST['razao_social']),
            'nome_fantasia'   => trim($_POST['nome_fantasia'] ?? '') ?: null,
            'cpf_cnpj'        => trim($_POST['cpf_cnpj'] ?? '') ?: null,
            'tipo_pessoa'     => $_POST['tipo_pessoa'],
            'endereco'        => trim($_POST['endereco'] ?? '') ?: null,
            'endereco_maps'   => trim($_POST['endereco_maps'] ?? '') ?: null,
            'cidade'          => trim($_POST['cidade'] ?? '') ?: null,
            'uf'              => strtoupper(trim($_POST['uf'] ?? '')) ?: null,
            'cep'             => trim($_POST['cep'] ?? '') ?: null,
            'telefone'        => trim($_POST['telefone'] ?? '') ?: null,
            'email'           => trim($_POST['email'] ?? '') ?: null,
            'contato'         => trim($_POST['contato'] ?? '') ?: null,
            'observacoes'     => trim($_POST['observacoes'] ?? '') ?: null,
            'pix_chave'       => $pixChave ?: null,
            'pix_tipo'        => $pixTipo ?: null,
        ];

        // Campos novos
        $diaVenc = $_POST['dia_vencimento'] ?? '';
        $dados['dia_vencimento'] = ($diaVenc !== '' && (int)$diaVenc >= 1 && (int)$diaVenc <= 31) ? (int)$diaVenc : null;

        $tipoVenc = $_POST['tipo_vencimento'] ?? '';
        $dados['tipo_vencimento'] = in_array($tipoVenc, ['mes_corrente', 'mes_seguinte'], true) ? $tipoVenc : null;

        $dados['emite_nfse']   = isset($_POST['emite_nfse'])   ? 1 : 0;
        $dados['emite_boleto'] = isset($_POST['emite_boleto']) ? 1 : 0;
        $dados['ativo']        = isset($_POST['ativo'])        ? 1 : 0;

        // E-mails NFSe/Boleto
        $emailsNfse   = $this->coletarEmails($_POST['emails_nfse']   ?? []);
        $emailsBoleto = $this->coletarEmails($_POST['emails_boleto'] ?? []);

        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            $novoId = 0;
            if ($id > 0) {
                $sql = 'UPDATE clientes SET
                            razao_social=:razao_social, nome_fantasia=:nome_fantasia, cpf_cnpj=:cpf_cnpj,
                            tipo_pessoa=:tipo_pessoa, endereco=:endereco, endereco_maps=:endereco_maps, cidade=:cidade, uf=:uf,
                            cep=:cep, telefone=:telefone, email=:email, contato=:contato,
                            observacoes=:observacoes, ativo=:ativo,
                            dia_vencimento=:dia_vencimento, tipo_vencimento=:tipo_vencimento,
                            emite_nfse=:emite_nfse, emite_boleto=:emite_boleto,
                            pix_chave=:pix_chave, pix_tipo=:pix_tipo
                        WHERE id=:id AND empresa_id=:empresa_id';
                $stmt = $db->prepare($sql);
                $dados['id'] = $id;
                $dados['empresa_id'] = $empresaId;
                $stmt->execute($dados);
                $novoId = $id;
            } else {

        // Constraint UNIQUE: checa duplicata por (empresa_id, cpf_cnpj)
        $cpfCnpj = trim((string)($dados['cpf_cnpj'] ?? ''));
        if ($cpfCnpj !== '') {
            $idAtual = (int)($dados['id'] ?? 0);
            $stmtChk = $db->prepare('SELECT id, razao_social FROM clientes WHERE empresa_id = ? AND cpf_cnpj = ? AND id != ? LIMIT 1');
            $stmtChk->execute([$empresaId, $cpfCnpj, $idAtual]);
            $duplicata = $stmtChk->fetch(PDO::FETCH_ASSOC);
            if ($duplicata) {
                Flash::set('erro', 'Ja existe um cliente com este CPF/CNPJ nesta empresa: #' . $duplicata['id'] . ' - ' . $duplicata['razao_social']);
                redirect('cliente_form.php' . ($idAtual ? '?id=' . $idAtual : ''));
            }
        }

                $sql = 'INSERT INTO clientes
                            (empresa_id, razao_social, nome_fantasia, cpf_cnpj, tipo_pessoa,
                             endereco, endereco_maps, cidade, uf, cep, telefone, email, contato, observacoes, ativo,
                             dia_vencimento, tipo_vencimento, emite_nfse, emite_boleto,
                             pix_chave, pix_tipo)
                        VALUES
                            (:empresa_id, :razao_social, :nome_fantasia, :cpf_cnpj, :tipo_pessoa,
                             :endereco, :endereco_maps, :cidade, :uf, :cep, :telefone, :email, :contato, :observacoes, :ativo,
                             :dia_vencimento, :tipo_vencimento, :emite_nfse, :emite_boleto,
                             :pix_chave, :pix_tipo)';
                $stmt = $db->prepare($sql);
                $dados['empresa_id'] = $empresaId;
                $stmt->execute($dados);
                $novoId = (int)$db->lastInsertId();
            }

            // Atualizar listas de e-mails
            $this->salvarEmails($db, $novoId, 'cliente_emails_nfse',   $emailsNfse);
            $this->salvarEmails($db, $novoId, 'cliente_emails_boleto', $emailsBoleto);

            $db->commit();
            Flash::set('sucesso', $id > 0 ? 'Cliente atualizado.' : 'Cliente criado.');

            // Se veio de outra tela e é criação, volta via view intermediária (cross-window)
            // pra preservar dados da janela pai (não causa reload)
            if ($returnTo && $id === 0 && $novoId > 0) {
                $query = http_build_query([
                    'tipo'   => 'cliente',
                    'select' => $returnSelect,
                    'id'     => $novoId,
                    'label'  => $dados['razao_social'],
                ]);
                redirect('_criar_filho_sucesso.php?' . $query);
            }
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('[Clientes] Erro: ' . $e->getMessage());
            // Mostra mensagem detalhada em dev (ou generica em prod) + redireciona pro FORM
            // (antes redirecionava pra clientes.php = lista, e o usuario NUNCA via o erro)
            $msg = 'Erro ao salvar cliente.';
            if (defined('DEBUG') && DEBUG) {
                $msg .= ' Detalhes: ' . htmlspecialchars($e->getMessage());
            }
            Flash::set('erro', $msg);
            $back = $returnTo ? $returnTo : ($id > 0 ? "cliente_form.php?id=$id" : 'cliente_form.php');
            redirect($back);
        }

        // Se chegou aqui sem catch, redireciona normalmente pra lista
        redirect('clientes.php');
    }

    /**
     * Coleta e valida e-mails do POST, removendo vazios/duplicados.
     * @return string[] Lista de e-mails válidos
     */
    private function coletarEmails(array $lista): array
    {
        $validos = [];
        foreach ($lista as $email) {
            $email = trim((string)$email);
            if ($email === '') continue;
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validos[] = strtolower($email);
            }
        }
        return array_values(array_unique($validos));
    }

    /**
     * Substitui a lista de e-mails de um cliente (estratégia: delete all + insert).
     * @param string $tabela Nome da tabela (cliente_emails_nfse ou cliente_emails_boleto)
     */
    private function salvarEmails(\PDO $db, int $clienteId, string $tabela, array $emails): void
    {
        $del = $db->prepare("DELETE FROM $tabela WHERE cliente_id = ?");
        $del->execute([$clienteId]);

        if (empty($emails)) return;

        $ins = $db->prepare("INSERT INTO $tabela (cliente_id, email) VALUES (?, ?)");
        foreach ($emails as $email) {
            $ins->execute([$clienteId, $email]);
        }
    }

    public function acao(): void
    {
        Auth::require();
        Permissao::requer('excluir', 'clientes.php');
        $empresaId = Auth::user()['empresa_id'];
        $acao = $_POST['acao'] ?? $_GET['acao'] ?? '';
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

        $db = Database::getConnection();

        if ($id <= 0) {
            Flash::set('erro', 'ID inválido.');
            redirect('clientes.php');
        }

        try {
            if ($acao === 'excluir') {
                // Confirmar que o cliente pertence à empresa (segurança multi-tenant)
                $stmtOwn = $db->prepare('SELECT id FROM clientes WHERE id = ? AND empresa_id = ?');
                $stmtOwn->execute([$id, $empresaId]);
                if (!$stmtOwn->fetch()) {
                    Flash::set('erro', 'Cliente não encontrado.');
                    redirect('clientes.php');
                }

                // Checagem prévia de FK: contas_receber e cliente_servicos (RESTRICT)
                $stmtCR = $db->prepare('SELECT COUNT(*) AS qtd FROM contas_receber WHERE cliente_id = ?');
                $stmtCR->execute([$id]);
                $qtdContas = (int)$stmtCR->fetch()['qtd'];

                $stmtCS = $db->prepare('SELECT COUNT(*) AS qtd FROM cliente_servicos WHERE cliente_id = ?');
                $stmtCS->execute([$id]);
                $qtdServicos = (int)$stmtCS->fetch()['qtd'];

                if ($qtdContas > 0 || $qtdServicos > 0) {
                    $partes = [];
                    if ($qtdContas > 0)    $partes[] = $qtdContas . ' conta(s) a receber';
                    if ($qtdServicos > 0)  $partes[] = $qtdServicos . ' serviço(s) vinculado(s)';
                    Flash::set('erro', 'Não é possível excluir: cliente possui ' . implode(' e ', $partes) . '. Exclua ou transfira antes.');
                    redirect('clientes.php');
                }

                // E-mails (cliente_emails_nfse, cliente_emails_boleto) são ON DELETE CASCADE
                $stmt = $db->prepare('DELETE FROM clientes WHERE id = ? AND empresa_id = ?');
                $stmt->execute([$id, $empresaId]);
                Flash::set('sucesso', 'Cliente excluído permanentemente.');
            } elseif ($acao === 'toggle' && $id > 0) {
                $stmt = $db->prepare('UPDATE clientes SET ativo = NOT ativo WHERE id = ? AND empresa_id = ?');
                $stmt->execute([$id, $empresaId]);
                Flash::set('sucesso', 'Status alterado.');
            } else {
                Flash::set('erro', 'Ação inválida.');
            }
        } catch (PDOException $e) {
            error_log('[Clientes] Erro: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao executar ação.');
        }

        redirect('clientes.php');
    }
}
