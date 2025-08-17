<?php

namespace App\Services;

use PDOException;
use PDO;
use PDOStatement;


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

            case '42703': // undefined_column
                // ex: ERROR:  column "txt_teste" does not exist
                $col = null;
                if (preg_match('/column "([^"]+)" does not exist/i', $detailedMessage, $m)) {
                    $col = $m[1];
                } elseif (preg_match('/column ([\w.]+)/i', $detailedMessage, $m2)) {
                    $col = $m2[1];
                }

                // tentar extrair trecho da LINE para mostrar contexto do erro
                $hint = null;
                if (preg_match('/LINE \d+:\s*(.+)$/m', $detailedMessage, $mLine)) {
                    $hint = trim($mLine[1]);
                }

                $userFriendlyMessage = $col
                    ? "Coluna inválida ou inexistente: a coluna \"{$col}\" não existe na tabela ou no conjunto de colunas referenciadas na consulta. Verifique ortografia, aliases e se a coluna pertence à tabela correta."
                    : "A consulta refere-se a uma coluna inexistente. Verifique os nomes das colunas, aliases e a estrutura do banco.";

                if ($hint) {
                    $userFriendlyMessage .= " Trecho provável do problema: \"{$hint}\".";
                }

                $httpStatusCode = 500;
                $errorCode = 'undefined_column';
                break;

            case '22P02': // invalid_text_representation (ex: invalid input syntax for type bigint)
                $type = null;
                $offending = null;
                if (preg_match('/invalid input syntax for type\s+(\w+):\s*"([^"]+)"/i', $detailedMessage, $m)) {
                    $type = $m[1];
                    $offending = $m[2];
                } elseif (preg_match("/invalid input syntax for type\s+(\\w+):\s*'([^']+)'/i", $detailedMessage, $m2)) {
                    $type = $m2[1];
                    $offending = $m2[2];
                }

                // tentar inferir o nome do campo a partir do contexto (quando o valor coincide)
                $field = null;
                if ($offending !== null) {
                    foreach ($contexto as $k => $v) {
                        if ((string)$v === (string)$offending) {
                            $field = $k;
                            break;
                        }
                    }
                }

                if ($field !== null) {
                    $userFriendlyMessage = "Valor inválido para {$field}: '{$offending}' não é compatível com o tipo {$type}. Verifique e tente novamente.";
                } elseif ($offending !== null) {
                    $userFriendlyMessage = "Valor inválido: '{$offending}' não é compatível com o tipo {$type}. Verifique os campos e tente novamente.";
                } else {
                    $userFriendlyMessage = "Valor inválido: um dos campos possui formato incompatível com o tipo esperado (ex.: inteiro). Verifique os dados e tente novamente.";
                }

                $httpStatusCode = 422;
                $errorCode = 'invalid_text_representation';
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

    public static function validarRegras(array $params, array $regrasValidacao, array $msg = []): array
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

            // Verificar se a chave de validação está presente nos params
            $chavePresente = array_key_exists($chave, $params);
            $valor = $chavePresente ? $params[$chave] : null;

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
                    if (!is_scalar($valor)) {
                        $errors[$chave][] = $msg["{$chave}.string"] ?? "O campo {$chave} deve ser texto.";
                    } else {
                        $valStr = (string)$valor;

                        // deve conter ao menos uma letra (Unicode-aware)
                        $temLetra = preg_match('/\p{L}/u', $valStr);

                        // rejeitar caracteres de controle e sequências que possam indicar/injetar SQL
                        // (NULL, caracteres ASCII de controle, ponto-e-vírgula, comentários SQL --, /* */)
                        $padraoPerigoso = '/[\x00-\x1F;]|--|\/\*|\*\//';

                        if (!$temLetra) {
                            $errors[$chave][] = $msg["{$chave}.string"] ?? "O campo {$chave} deve conter ao menos uma letra.";
                        } elseif (preg_match($padraoPerigoso, $valStr)) {
                            $errors[$chave][] = $msg["{$chave}.string"] ?? "O campo {$chave} contém caracteres inválidos que podem causar erro no banco de dados.";
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
        $contextoFiltrado = array_intersect_key($params, $regrasValidacao);

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
    public static function padronizarRespostaSucesso(array $data, string $msg, array $contexto = []): array
    {
        return [
            'msg'     => $msg,
            'detail'      => $data,
            'contexto'    => $contexto,
        ];
    }

    public static function Parametrizar(array $Params): array
    {
        $whereParams = [];
        $execParams = [];
        $optsParams = [];

        foreach ($Params as $key => $val) {
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
                    $whereParams[] = " AND $column IN (" . implode(', ', $placeholders) . ")";
                }
                continue;
            }

            // colunas de texto -> busca parcial
            if (str_starts_with($key, 'txt_') === true) {
                $whereParams[] = " AND $key ILIKE :$key";
                $execParams[':' . $key] = '%' . $val . '%';
                continue;
            }

            // flags booleanas
            if (str_starts_with($key, 'flg_') === true) {
                $whereParams[] = " AND $key = :$key";
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
            $whereParams[] = " AND $key = :$key";
            $execParams[':' . $key] = $val;
        }

        return ['whereParams' => $whereParams, 'execParams' => $execParams, 'optsParams' => $optsParams];
    }

    /**
     * Gera headers de segurança padrão para endpoints de autenticação
     *
     * @param string|null $requestId ID único da requisição
     * @return array Headers de segurança
     */
    public static function gerarHeadersSeguranca(?string $requestId = null): array
    {
        return [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'X-Request-Id' => $requestId ?? uniqid('auth-', true),
            'X-API-Version' => 'v1',
            'Content-Language' => 'pt-BR'
        ];
    }

    /**
     * Gera headers específicos para erros (RFC 9457)
     *
     * @param string|null $requestId ID único da requisição
     * @return array Headers para erro
     */
    public static function gerarHeadersErro(?string $requestId = null): array
    {
        $headersBase = self::gerarHeadersSeguranca($requestId);
        $headersBase['Content-Type'] = 'application/problem+json; charset=utf-8';

        return $headersBase;
    }

    /**
     * Gera headers de paginação baseado nos dados de entrada e resultados
     *
     * @param array $request Dados da requisição
     * @param int $totalRegistros Total de registros encontrados
     * @param string $baseUrl URL base para navegação
     * @return array Headers de paginação
     */
    public static function gerarHeadersPaginacao(array $request, int $totalRegistros, string $baseUrl): array
    {
        $limit = (int)($request['limit'] ?? null);
        $offset = (int)($request['offset'] ?? null);
        $currentPage = $limit ? floor($offset / $limit) + 1 : 1;

        $headers = [
            'X-Total-Count' => (string)$totalRegistros,
            'X-Page' => (string)$currentPage,
            'X-Per-Page' => (string)$limit
        ];

        $linkParts = [];
        $queryParams = $request;

        // Link para próxima página
        if ($totalRegistros > ($offset + $limit)) {
            $queryParams['offset'] = $offset + $limit;
            $nextUrl = $baseUrl . '?' . http_build_query($queryParams);
            $linkParts[] = "<{$nextUrl}>; rel=\"next\"";
        }

        // Link para página anterior
        if ($offset > 0) {
            $queryParams['offset'] = max(0, $offset - $limit);
            $prevUrl = $baseUrl . '?' . http_build_query($queryParams);
            $linkParts[] = "<{$prevUrl}>; rel=\"prev\"";

            // Link para primeira página
            $queryParams['offset'] = 0;
            $firstUrl = $baseUrl . '?' . http_build_query($queryParams);
            $linkParts[] = "<{$firstUrl}>; rel=\"first\"";
        }

        // Link para última página
        if ($totalRegistros > $limit) {
            $lastOffset = $limit ? floor(($totalRegistros - 1) / $limit) * $limit : 1;
            if ($lastOffset > $offset) {
                $queryParams['offset'] = $lastOffset;
                $lastUrl = $baseUrl . '?' . http_build_query($queryParams);
                $linkParts[] = "<{$lastUrl}>; rel=\"last\"";
            }
        }

        if (!empty($linkParts)) {
            $headers['Link'] = implode(', ', $linkParts);
        }

        return $headers;
    }


    /**
     * Processa resposta de erro com headers apropriados
     *
     * @param array $dadosErro Dados do erro (já formatados)
     * @param string|null $requestId ID da requisição
     * @return array Resposta de erro com headers
     */
    public static function processarRespostaErro(array $dadosErro, ?string $requestId = null): array
    {
        $headers = self::gerarHeadersErro($requestId);

        return [
            'dados' => $dadosErro,
            'status' => (int)$dadosErro['http_status'],
            'headers' => $headers
        ];
    }

    /**
     * Extrai ou gera Request ID da requisição
     *
     * @param \Illuminate\Http\Request $request Instância da requisição
     * @param string $prefixo Prefixo para o ID único
     * @return string Request ID
     */
    public static function extrairRequestId($request, string $prefixo = 'auth'): string
    {
        return $request->header('X-Request-Id', uniqid($prefixo . '-', true));
    }



    /**
     * Gera headers completos (segurança + paginação) para respostas de sucesso
     *
     * @param string $requestId Request ID único
     * @param array $requestData Dados da requisição para paginação
     * @param string $baseUrl URL base para links de paginação
     * @param array $dados Dados da resposta para cálculo de paginação
     * @return array Headers completos
     */
    public static function gerarHeadersCompletos(string $requestId, array $requestData, string $baseUrl, array $dados): array
    {
        // Headers de segurança base
        $headers = self::gerarHeadersSeguranca($requestId);

        // Adicionar headers de paginação se for listagem
        if (is_array($dados) && !isset($dados['http_status'])) {
            $headersPaginacao = self::gerarHeadersPaginacao($requestData, count($dados), $baseUrl);
            $headers = array_merge($headers, $headersPaginacao);
        }

        return $headers;
    }

    /**
     * Prepara, faz bind dos parâmetros (com tipo quando informado) e executa um comando PDO.
     * $bindings aceita:
     *  - valor simples: ':chave' => $valor
     *  - array com 'value' e opcional 'type': ':chave' => ['value' => $valor, 'type' => PDO::PARAM_INT]
     * Retorna o PDOStatement executado ou lança PDOException.
     *
     * @param PDO $pdo
     * @param string $consultaSql
     * @param array $bindings
     * @param array $contexto
     * @return PDOStatement
     * @throws PDOException
     */
    public static function prepararEExecutarComando(PDO $pdo, string $consultaSql, array $bindings = [], array $contexto = []): PDOStatement
    {
        $comando = $pdo->prepare($consultaSql);

        // Normalizar placeholders sem ':' para garantir consistência
        $normalizedBindings = [];
        foreach ($bindings as $chave => $valor) {
            $placeholder = strpos($chave, ':') === 0 ? $chave : ':' . $chave;
            $normalizedBindings[$placeholder] = $valor;
        }

        foreach ($normalizedBindings as $placeholder => $info) {
            if (is_array($info) && array_key_exists('value', $info)) {
                $tipo = $info['type'] ?? null;
                if ($tipo !== null) {
                    $comando->bindValue($placeholder, $info['value'], $tipo);
                } else {
                    $comando->bindValue($placeholder, $info['value']);
                }
            } else {
                // binding simples, usa bindValue sem tipo
                $comando->bindValue($placeholder, $info);
            }
        }

        // Executa sem parâmetros adicionais porque já bindamos tudo
        $comando->execute();

        return $comando;
    }

    /**
     * Inferir tipo PDO a partir do nome do campo.
     * Regras simples usadas em todo o projeto:
     *  - sufixo "_id" ou prefixo "id_" => PDO::PARAM_INT
     *  - prefixo "flg_" => PDO::PARAM_BOOL
     *  - default => PDO::PARAM_STR
     *
     * @param string $nomeCampo
     * @return int
     */
    public static function inferirTipoPorNome(string $nomeCampo): int
    {
        $nome = strtolower($nomeCampo);
        if (str_ends_with($nome, '_id') || str_starts_with($nome, 'id_')) {
            return PDO::PARAM_INT;
        }
        if (str_starts_with($nome, 'flg_')) {
            return PDO::PARAM_BOOL;
        }
        return PDO::PARAM_STR;
    }
}
