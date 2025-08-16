<?php

namespace App\Services;

use PDOException;

class Operations
{
    // Implementação dos métodos de operação

    /**
     * Mapeia \PDOException para um array padronizado com http_status, código e mensagem amigável.
     * $contexto pode conter chaves como 'locatario_id', 'email', 'grupo_id' para mensagens mais claras.
     */
    public static function mapearExcecaoPDO(PDOException $pdoException, array $contexto = []): array
    {
        $sqlStateCode    = $pdoException->errorInfo[0] ?? (string)$pdoException->getCode();
        $detailedMessage = $pdoException->errorInfo[2] ?? $pdoException->getMessage();

        // Extrair pares (colunas) e (valores) se presentes no DETAIL do Postgres:
        // ex: Key (locatario_id, txt_email_usuario)=(1, joao@ex.com)
        $firstColumn = null;
        $firstValue  = null;
        if (preg_match('/Key \(([^)]+)\)=\(([^)]+)\)/', $detailedMessage, $keyMatches)) {
            $cols = array_map('trim', explode(',', $keyMatches[1]));
            $vals = array_map('trim', explode(',', $keyMatches[2]));
            $firstColumn = $cols[0] ?? null;
            $firstValue  = $vals[0] ?? null;
        }

        // Extrair coluna para not-null (ex: null value in column "txt_nome_usuario")
        $nullColumn = null;
        if (preg_match('/null value in column "([^"]+)"/i', $detailedMessage, $nullMatches)) {
            $nullColumn = $nullMatches[1];
        } elseif (preg_match('/column "([^"]+)"/i', $detailedMessage, $colMatches)) {
            // fallback genérico
            $nullColumn = $colMatches[1];
        }

        switch ($sqlStateCode) {
            case '23503': // foreign_key_violation
                $userFriendlyMessage = $firstColumn && $firstValue
                    ? "Referência inválida: o registro informado em {$firstColumn} ({$firstValue}) não existe. Cadastre esse recurso antes de continuar."
                    : "Referência inválida: existe uma chave estrangeira para um recurso inexistente. Verifique os dados referenciados.";
                $httpStatusCode = 422;
                $errorCode = 'foreign_key_violation';
                break;

            case '23505': // unique_violation
                $constraint = null;
                $cols = $vals = [];

                // tentar extrair colunas/valores do DETAIL padrão do Postgres
                if (preg_match('/Key \(([^)]+)\)=\(([^)]+)\)/', $detailedMessage, $m)) {
                    $cols = array_map('trim', explode(',', $m[1]));
                    $vals = array_map('trim', explode(',', $m[2]));
                } elseif (preg_match('/duplicate key value violates unique constraint "([^"]+)"/i', $detailedMessage, $m2)) {
                    $constraint = $m2[1];
                }

                if (!empty($cols)) {
                    $pairs = [];
                    foreach ($cols as $i => $col) {
                        $val = $vals[$i] ?? null;

                        // tentar mapear valor pelo contexto (chaves exatas ou com prefixos comuns)
                        $contextValue = null;
                        if (array_key_exists($col, $contexto)) {
                            $contextValue = $contexto[$col];
                        } else {
                            // variações comuns: sem prefixo 'txt_', remover aspas, usar nome em minúsculas
                            $candidates = [
                                trim($col, '"'),
                                ltrim(trim($col, '"'), 'txt_'),
                                mb_strtolower(trim($col, '"')),
                            ];
                            foreach ($candidates as $cand) {
                                if ($cand !== '' && array_key_exists($cand, $contexto)) {
                                    $contextValue = $contexto[$cand];
                                    break;
                                }
                            }
                        }

                        if ($contextValue !== null && $contextValue !== '') {
                            $displayVal = (is_string($contextValue) && mb_strpos($contextValue, '@') !== false)
                                ? "\"{$contextValue}\"" // melhorar leitura para e-mails
                                : $contextValue;
                        } elseif ($val !== null && $val !== '') {
                            $displayVal = (mb_strlen($val) > 50) ? mb_substr($val, 0, 47) . '...' : $val;
                        } else {
                            $displayVal = null;
                        }

                        $pairs[] = $displayVal !== null ? "{$col} = {$displayVal}" : $col;
                    }

                    $detailText = implode(', ', $pairs);
                    $userFriendlyMessage = count($pairs) === 1
                        ? "Já existe um registro com {$detailText}. Verifique os dados e tente outro valor."
                        : "Já existe um registro com os mesmos valores para ({$detailText}).";
                } else {
                    // fallback para constraint se não tivermos colunas/valores
                    if ($constraint) {
                        $userFriendlyMessage = "Violação de unicidade para a restrição \"{$constraint}\". Verifique os dados e tente outro valor.";
                    } else {
                        $userFriendlyMessage = "Já existe um registro com valores duplicados. Verifique os dados e tente outro valor.";
                    }
                }

                $httpStatusCode = 409;
                $errorCode = 'unique_violation';
                break;

            case '23502': // not_null_violation
                $col = $nullColumn ?? ($firstColumn ?? null);
                $userFriendlyMessage = $col
                    ? "Campo obrigatório ausente: {$col}. Preencha esse campo e tente novamente."
                    : "Campo obrigatório ausente. Verifique os dados enviados e tente novamente.";
                $httpStatusCode = 422;
                $errorCode = 'not_null_violation';
                break;

            case '22001': // string_data_right_truncation
                // tentativa de extrair coluna não é garantida no detalhe padrão, usar informação disponível
                $userFriendlyMessage = ($firstColumn)
                    ? "O valor enviado para {$firstColumn} excede o tamanho permitido. Reduza o tamanho do campo."
                    : "Um dos valores enviados excede o tamanho permitido. Verifique os campos e reduza o tamanho.";
                $httpStatusCode = 422;
                $errorCode = 'string_truncation';
                break;

            case '40001': // serialization_failure
            case '40P01': // deadlock_detected
                $userFriendlyMessage = "Condição de concorrência detectada. Por favor, tente novamente.";
                $httpStatusCode = 503;
                $errorCode = 'concurrency_failure';
                break;

            default:
                $userFriendlyMessage = "Erro no banco de dados.";
                $httpStatusCode = 500;
                $errorCode = (string)$sqlStateCode;
                break;
        }

        return [
            'http_status' => $httpStatusCode,
            'error_code'  => $errorCode,
            'sqlstate'    => (string)$sqlStateCode,
            'message'     => $userFriendlyMessage,
            // manter detalhe técnico para logs/telemetria
            'detail'      => $detailedMessage,
            'contexto'    => $contexto,
        ];
    }

    public static function validarRegras(array $data, array $rules, array $messages = [], array $contexto = []): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleSet) {
            $ruleList = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
            $hasSometimes = in_array('sometimes', $ruleList, true);
            $present = array_key_exists($field, $data);
            $value = $present ? $data[$field] : null;

            // se é "sometimes" e não veio, pula validação
            if ($hasSometimes && !$present) {
                continue;
            }

            // aplicar regras em ordem
            foreach ($ruleList as $rule) {
                if ($rule === 'sometimes') continue;

                // regra e possíveis parâmetros (ex: min:8)
                [$rName, $rParam] = array_pad(explode(':', $rule, 2), 2, null);

                // required
                if ($rName === 'required') {
                    if (!$present || $value === null || $value === '') {
                        $errors[$field][] = $messages["{$field}.required"] ?? "O campo {$field} é obrigatório.";
                        break; // não validar outras regras se faltar o required
                    }
                    continue;
                }

                // se não é required e não está presente, ignora outras validações
                if (!$present) {
                    break;
                }

                // integer
                if ($rName === 'integer') {
                    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                        $errors[$field][] = $messages["{$field}.integer"] ?? "O campo {$field} deve ser um número inteiro.";
                    }
                    continue;
                }

                // string
                if ($rName === 'string') {
                    if (!is_string($value)) {
                        // permitir castable scalars
                        if (!is_scalar($value)) {
                            $errors[$field][] = $messages["{$field}.string"] ?? "O campo {$field} deve ser texto.";
                        }
                    }
                    continue;
                }

                // email
                if ($rName === 'email') {
                    if (!filter_var((string)$value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$field][] = $messages["{$field}.email"] ?? "Informe um e‑mail válido para {$field}.";
                    }
                    continue;
                }

                // boolean
                if ($rName === 'boolean') {
                    $validBool = is_bool($value) || in_array($value, [0,1,'0','1','true','false'], true);
                    if (!$validBool) {
                        $errors[$field][] = $messages["{$field}.boolean"] ?? "O campo {$field} deve ser verdadeiro ou falso.";
                    }
                    continue;
                }

                // min
                if ($rName === 'min' && $rParam !== null) {
                    $min = (int)$rParam;
                    if (is_numeric($value)) {
                        if ($value < $min) {
                            $errors[$field][] = $messages["{$field}.min"] ?? "O valor de {$field} deve ser no mínimo {$min}.";
                        }
                    } else {
                        if (mb_strlen((string)$value) < $min) {
                            $errors[$field][] = $messages["{$field}.min"] ?? "O campo {$field} deve ter pelo menos {$min} caracteres.";
                        }
                    }
                    continue;
                }

                // max
                if ($rName === 'max' && $rParam !== null) {
                    $max = (int)$rParam;
                    if (is_numeric($value)) {
                        if ($value > $max) {
                            $errors[$field][] = $messages["{$field}.max"] ?? "O valor de {$field} não pode ser maior que {$max}.";
                        }
                    } else {
                        if (mb_strlen((string)$value) > $max) {
                            $errors[$field][] = $messages["{$field}.max"] ?? "O campo {$field} não pode exceder {$max} caracteres.";
                        }
                    }
                    continue;
                }

                // regras não suportadas aqui (exists, unique, etc.) são ignoradas — devem ser tratadas via DB
            }
        }

        if (!empty($errors)) {
            // pega a primeira mensagem amigável
            $firstMessage = reset($errors)[0] ?? 'Erro de validação nos dados enviados.';
            return [
                'http_status' => 422,
                'error_code'  => 'validation_error',
                'sqlstate'    => null,
                'message'     => $firstMessage,
                'detail'      => $errors,
                'contexto'    => $contexto,
            ];
        }

        // OK
        return [
            'http_status' => 200,
            'error_code'  => null,
            'sqlstate'    => null,
            'message'     => 'OK',
            'detail'      => [],
            'contexto'    => $contexto,
        ];
    }

    /**
     * Monta resposta padronizada para sucesso (mesmo formato do mapeador de erros).
     *
     * @param array $data  Registro(s) ou payload a colocar em 'detail'
     * @param int $httpStatus
     * @param string $message
     * @param array $contexto
     * @return array
     */
    public static function padronizarRespostaSucesso(array $data, int $httpStatus = 201, string $message = 'Operação realizada com sucesso.', array $contexto = []): array
    {
        return [
            'http_status' => $httpStatus,
            'error_code'  => null,
            'sqlstate'    => null,
            'message'     => $message,
            'detail'      => $data,
            'contexto'    => $contexto,
        ];
    }
}
