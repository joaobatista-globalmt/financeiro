<?php
/**
 * Uploader - Upload de arquivos PDF (anexos)
 *
 * Valida MIME type, magic bytes, tamanho máximo (10MB).
 * Gera nome único com UUID. Move para diretório de uploads.
 */

declare(strict_types=1);

final class Uploader
{
    private const MAX_SIZE = 10 * 1024 * 1024; // 10MB
    private const ALLOWED_MIMES = ['application/pdf'];
    private const UPLOAD_DIR = '/home/sistema/financeiro/uploads/';

    /**
     * Processa upload de arquivo PDF.
     * Retorna array com dados do arquivo ou array com erro.
     *
     * @param array $file Entrada do $_FILES
     * @return array{ok:bool,erro?:string,nome?:string,caminho?:string}
     */
    public static function uploadPdf(array $file): array
    {
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'erro' => 'Erro no upload do arquivo.'];
        }

        if ($file['size'] > self::MAX_SIZE) {
            return ['ok' => false, 'erro' => 'Arquivo excede o tamanho máximo de 10MB.'];
        }

        if (!in_array($file['type'], self::ALLOWED_MIMES, true)) {
            return ['ok' => false, 'erro' => 'Apenas arquivos PDF são permitidos.'];
        }

        // Validar magic bytes (primeiros 4 bytes: %PDF)
        $handle = fopen($file['tmp_name'], 'rb');
        if (!$handle) {
            return ['ok' => false, 'erro' => 'Não foi possível ler o arquivo.'];
        }
        $magic = fread($handle, 4);
        fclose($handle);

        if ($magic !== '%PDF') {
            return ['ok' => false, 'erro' => 'Arquivo não é um PDF válido.'];
        }

        // Gerar nome único
        $uuid = bin2hex(random_bytes(16));
        $nomeArquivo = $uuid . '.pdf';

        // Garantir que o diretório existe
        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0755, true);
        }

        $caminhoCompleto = self::UPLOAD_DIR . $nomeArquivo;

        if (!move_uploaded_file($file['tmp_name'], $caminhoCompleto)) {
            return ['ok' => false, 'erro' => 'Falha ao salvar o arquivo no servidor.'];
        }

        // Restringir permissões
        chmod($caminhoCompleto, 0644);

        return [
            'ok'       => true,
            'nome'     => $nomeArquivo,
            'caminho'  => $caminhoCompleto,
        ];
    }

    /**
     * Remove um arquivo de upload.
     */
    public static function remover(string $caminho): bool
    {
        if (!str_starts_with($caminho, self::UPLOAD_DIR)) {
            return false; // segurança: só remove dentro do diretório permitido
        }

        if (file_exists($caminho)) {
            return unlink($caminho);
        }

        return true;
    }
}