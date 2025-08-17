<?php

namespace App\Services;

use PDOException;
use InvalidArgumentException;

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

            case '42601': // syntax_error
                // Detectar palavras-chave específicas no erro para dar mensagem mais direcionada
                if (preg_match('/syntax error at or near ["\']?(order by|limit|offset)["\']?/i', $detailedMessage, $matches)) {
                    $keyword = strtolower($matches[1]);
                    $userFriendlyMessage = match ($keyword) {
                        'order by' => "Erro na ordenação dos dados. Verifique os campos utilizados para ordenação e tente novamente.",
                        'limit' => "Erro no limite de registros. Verifique se o valor do limite é um número válido.",
                        'offset' => "Erro no deslocamento de registros. Verifique se o valor do offset é um número válido.",
                        default => "Erro de sintaxe na consulta. Verifique os parâmetros de busca e tente novamente."
                    };
                } elseif (preg_match('/syntax error at or near/i', $detailedMessage)) {
                    $userFriendlyMessage = "Erro de sintaxe na consulta ao banco de dados. Verifique os parâmetros de busca e tente novamente.";
                } else {
                    $userFriendlyMessage = "Consulta inválida. Verifique os dados enviados e tente novamente.";
                }
                $httpStatusCode = 400;
                $errorCode = 'syntax_error';
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
            'msg'     => $userFriendlyMessage,
            // manter detalhe técnico para logs/telemetria
            'detail'      => $detailedMessage,
            'contexto'    => $contexto,
        ];
    }

    public static function validarRegras(array $filtros, array $regrasValidacao, array $msg = []): array
    {
        $errors = [];

        // percorrer as chaves com os conjuntos de regras
        foreach ($regrasValidacao as $chave => $conjuntoRegras) {

            //Transformar as regras de cada chave em uma lista
            $listaRegras = is_array($conjuntoRegras) ? $conjuntoRegras : explode('|', $conjuntoRegras);

            // Verificar se há regra opcional
            $possuiRegraOpcional   = in_array('quandoPresente ', $listaRegras, true);

            // Verificar qual o tipo da chave verificando se contem na listaRegras string, integer ou date
            $tipoChave = null;
            if (in_array('string', $listaRegras, true)) {
                $tipoChave = 'string';
            } elseif (in_array('integer', $listaRegras, true)) {
                $tipoChave = 'integer';
            } elseif (in_array('date', $listaRegras, true)) {
                $tipoChave = 'date';
            }

            // Verificar se a chave de validação está presente nos filtros
            $chavePresente = array_key_exists($chave, $filtros);
            $valor = $chavePresente ? $filtros[$chave] : null;

            // se é "quandoPresente " e não veio, pula validação
            if ($possuiRegraOpcional && !$chavePresente) {
                continue;
            }

            // aplicar regras em ordem
            foreach ($listaRegras as $regra) {

                // Ignorar regra "quandoPresente"
                if ($regra === 'quandoPresente') continue;

                /**regra e possíveis parâmetros (ex: min:8)
                 * Garante que o array resultante sempre tenha 2 posições.
                 * Se não tiver, ele preenche com null.
                 * Exemplo:
                 * ["min", "5"] → continua ["min", "5"]
                 * ["required"] → vira ["required", null]
                 */

                [$chaveRegra, $valorRegra] = array_pad(explode(':', $regra, 2), 2, null);


                // required
                if ($chaveRegra === 'required') {
                    if (!$chavePresente || $valor === null || $valor === '') {
                        $errors[$chave][] = $msg["{$chave}.required"] ?? "O campo {$chave} é obrigatório.";
                        break;
                    }
                    // não validar outras regras se faltar o required
                    continue;
                }

                // se não é required e não está presente, ignora outras validações
                if (!$chavePresente) {
                    break;
                }

                // integer
                if ($chaveRegra === 'integer') {
                    if (filter_var($valor, FILTER_VALIDATE_INT) === false) {
                        $errors[$chave][] = $msg["{$chave}.integer"] ?? "O campo {$chave} deve ser um número inteiro.";
                    }

                    continue;
                }

                // string
                if ($chaveRegra === 'string') {
                    if (!is_string($valor)) {
                        // permitir castable scalars
                        if (!is_scalar($valor)) {
                            $errors[$chave][] = $msg["{$chave}.string"] ?? "O campo {$chave} deve ser texto.";
                        }
                    }
                    continue;
                }

                // email
                if ($chaveRegra === 'email') {
                    if (!filter_var((string)$valor, FILTER_VALIDATE_EMAIL)) {
                        $errors[$chave][] = $msg["{$chave}.email"] ?? "Informe um e‑mail válido para {$chave}.";
                    }
                    continue;
                }

                // password: requer pelo menos uma letra, um número e uma letra maiúscula
                if ($chaveRegra === 'password') {
                    $valStr = (string)$valor;
                    $hasLetter = preg_match('/[a-z]/', $valStr);
                    $hasNumber = preg_match('/\d/', $valStr);
                    $hasUpper  = preg_match('/[A-Z]/', $valStr);

                    if (!$hasLetter || !$hasNumber || !$hasUpper) {
                        $errors[$chave][] = $msg["{$chave}.password"] ?? "O campo {$chave} deve conter pelo menos uma letra minuscula, um número e uma letra maiúscula.";
                    }
                    continue;
                }

                // boolean
                if ($chaveRegra === 'boolean') {
                    $validBool = is_bool($valor) || in_array($valor, [0, 1, '0', '1', 'true', 'false'], true);
                    if (!$validBool) {
                        $errors[$chave][] = $msg["{$chave}.boolean"] ?? "O campo {$chave} deve ser verdadeiro ou falso.";
                    }
                    continue;
                }

                // min
                if ($chaveRegra === 'min' && $valorRegra !== null) {
                    $min = (int)$valorRegra;
                    // com base no tipoChave verificar se é o minimo
                    if ($tipoChave === 'integer' && is_numeric($valor)) {
                        if ($valor < $min) {
                            $errors[$chave][] = $msg["{$chave}.min"] ?? "O valor de {$chave} deve ser no mínimo {$min}.";
                        }
                    } elseif ($tipoChave === 'string') {
                        if (mb_strlen((string)$valor) < $min) {
                            $errors[$chave][] = $msg["{$chave}.min"] ?? "O campo {$chave} deve ter pelo menos {$min} caracteres.";
                        }
                    } elseif ($tipoChave === 'date') {
                        // $valorRegra pode ser um inteiro (dias) ou uma datetime/ISO string.
                        try {
                            if (is_numeric($valorRegra)) {
                                $limiteDate = (new \DateTime())->modify("-{$valorRegra} days");
                            } else {
                                $limiteDate = new \DateTime($valorRegra);
                            }

                            $valorDate = new \DateTime($valor);

                            if ($valorDate < $limiteDate) {
                                $errors[$chave][] = $msg["{$chave}.min"] ?? "O campo {$chave} não pode ser anterior a " . $limiteDate->format('Y-m-d H:i:s') . ".";
                            }
                        } catch (\Exception $e) {
                            // se não for possível interpretar como data, adicionar erro de formato
                            $errors[$chave][] = $msg["{$chave}.date"] ?? "O campo {$chave} deve ser uma data/hora válida.";
                        }
                    }
                    continue;
                }

                // max
                if ($chaveRegra === 'max' && $valorRegra !== null) {
                    $max = (int)$valorRegra;

                    if ($tipoChave === 'integer' && is_numeric($valor)) {
                        if ($valor > $max) {
                            $errors[$chave][] = $msg["{$chave}.max"] ?? "O valor de {$chave} não pode ser maior que {$max}.";
                        }
                    } elseif ($tipoChave === 'string') {
                        if (mb_strlen((string)$valor) > $max) {
                            $errors[$chave][] = $msg["{$chave}.max"] ?? "O campo {$chave} não pode exceder {$max} caracteres.";
                        }
                    } elseif ($tipoChave === 'date') {
                        try {
                            if (is_numeric($valorRegra)) {
                                $limiteDate = (new \DateTime())->modify("+{$valorRegra} days");
                            } else {
                                $limiteDate = new \DateTime($valorRegra);
                            }

                            $valorDate = new \DateTime($valor);

                            if ($valorDate > $limiteDate) {
                                $errors[$chave][] = $msg["{$chave}.max"] ?? "O campo {$chave} não pode ser posterior a " . $limiteDate->format('Y-m-d H:i:s') . ".";
                            }
                        } catch (\Exception $e) {
                            $errors[$chave][] = $msg["{$chave}.date"] ?? "O campo {$chave} deve ser uma data/hora válida.";
                        }
                    }

                    continue;
                }
            }
        }

        // filtrar apenas chaves que existem nas regras de validação
        $contextoFiltrado = array_intersect_key($filtros, $regrasValidacao);

        if (!empty($errors)) {
            // pega a primeira mensagem amigável
            $firstMessage = reset($errors)[0] ?? 'Erro de validação nos dados enviados.';
            return [
                'http_status' => 422,
                'error_code'  => 'validation_error',
                'sqlstate'    => null,
                'msg'     => $firstMessage,
                'detail'      => $errors,
                'contexto'    => $contextoFiltrado,
            ];
        }

        // OK
        return [
            'http_status' => 200,
            'error_code'  => null,
            'sqlstate'    => null,
            'msg'     => 'Validação bem-sucedida.',
            'detail'      => [],
            'contexto'    => $contextoFiltrado,
        ];
    }

    /**
     * Monta resposta padronizada para sucesso (mesmo formato do mapeador de erros).
     *
     * @param array $data  Registro(s) ou payload a colocar em 'detail'
     * @param int $httpStatus
     * @param string $msg
     * @param array $contexto
     * @return array
     */
    public static function padronizarRespostaSucesso(array $data, int $httpStatus = 201, string $msg = 'Operação realizada com sucesso.', array $contexto = []): array
    {
        return [
            'http_status' => $httpStatus,
            'error_code'  => null,
            'sqlstate'    => null,
            'msg'     => $msg,
            'detail'      => $data,
            'contexto'    => $contexto,
        ];
    }

    public static function Parametrizar(array $filtros): array
    {
        $where = [];
        $execParams = [];
        $optsParams = [];

        foreach ($filtros as $key => $val) {
            if ($val === null || $val === '') continue;

            // plural -> IN (...) quando valor for array
            if (str_ends_with($key, 's') && is_array($val)) {
                $column = substr($key, 0, -1);

                $safe = preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
                $placeholders = [];
                foreach (array_values($val) as $i => $v) {
                    $ph = ':' . $safe . '_' . $i;
                    $placeholders[] = $ph;
                    // se coluna sugere id, cast para int
                    $execParams[$ph] = (str_ends_with($column, 'id_') === true || str_ends_with($column, '_id') === true) ? (int)$v : $v;
                }

                if (count($placeholders) > 0) {
                    $where[] = " AND $column IN (" . implode(', ', $placeholders) . ")";
                }
                continue;
            }

            // colunas de texto -> busca parcial
            if (str_starts_with($key, 'txt_') === true) {
                $where[] = " AND $key ILIKE :$key";
                $execParams[':' . $key] = '%' . $val . '%';
                continue;
            }

            // flags booleanas
            if (str_starts_with($key, 'flg_') === true) {
                $where[] = " AND $key = :$key";
                $execParams[':' . $key] = (bool)$val;
                continue;
            }
            // se o parametro for order_by, limit ou offset atribuir a optsParams
            if (in_array($key, ['order_by', 'limit', 'offset'])) {
                // manter em optsParams para uso na query e também registrar em execParams caso exista
                if (in_array($key, ['limit', 'offset'], true)) {
                    $optsParams[$key] = " $key  :$key";
                    $execParams[':' . $key] = (int)$val;
                } else {
                    // verificar se val não é um sql injection
                    // se contem select, delete ou drop ou comandos perigosos
                    if (preg_match('/\b(SELECT|DELETE|DROP|INSERT|UPDATE|TRUNCATE|MERGE|EXEC)\b/i', $val)) {
                        $optsParams[$key] = null;
                        //TODO: Log warning about potential SQL injection
                    } else {
                        // A chave vem como order_by , mas quando utilizada na query, deve ser substituída por "order by"
                        $sql_key = str_replace('_', ' ', $key);
                        $optsParams[$key] = " $sql_key  $val";
                    }
                }
                continue;
            }

            // fallback: igualdade direta
            $where[] = " AND $key = :$key";
            $execParams[':' . $key] = $val;
        }

        return ['whereParams' => $where, 'execParams' => $execParams, 'optsParams' => $optsParams];
    }
}
