<?php

namespace App\Models\Auth;

use InvalidArgumentException;
use PDO;
use App\Services\Operations;

class Permissao
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ==============================
       CRUD PRINCIPAL DE PERMISSÃO
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
                    FROM auth.permissoes
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

    /** Busca 1 permissão por ID */
    function procurar_por_id(int $id_permissao): ?array
    {
        $contexto = ['id_permissao' => $id_permissao];
        $sql = "SELECT * FROM auth.permissoes
                WHERE id_permissao = :id AND dat_cancelamento_em IS NULL";
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute([':id' => $id_permissao]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
        }
    }

    /** Cria permissão (padronizado) */
    function Criar(array $params): array
    {
        // remover campos imutáveis se enviados por engano
        unset($params['id_permissao'], $params['dat_criado_em'], $params['dat_atualizado_em'], $params['dat_cancelamento_em']);

        // montar colunas e placeholders dinamicamente
        $colunas = array_keys($params);
        $placeholders = array_map(fn($c) => ':' . $c, $colunas);

        // montar comando SQL
        $comandoSql = "INSERT INTO auth.permissoes (" . implode(', ', $colunas) . ")\n                VALUES (" . implode(', ', $placeholders) . ") RETURNING *";

        // preparar bindings com inferência de tipo pelo nome
        $bindings = [];
        foreach ($params as $col => $valor) {
            $tipo = Operations::inferirTipoPorNome($col);
            $bindings[':' . $col] = ['value' => $valor, 'type' => $tipo];
        }

        try {
            $comando = Operations::prepararEExecutarComando(
                pdo: $this->pdo,
                consultaSql: $comandoSql,
                bindings: $bindings
            );

            return [
                'data' => $comando->fetch(PDO::FETCH_ASSOC),
                'message' => 'Permissão criada com sucesso.',
                'pdo_status' => 201
            ];
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, array_merge(['função' => 'Permissao::Criar'], $params));
        }
    }

    /** Atualiza permissão */
    function atualizar(int $id_permissao, array $data): array
    {
        $contexto = ['id_permissao' => $id_permissao, 'data' => $data];
        unset($data['dat_criado_em'], $data['dat_atualizado_em'], $data['dat_cancelamento_em'], $data['id_permissao']);
        if (!$data) throw new InvalidArgumentException('Nada para atualizar.');

        $sets = [];
        foreach ($data as $col => $_) $sets[] = "$col = :$col";

        $sql = "UPDATE auth.permissoes SET " . implode(', ', $sets) . "
                WHERE id_permissao = :id
                RETURNING *";

        try {
            $st = $this->pdo->prepare($sql);
            foreach ($data as $col => $val) $st->bindValue(":$col", $val);
            $st->bindValue(':id', $id_permissao, PDO::PARAM_INT);
            $st->execute();
            return $st->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
        }
    }

    /** Soft-delete */
    function remover_logicamente(int $id_permissao): bool
    {
        $contexto = ['id_permissao' => $id_permissao];
        $sql = "UPDATE auth.permissoes
                SET dat_cancelamento_em = now()
                WHERE id_permissao = :id AND dat_cancelamento_em IS NULL";
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute([':id' => $id_permissao]);
            return $st->rowCount() > 0;
        } catch (\PDOException $e) {
            Operations::mapearExcecaoPDO($e, array_merge(['função' => __METHOD__], $contexto));
            return false;
        }
    }
}
