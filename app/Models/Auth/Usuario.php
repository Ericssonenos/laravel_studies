<?php

namespace App\Models\Auth;

use InvalidArgumentException;
use App\Services\Operations;
use PDO;
use Illuminate\Support\Facades\DB;

class Usuario
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = DB::connection()->getPdo();
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
            return [
                'data' => $comando->fetchAll(PDO::FETCH_ASSOC),
                'pdo_status' => 200,
                'message' => 'Lista de usuários retornada com sucesso.'
            ];
        } catch (\PDOException $excecaoPDO) {
            // Devolve mensagem de erro, ação e parâmetros recebidos
            return Operations::mapearExcecaoPDO(
                $excecaoPDO,
                array_merge(['função' => __METHOD__], $params)
            );
        }
    }
    function Criar($params): array
    {
        // remover campos imutáveis se enviados por engano
        unset($params['id_usuario'], $params['dat_criado_em'], $params['dat_atualizado_em'], $params['dat_cancelamento_em']);

        // montar colunas e placeholders dinamicamente
        $colunas = array_keys($params);
        $placeholders = array_map(fn($c) => ':' . $c, $colunas);

        // montar comando SQL
        $comandoSql = "INSERT INTO auth.usuarios (" . implode(', ', $colunas) . ")\n                VALUES (" . implode(', ', $placeholders) . ") RETURNING *";

        // preparar bindings com inferência de tipo pelo nome
        $bindings = [];
        foreach ($params as $col => $valor) {
            $tipo = Operations::inferirTipoPorNome($col);
            $bindings[':' . $col] = ['value' => $valor, 'type' => $tipo];
        }

        try {
            $Comando = Operations::prepararEExecutarComando($this->pdo, $comandoSql, $bindings, $params);
            return [
                'data' => $Comando->fetch(PDO::FETCH_ASSOC),
                'message' => 'Usuário criado com sucesso.',
                'pdo_status' => 201
            ];
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $params));
        }
    }



    /** Atribui grupo a usuário */
    function AtribuirGrupo($params): array
    {
        // gerar query
        $comandoSql = "INSERT INTO auth.usuarios_grupos (usuario_id, grupo_id)
                VALUES (:usuario_id, :grupo_id)
                ON CONFLICT (usuario_id, grupo_id) DO NOTHING RETURNING *";

        // Preparar comando
        $comando = $this->pdo->prepare($comandoSql);


        try {
            // Executar comando
            $comando->execute([':usuario_id' => $params['id_usuario'], ':grupo_id' => $params['grupo_id']]);

            // Verificar se a atribuição foi bem-sucedida
            $retornoAtribuicao = $comando->fetch(PDO::FETCH_ASSOC);

            // Papel já atribuído ao usuário
            if (!$retornoAtribuicao) {
                return [
                    'data' => null,
                    'message' => 'Grupo já atribuído ao usuário.',
                    'pdo_status' => 409 // Conflito
                ];
            }
            // Papel atribuído com sucesso
            return [
                'data' => $comando->fetch(PDO::FETCH_ASSOC),
                'message' => 'Grupo atribuído ao usuário com sucesso.',
                'pdo_status' => 201
            ];
        } catch (\PDOException $e) {
            // Tratar exceção
            return Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $params));
        }
    }

    /** Atribui papel a usuário */
    function AtribuirPapel($params): array
    {
        // gerar query
        $comandoSql = "INSERT INTO auth.usuarios_papeis (usuario_id, papel_id)
                VALUES (:usuario_id, :papel_id)
                ON CONFLICT (usuario_id, papel_id) DO NOTHING RETURNING *";

        // Preparar comando
        $comando = $this->pdo->prepare($comandoSql);

        try {
            // Executar comando
             $comando->execute([':usuario_id' => $params['usuario_id'], ':papel_id' => $params['papel_id']]);

            // Obter retorno da atribuição
            $retornoAtribuicao = $comando->fetch(PDO::FETCH_ASSOC);

            // Verificar se a atribuição foi bem-sucedida
            if (!$retornoAtribuicao) {
                // Papel já atribuído ao usuário
                return [
                    'data' => null,
                    'message' => 'Papel já atribuído ao usuário',
                    'pdo_status' => 409 // Conflito
                ];
            }
            // Papel atribuído com sucesso
            return [
                'data' => $comando->fetch(PDO::FETCH_ASSOC),
                'message' => 'Papel atribuído ao usuário com sucesso.',
                'pdo_status' => 201
            ];
        } catch (\PDOException $e) {
            // Tratar exceção
            return Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $params));
        }
    }

    public function procurar_por_id(int $id_usuario): ?array
    {
        $contexto = ['id_usuario' => $id_usuario];
        $comandoSql = "SELECT * FROM auth.usuarios
                WHERE id_usuario = :id AND dat_cancelamento_em IS NULL";
        $st = $this->pdo->prepare($comandoSql);
        try {
            $st->execute([':id' => $id_usuario]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
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
