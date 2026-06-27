<?php
/**
 * Validator - Validações de formulário reutilizáveis
 *
 * Métodos estáticos para validar campos comuns. Retornam array
 * ['ok' => bool, 'msg' => string] para feedback consistente.
 */

declare(strict_types=1);

final class Validator
{
    /**
     * @return array{ok:bool,msg:string}
     */
    public static function required(string $campo, mixed $valor): array
    {
        if ($valor === null || (is_string($valor) && trim($valor) === '')) {
            return ['ok' => false, 'msg' => "Campo '$campo' é obrigatório."];
        }
        return ['ok' => true, 'msg' => ''];
    }

    /**
     * @return array{ok:bool,msg:string}
     */
    public static function email(string $email): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'msg' => 'E-mail inválido.'];
        }
        return ['ok' => true, 'msg' => ''];
    }

    /**
     * @return array{ok:bool,msg:string}
     */
    public static function numero(string $campo, mixed $valor, ?float $min = null, ?float $max = null): array
    {
        if (!is_numeric($valor)) {
            return ['ok' => false, 'msg' => "Campo '$campo' deve ser numérico."];
        }
        $n = (float)$valor;
        if ($min !== null && $n < $min) {
            return ['ok' => false, 'msg' => "Campo '$campo' deve ser >= $min."];
        }
        if ($max !== null && $n > $max) {
            return ['ok' => false, 'msg' => "Campo '$campo' deve ser <= $max."];
        }
        return ['ok' => true, 'msg' => ''];
    }

    /**
     * @return array{ok:bool,msg:string}
     */
    public static function data(string $campo, string $data, string $formato = 'Y-m-d'): array
    {
        $d = DateTime::createFromFormat($formato, $data);
        if (!$d || $d->format($formato) !== $data) {
            return ['ok' => false, 'msg' => "Campo '$campo' deve ser uma data válida (AAAA-MM-DD)."];
        }
        return ['ok' => true, 'msg' => ''];
    }

    /**
     * @return array{ok:bool,msg:string}
     */
    public static function enum(string $campo, mixed $valor, array $permitidos): array
    {
        if (!in_array($valor, $permitidos, true)) {
            return ['ok' => false, 'msg' => "Campo '$campo' tem valor inválido."];
        }
        return ['ok' => true, 'msg' => ''];
    }

    /**
     * Sanitiza string para saída em HTML.
     */
    public static function h(?string $s): string
    {
        return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Formata valor monetário BRL.
     */
    public static function money(float $valor): string
    {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }
}