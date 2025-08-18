<?php

namespace App\Models\Auth;

use InvalidArgumentException;
use PDO;
use App\Services\Operations;

class Grupo
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ==============================
       CRUD PRINCIPAL DE GRUPO
       ============================== */

    public function Lista(array $params = []): array
    {
        // Parametriza os filtros de busca, ordenação e paginação
        $parametrizacao = Operations::Parametrizar($params);

        // Verifica se houve erro na parametrização
        if ($parametrizacao['statusParams'] !== 200) {
            return [
                'pdo_status' => $parametrizacao['statusParams'],
                'message' => $parametrizacao['message'],
                'data' => []
            ];
        }

        $whereParams = $parametrizacao['whereParams'];
        $optsParams  = $parametrizacao['optsParams'];
        $execParams  = $parametrizacao['execParams'];

        // Monta a consulta SQL
        $consultaSql = "SELECT *
                    FROM auth.grupos
                    WHERE dat_cancelamento_em IS NULL"
            . implode(' ', $whereParams)
            . ($optsParams['order_by'] ?? ' ')
            . ($optsParams['limit']    ?? ' ')
            . ($optsParams['offset']   ?? ' ');

        $comando = $this->pdo->prepare($consultaSql);

        try {
            $comando->execute($execParams);
            $rows = $comando->fetchAll(PDO::FETCH_ASSOC);
            return [
                'data' => $rows,
                'message' => 'Lista de grupos retornada com sucesso.',
                'pdo_status' => 200
            ];
        } catch (\PDOException $excecaoPDO) {
            // Devolve mensagem de erro, ação e parâmetros recebidos
            return Operations::mapearExcecaoPDO(
                $excecaoPDO,
                array_merge(['funcao' => __METHOD__], $params)
            );
        }
    }

    function procurar_por_id(int $id_grupo): ?array
    {
        $contexto = ['id_grupo' => $id_grupo];
        $sql = "SELECT * FROM auth.grupos
                WHERE id_grupo = :id AND dat_cancelamento_em IS NULL";
        $st = $this->pdo->prepare($sql);
        try {
            $st->execute([':id' => $id_grupo]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
        }
    }

        function Criar(array $params): array
        {
            if (!is_array($params) || empty($params)) {
                $errors = ['params' => ['Parâmetros inválidos para criação. Deve ser array não vazio.']];
                $firstMessage = reset($errors)[0] ?? 'Erro de validação nos dados enviados.';
                $contextoFiltrado = is_array($params) ? $params : [];
                return [
                    'pdo_status' => 422,
                    'error_code'  => 'validation_error',
                    'sqlstate'    => null,
                    'msg'     => $firstMessage,
                    'detail'      => $errors,
                    'contexto'    => $contextoFiltrado,
                ];
            }

            // remover campos imutáveis se enviados por engano
            unset($params['id_grupo'], $params['dat_criado_em'], $params['dat_atualizado_em'], $params['dat_cancelamento_em']);

            if (empty($params)) {
                $errors = ['params' => ['Nenhum campo restante para inserção.']];
                $firstMessage = reset($errors)[0] ?? 'Erro de validação nos dados enviados.';
                $contextoFiltrado = $params;
                return [
                    'pdo_status' => 422,
                    'error_code'  => 'validation_error',
                    'sqlstate'    => null,
                    'msg'     => $firstMessage,
                    'detail'      => $errors,
                    'contexto'    => $contextoFiltrado,
                ];
            }

            // montar colunas e placeholders dinamicamente
            $colunas = array_keys($params);
            $placeholders = array_map(fn($c) => ':' . $c, $colunas);

            $sql = "INSERT INTO auth.grupos (" . implode(', ', $colunas) . ")\n                VALUES (" . implode(', ', $placeholders) . ") RETURNING *";

            // preparar bindings com inferência de tipo pelo nome
            $bindings = [];
            foreach ($params as $col => $valor) {
                $tipo = Operations::inferirTipoPorNome($col);
                $bindings[':' . $col] = ['value' => $valor, 'type' => $tipo];
            }

            try {
                $st = Operations::prepararEExecutarComando($this->pdo, $sql, $bindings, $params);
                $dados = $st->fetch(PDO::FETCH_ASSOC);
                return $dados ?: [];
            } catch (\PDOException $e) {
                return Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $params));
            }
        }

    function atualizar(int $id_grupo, array $data): array
    {
        $contexto = ['id_grupo' => $id_grupo, 'data' => $data];
        unset($data['dat_criado_em'], $data['dat_atualizado_em'], $data['dat_cancelamento_em'], $data['id_grupo']);
        if (!$data) throw new InvalidArgumentException('Nada para atualizar.');

        $sets = [];
        foreach ($data as $col => $_) $sets[] = "$col = :$col";

        $sql = "UPDATE auth.grupos SET " . implode(', ', $sets) . "
                WHERE id_grupo = :id
                RETURNING *";

        $st = $this->pdo->prepare($sql);
        foreach ($data as $col => $val) $st->bindValue(":$col", $val);
        $st->bindValue(':id', $id_grupo, PDO::PARAM_INT);

        try {
            $st->execute();
            return $st->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
        }
    }

    function remover_logicamente(int $id_grupo): bool
    {
        $contexto = ['id_grupo' => $id_grupo];
        $sql = "UPDATE auth.grupos
                SET dat_cancelamento_em = now()
                WHERE id_grupo = :id AND dat_cancelamento_em IS NULL";
        $st = $this->pdo->prepare($sql);
        try {
            $st->execute([':id' => $id_grupo]);
            return $st->rowCount() > 0;
        } catch (\PDOException $e) {
            Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
            return false;
        }
    }

    /* ==============================
       RELAÇÃO grupos_papeis
       ============================== */

    /** Atribui papel a grupo */
    function AtribuirPapel($params): array
    {
        // gerar query
        $comandoSql = "INSERT INTO auth.grupos_papeis (grupo_id, papel_id)
                VALUES (:grupo_id, :papel_id)
                ON CONFLICT (grupo_id, papel_id) DO NOTHING RETURNING *";

        // Preparar comando
        $comando = $this->pdo->prepare($comandoSql);

        try {
            // Executar comando
            $comando->execute([':grupo_id' => $params['grupo_id'], ':papel_id' => $params['papel_id']]);

            // Obter retorno da atribuição
            $retornoAtribuicao = $comando->fetch(PDO::FETCH_ASSOC);

            // Verificar se a atribuição foi bem-sucedida
            if (!$retornoAtribuicao) {
                // Papel já atribuído ao grupo
                return [
                    'data' => null,
                    'message' => 'Papel já atribuído ao grupo',
                    'pdo_status' => 409 // Conflito
                ];
            }
            // Papel atribuído com sucesso
            return [
                'data' => $retornoAtribuicao,
                'message' => 'Papel atribuído ao grupo com sucesso.',
                'pdo_status' => 201
            ];
        } catch (\PDOException $e) {
            // Tratar exceção
            return Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $params));
        }
    }

    /** Remove um papel de um grupo */
    function remover_papel(int $id_grupo, int $id_papel): bool
    {
        $contexto = ['id_grupo' => $id_grupo, 'papel_id' => $id_papel];
        $sql = "DELETE FROM auth.grupos_papeis
                WHERE grupo_id = :grupo AND papel_id = :papel";
        $st = $this->pdo->prepare($sql);
        try {
            $st->execute([':grupo' => $id_grupo, ':papel' => $id_papel]);
            return $st->rowCount() > 0;
        } catch (\PDOException $e) {
            Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
            return false;
        }
    }

    /** Lista papéis de um grupo */
    function listar_papeis(int $id_grupo): array
    {
        $contexto = ['id_grupo' => $id_grupo];
        $sql = "SELECT p.*
                FROM auth.papeis p
                INNER JOIN auth.grupos_papeis gp ON gp.papel_id = p.id_papel
                WHERE gp.grupo_id = :grupo
                  AND p.dat_cancelamento_em IS NULL";
        $st = $this->pdo->prepare($sql);
        try {
            $st->execute([':grupo' => $id_grupo]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
        }
    }
}
