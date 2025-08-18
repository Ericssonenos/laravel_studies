<?php

namespace App\Models\Auth;

use InvalidArgumentException;
use PDO;
use App\Services\Operations;

class Papel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ==============================
       CRUD PRINCIPAL DE PAPEL
       ============================== */

    public function Lista(array $params = []): array
    {
        // Parametriza os filtros de busca, ordenação e paginação
        $parametrizacao = Operations::Parametrizar($params);
        $whereParams = $parametrizacao['whereParams'];
        $optsParams  = $parametrizacao['optsParams'];
        $execParams  = $parametrizacao['execParams'];

        // Monta a consulta SQL
        $consultaSql = "SELECT *
                    FROM auth.papeis
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
                array_merge(['funcao' => __METHOD__], $params)
            );
        }
    }
    function Criar($params): array
    {
        // montar colunas e placeholders dinamicos
        $colunas = array_keys($params);
        $placeholders = array_map(fn($col) => ":$col", $colunas);

        // montar comando SQL
        $comandoSql = "INSERT INTO auth.papeis (
                    " . implode(', ', $colunas) . "
                    )    VALUES (
                    " . implode(', ', $placeholders) . "
                    ) RETURNING *";

        // preparar bindings com inferência de tipo pelo nome
        $bindings = [];
        foreach ($params as $col => $valor) {
            $tipo = Operations::inferirTipoPorNome($col);
            $bindings[':' . $col] = ['value' => $valor, 'type' => $tipo];
        }

        try {

            // executar comando
            $comando = Operations::prepararEExecutarComando(
                pdo: $this->pdo,
                consultaSql: $comandoSql,
                bindings: $bindings
            );

            // retornar o resultado
            return [
                'data' => $comando->fetch(PDO::FETCH_ASSOC),
                'message' => 'Papel criado com sucesso.',
                'pdo_status' => 201
            ];
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, array_merge(['função' => 'Papel::Criar'], $params));
        }
    }
    function procurar_por_id(int $id_papel): ?array
    {
        $contexto = ['id_papel' => $id_papel];
        $sql = "SELECT * FROM auth.papeis
                WHERE id_papel = :id AND dat_cancelamento_em IS NULL";
        $st = $this->pdo->prepare($sql);
        try {
            $st->execute([':id' => $id_papel]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
        }
    }



    function atualizar(int $id_papel, array $data): array
    {
        $contexto = ['id_papel' => $id_papel, 'data' => $data];
        unset($data['dat_criado_em'], $data['dat_atualizado_em'], $data['dat_cancelamento_em'], $data['id_papel']);
        if (!$data) throw new InvalidArgumentException('Nada para atualizar.');

        $sets = [];
        foreach ($data as $col => $_) $sets[] = "$col = :$col";

        $sql = "UPDATE auth.papeis SET " . implode(', ', $sets) . "
                WHERE id_papel = :id
                RETURNING *";

        try {
            $st = $this->pdo->prepare($sql);
            foreach ($data as $col => $val) $st->bindValue(":$col", $val);
            $st->bindValue(':id', $id_papel, PDO::PARAM_INT);
            $st->execute();
            return $st->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
        }
    }

    function remover_logicamente(int $id_papel): bool
    {
        $contexto = ['id_papel' => $id_papel];
        $sql = "UPDATE auth.papeis
                SET dat_cancelamento_em = now()
                WHERE id_papel = :id AND dat_cancelamento_em IS NULL";
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute([':id' => $id_papel]);
            return $st->rowCount() > 0;
        } catch (\PDOException $e) {
            Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
            return false;
        }
    }

    /* ==============================
       RELAÇÃO papeis_permissoes
       ============================== */

    /** Atribui permissão a papel */
    function atribuir_permissao(int $id_papel, int $id_permissao): bool
    {
        $contexto = ['id_papel' => $id_papel, 'permissao_id' => $id_permissao];
        $sql = "INSERT INTO auth.papeis_permissoes (papel_id, permissao_id)
                VALUES (:papel, :permissao)
                ON CONFLICT (papel_id, permissao_id) DO NOTHING";
        try {
            $st = $this->pdo->prepare($sql);
            return $st->execute([':papel' => $id_papel, ':permissao' => $id_permissao]);
        } catch (\PDOException $e) {
            Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
            return false;
        }
    }

    /** Remove permissão de papel */
    function remover_permissao(int $id_papel, int $id_permissao): bool
    {
        $contexto = ['id_papel' => $id_papel, 'permissao_id' => $id_permissao];
        $sql = "DELETE FROM auth.papeis_permissoes
                WHERE papel_id = :papel AND permissao_id = :permissao";
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute([':papel' => $id_papel, ':permissao' => $id_permissao]);
            return $st->rowCount() > 0;
        } catch (\PDOException $e) {
            Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
            return false;
        }
    }

    /** Lista permissões do papel */
    function listar_permissoes(int $id_papel): array
    {
        $contexto = ['id_papel' => $id_papel];
        $sql = "SELECT p.*
                FROM auth.permissoes p
                INNER JOIN auth.papeis_permissoes pp ON pp.permissao_id = p.id_permissao
                WHERE pp.papel_id = :papel
                  AND p.dat_cancelamento_em IS NULL";
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute([':papel' => $id_papel]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
        }
    }
}
