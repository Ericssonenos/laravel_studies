<?php

namespace App\Models\Auth;

use InvalidArgumentException;
use App\Services\Operations;
use PDO;

class Usuario
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function Lista(array $params = []): array
    {
        // Parametriza os filtros de busca, ordenação e paginação
        $parametrizacao = Operations::Parametrizar($params);
        $whereParams = $parametrizacao['whereParams'];
        $optsParams  = $parametrizacao['optsParams'];
        $execParams  = $parametrizacao['execParams'];

        // Monta a consulta SQL
        $consultaSql = "SELECT *
                    FROM auth.usuarios
                    WHERE dat_cancelamento_em IS NULL"
            . implode(' ', $whereParams)
            . ($optsParams['order_by'] ?? ' ')
            . ($optsParams['limit']    ?? ' ')
            . ($optsParams['offset']   ?? ' ');

        $comando = $this->pdo->prepare($consultaSql);

        try {
            $comando->execute($execParams);
            return $comando->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $excecaoPDO) {
            // Devolve mensagem de erro, ação e parâmetros recebidos
            return Operations::mapearExcecaoPDO(
                $excecaoPDO,
                array_merge(['função' => __METHOD__], $params)
            );
        }
    }

    public function procurar_por_id(int $id_usuario): ?array
    {
        $contexto = ['id_usuario' => $id_usuario];
        $sql = "SELECT * FROM auth.usuarios
                WHERE id_usuario = :id AND dat_cancelamento_em IS NULL";
        $st = $this->pdo->prepare($sql);
        try {
            $st->execute([':id' => $id_usuario]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
        }
    }

    function Criar($params): array
    {

        // Aqui os parâmetros são dinâmicos porque a validação de regras como
        // "required", "min", "max", etc., ocorre no Controller antes de chamar este método.

        if (!is_array($params) || empty($params)) {
            $errors = ['params' => ['Parâmetros inválidos para criação. Deve ser array não vazio.']];
            // pega a primeira mensagem amigável
            $firstMessage = reset($errors)[0] ?? 'Erro de validação nos dados enviados.';
            $contextoFiltrado = is_array($params) ? $params : [];
            return [
                'http_status' => 422,
                'error_code'  => 'validation_error',
                'sqlstate'    => null,
                'msg'     => $firstMessage,
                'detail'      => $errors,
                'contexto'    => $contextoFiltrado,
            ];
        }

        // remover campos imutáveis se enviados por engano
        unset($params['id_usuario'], $params['dat_criado_em'], $params['dat_atualizado_em'], $params['dat_cancelamento_em']);

        if (empty($params)) {
            $errors = ['params' => ['Nenhum campo restante para inserção.']];
            // pega a primeira mensagem amigável
            $firstMessage = reset($errors)[0] ?? 'Erro de validação nos dados enviados.';
            $contextoFiltrado = $params;
            return [
                'http_status' => 422,
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

        $sql = "INSERT INTO auth.usuarios (" . implode(', ', $colunas) . ")\n                VALUES (" . implode(', ', $placeholders) . ") RETURNING *";

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

    function atualizar(int $id_usuario, array $data): array
    {
        $contexto = ['id_usuario' => $id_usuario, 'data' => $data];
        unset($data['dat_criado_em'], $data['dat_atualizado_em'], $data['dat_cancelamento_em'], $data['id_usuario']);
        if (!$data) throw new InvalidArgumentException('Nada para atualizar.');

        $sets = [];
        foreach ($data as $col => $_) $sets[] = "$col = :$col";

        $sql = "UPDATE auth.usuarios SET " . implode(', ', $sets) . "
                WHERE id_usuario = :id
                RETURNING *";

        $st = $this->pdo->prepare($sql);
        foreach ($data as $col => $val) $st->bindValue(":$col", $val);
        $st->bindValue(':id', $id_usuario, PDO::PARAM_INT);

        try {
            $st->execute();
            return $st->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
        }
    }

    function remover_logicamente(int $id_usuario): bool
    {
        $contexto = ['id_usuario' => $id_usuario];
        $sql = "UPDATE auth.usuarios
                SET dat_cancelamento_em = now()
                WHERE id_usuario = :id AND dat_cancelamento_em IS NULL";
        $st = $this->pdo->prepare($sql);
        try {
            $st->execute([':id' => $id_usuario]);
            return $st->rowCount() > 0;
        } catch (\PDOException $e) {
            Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
            return false;
        }
    }

    /* ==============================
       RELAÇÕES DE USUÁRIO
       ============================== */

    /** Atribui grupo a usuário */
    function atribuir_grupo(int $id_usuario, int $id_grupo): bool
    {
        $contexto = ['id_usuario' => $id_usuario, 'grupo_id' => $id_grupo];
        $sql = "INSERT INTO auth.usuarios_grupos (usuario_id, grupo_id)
                VALUES (:usuario, :grupo)
                ON CONFLICT (usuario_id, grupo_id) DO NOTHING";
        $st = $this->pdo->prepare($sql);
        try {
            return $st->execute([':usuario' => $id_usuario, ':grupo' => $id_grupo]);
        } catch (\PDOException $e) {
            Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
            return false;
        }
    }

    /** Remove grupo de usuário */
    function remover_grupo(int $id_usuario, int $id_grupo): bool
    {
        $contexto = ['id_usuario' => $id_usuario, 'grupo_id' => $id_grupo];
        $sql = "DELETE FROM auth.usuarios_grupos
                WHERE usuario_id = :usuario AND grupo_id = :grupo";
        $st = $this->pdo->prepare($sql);
        try {
            $st->execute([':usuario' => $id_usuario, ':grupo' => $id_grupo]);
            return $st->rowCount() > 0;
        } catch (\PDOException $e) {
            Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
            return false;
        }
    }

    /** Lista grupos do usuário */
    function listar_grupos(int $id_usuario): array
    {
        $contexto = ['id_usuario' => $id_usuario];
        $sql = "SELECT g.*
                FROM auth.grupos g
                INNER JOIN auth.usuarios_grupos ug ON ug.grupo_id = g.id_grupo
                WHERE ug.usuario_id = :usuario
                  AND g.dat_cancelamento_em IS NULL";
        $st = $this->pdo->prepare($sql);
        try {
            $st->execute([':usuario' => $id_usuario]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
        }
    }

    /** Atribui papel a usuário */
    function atribuir_papel(int $id_usuario, int $id_papel): bool
    {
        $contexto = ['id_usuario' => $id_usuario, 'papel_id' => $id_papel];
        $sql = "INSERT INTO auth.usuarios_papeis (usuario_id, papel_id)
                VALUES (:usuario, :papel)
                ON CONFLICT (usuario_id, papel_id) DO NOTHING";
        $st = $this->pdo->prepare($sql);
        try {
            return $st->execute([':usuario' => $id_usuario, ':papel' => $id_papel]);
        } catch (\PDOException $e) {
            Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
            return false;
        }
    }

    /** Remove papel de usuário */
    function remover_papel(int $id_usuario, int $id_papel): bool
    {
        $contexto = ['id_usuario' => $id_usuario, 'papel_id' => $id_papel];
        $sql = "DELETE FROM auth.usuarios_papeis
                WHERE usuario_id = :usuario AND papel_id = :papel";
        $st = $this->pdo->prepare($sql);
        try {
            $st->execute([':usuario' => $id_usuario, ':papel' => $id_papel]);
            return $st->rowCount() > 0;
        } catch (\PDOException $e) {
            Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
            return false;
        }
    }

    /** Lista papéis do usuário */
    function listar_papeis(int $id_usuario): array
    {
        $contexto = ['id_usuario' => $id_usuario];
        $sql = "SELECT p.*
                FROM auth.papeis p
                INNER JOIN auth.usuarios_papeis up ON up.papel_id = p.id_papel
                WHERE up.usuario_id = :usuario
                  AND p.dat_cancelamento_em IS NULL";
        $st = $this->pdo->prepare($sql);
        try {
            $st->execute([':usuario' => $id_usuario]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
        }
    }

    /** Atribui permissão a usuário */
    function atribuir_permissao(int $id_usuario, int $id_permissao): bool
    {
        $contexto = ['id_usuario' => $id_usuario, 'permissao_id' => $id_permissao];
        $sql = "INSERT INTO auth.usuarios_permissoes (usuario_id, permissao_id)
                VALUES (:usuario, :permissao)
                ON CONFLICT (usuario_id, permissao_id) DO NOTHING";
        $st = $this->pdo->prepare($sql);
        try {
            return $st->execute([':usuario' => $id_usuario, ':permissao' => $id_permissao]);
        } catch (\PDOException $e) {
            Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
            return false;
        }
    }

    /** Remove permissão de usuário */
    function remover_permissao(int $id_usuario, int $id_permissao): bool
    {
        $contexto = ['id_usuario' => $id_usuario, 'permissao_id' => $id_permissao];
        $sql = "DELETE FROM auth.usuarios_permissoes
                WHERE usuario_id = :usuario AND permissao_id = :permissao";
        $st = $this->pdo->prepare($sql);
        try {
            $st->execute([':usuario' => $id_usuario, ':permissao' => $id_permissao]);
            return $st->rowCount() > 0;
        } catch (\PDOException $e) {
            Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
            return false;
        }
    }

    /** Lista permissões do usuário */
    function listar_permissoes(int $id_usuario): array
    {
        $contexto = ['id_usuario' => $id_usuario];
        $sql = "SELECT p.*
                FROM auth.permissoes p
                INNER JOIN auth.usuarios_permissoes up ON up.permissao_id = p.id_permissao
                WHERE up.usuario_id = :usuario
                  AND p.dat_cancelamento_em IS NULL";
        $st = $this->pdo->prepare($sql);
        try {
            $st->execute([':usuario' => $id_usuario]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
        }
    }
}
