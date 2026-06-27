<?php
/**
 * Anexos: Upload, Download, Excluir
 *
 * Endpoint unificado para anexos de contas a pagar e receber.
 * Apenas o controller das views os referenciam.
 *
 * Para simplificar, esses endpoints ficam em arquivos separados:
 *   anexo_upload.php   - POST upload
 *   anexo_download.php - GET download
 *   anexo_excluir.php  - POST excluir
 *
 * A lógica está aqui, cada endpoint apenas chama o método apropriado.
 */

declare(strict_types=1);

final class AnexoController
{
    public static function upload(): void
    {
        Auth::require();
        Permissao::requer('criar', 'index.php');

        $empresaId = Auth::user()['empresa_id'];
        $tipoOrigem = $_POST['tipo_origem'] ?? '';
        $origemId = (int)($_POST['origem_id'] ?? 0);

        if (!in_array($tipoOrigem, ['conta_pagar', 'conta_receber'], true) || $origemId <= 0) {
            Flash::set('erro', 'Parâmetros inválidos.');
            redirect('index.php');
        }

        if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            Flash::set('erro', 'Selecione um arquivo PDF.');
            redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
        }

        $result = Uploader::uploadPdf($_FILES['arquivo']);

        if (!$result['ok']) {
            Flash::set('erro', $result['erro']);
            redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('
                INSERT INTO anexos (empresa_id, tipo_origem, origem_id, nome_arquivo, nome_original,
                                    tamanho, mime_type, caminho, usuario_upload_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $empresaId,
                $tipoOrigem,
                $origemId,
                $result['nome'],
                $_FILES['arquivo']['name'],
                $_FILES['arquivo']['size'],
                $_FILES['arquivo']['type'],
                $result['caminho'],
                Auth::user()['id'],
            ]);
            Flash::set('sucesso', 'Anexo enviado.');
        } catch (PDOException $e) {
            error_log('[Anexo] Erro ao gravar: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao salvar anexo.');
        }

        redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
    }

    public static function download(): void
    {
        Auth::require();

        $id = (int)($_GET['id'] ?? 0);
        $tipo = $_GET['tipo'] ?? '';
        $empresaId = Auth::user()['empresa_id'];

        if ($id <= 0 || !in_array($tipo, ['conta_pagar', 'conta_receber'], true)) {
            http_response_code(400);
            exit('Parâmetros inválidos.');
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM anexos WHERE id = ? AND empresa_id = ? AND tipo_origem = ?');
        $stmt->execute([$id, $empresaId, $tipo]);
        $anexo = $stmt->fetch();

        if (!$anexo || !file_exists($anexo['caminho'])) {
            http_response_code(404);
            exit('Anexo não encontrado.');
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $anexo['nome_original'] . '"');
        header('Content-Length: ' . filesize($anexo['caminho']));
        readfile($anexo['caminho']);
        exit;
    }

    public static function excluir(): void
    {
        Auth::require();
        Permissao::requer('excluir', 'index.php');

        $id = (int)($_POST['id'] ?? 0);
        $tipo = $_POST['tipo'] ?? '';
        $empresaId = Auth::user()['empresa_id'];

        if ($id <= 0 || !in_array($tipo, ['conta_pagar', 'conta_receber'], true)) {
            Flash::set('erro', 'Parâmetros inválidos.');
            redirect('index.php');
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM anexos WHERE id = ? AND empresa_id = ? AND tipo_origem = ?');
        $stmt->execute([$id, $empresaId, $tipo]);
        $anexo = $stmt->fetch();

        if (!$anexo) {
            Flash::set('erro', 'Anexo não encontrado.');
            redirect('index.php');
        }

        Uploader::remover($anexo['caminho']);

        $stmtDel = $db->prepare('DELETE FROM anexos WHERE id = ? AND empresa_id = ?');
        $stmtDel->execute([$id, $empresaId]);
        Flash::set('sucesso', 'Anexo excluído.');

        redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
    }
}