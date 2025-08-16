<?php

namespace App\Models\Auth;

use InvalidArgumentException;
use PDO;
use App\Services\Operations;

class Permissao
{
    /**
     * Busca dinâmica de permissões com paginação e ordenação.
     * $filtros: ['codigo'?, 'descricao'?, 'ativo'?, 'ids'?]
     * $opts: ['order_by' => 'cod_permissao ASC', 'limit' => 50, 'offset' => 0]
     */
    function procurar(PDO $pdo, array $filtros = [], array $opts = []): array
    {
        $where = ['dat_cancelamento_em IS NULL'];
        $params = [];

        if (!empty($filtros['codigo'])) {
            $where[] = 'cod_permissao ILIKE :codigo';
            $params[':codigo'] = '%' . $filtros['codigo'] . '%';
        }
        if (!empty($filtros['descricao'])) {
            $where[] = 'txt_descricao_permissao ILIKE :descricao';
            $params[':descricao'] = '%' . $filtros['descricao'] . '%';
        }
        if (isset($filtros['ativo'])) {
            $where[] = 'flg_ativo_permissao = :ativo';
            $params[':ativo'] = (bool)$filtros['ativo'];
        }
        if (!empty($filtros['ids'])) {
            $ids = array_values(array_map('intval', $filtros['ids']));
            $in = implode(',', array_fill(0, count($ids), '?'));
            $where[] = "id_permissao IN ($in)";
        }

        $orderBy = $opts['order_by'] ?? 'id_permissao DESC';
        $limit   = isset($opts['limit'])  ? (int)$opts['limit']  : 50;
        $offset  = isset($opts['offset']) ? (int)$opts['offset'] : 0;

        $sql = "SELECT *
                FROM auth.permissoes
                WHERE " . implode(' AND ', $where) . "
                ORDER BY $orderBy
                LIMIT :_limit OFFSET :_offset";

        try {
            $stmt = $pdo->prepare($sql);

            foreach ($params as $chave => $valor) {
                if ($chave !== ':ativo') {
                    $stmt->bindValue($chave, $valor);
                } else {
                    $stmt->bindValue($chave, $valor, PDO::PARAM_BOOL);
                }
            }

            if (!empty($filtros['ids'])) {
                $stmt = $pdo->prepare(str_replace("IN ($in)", "IN (" . implode(',', $ids) . ")", $sql));
                foreach ($params as $chave => $valor) $stmt->bindValue($chave, $valor);
            }

            $stmt->bindValue(':_limit',  $limit,  PDO::PARAM_INT);
            $stmt->bindValue(':_offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, [
                'funcao' => 'Permissao::procurar',
                'filtros' => $filtros,
            ]);
        }
    }

    /** Busca 1 permissão por ID */
    function procurar_por_id(PDO $pdo, int $id_permissao): ?array
    {
        $sql = "SELECT * FROM auth.permissoes
                WHERE id_permissao = :id AND dat_cancelamento_em IS NULL";
        try {
            $st = $pdo->prepare($sql);
            $st->execute([':id' => $id_permissao]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, [
                'funcao' => 'Permissao::procurar_por_id',
                'id_permissao' => $id_permissao,
            ]);
        }
    }

    /** Cria permissão */
    function inserir(PDO $pdo, string $codigo, ?string $descricao = null, bool $ativo = true): array
    {
        $sql = "INSERT INTO auth.permissoes (
                    cod_permissao, txt_descricao_permissao, flg_ativo_permissao
                ) VALUES (
                    :codigo, :descricao, :ativo
                ) RETURNING *";
        try {
            $st = $pdo->prepare($sql);
            $st->bindValue(':codigo', $codigo);
            $st->bindValue(':descricao', $descricao);
            $st->bindValue(':ativo', $ativo, PDO::PARAM_BOOL);
            $st->execute();
            return $st->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, [
                'funcao' => 'Permissao::inserir',
                'cod_permissao' => $codigo,
            ]);
        }
    }

    /** Atualiza permissão */
    function atualizar(PDO $pdo, int $id_permissao, array $data): array
    {
        unset($data['dat_criado_em'], $data['dat_atualizado_em'], $data['dat_cancelamento_em'], $data['id_permissao']);
        if (!$data) throw new InvalidArgumentException('Nada para atualizar.');

        $sets = [];
        foreach ($data as $col => $_) $sets[] = "$col = :$col";

        $sql = "UPDATE auth.permissoes SET " . implode(', ', $sets) . "
                WHERE id_permissao = :id
                RETURNING *";

        try {
            $st = $pdo->prepare($sql);
            foreach ($data as $col => $val) $st->bindValue(":$col", $val);
            $st->bindValue(':id', $id_permissao, PDO::PARAM_INT);
            $st->execute();
            return $st->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, [
                'funcao' => 'Permissao::atualizar',
                'id_permissao' => $id_permissao,
                'data' => $data,
            ]);
        }
    }

    /** Soft-delete */
    function remover_logicamente(PDO $pdo, int $id_permissao): bool
    {
        $sql = "UPDATE auth.permissoes
                SET dat_cancelamento_em = now()
                WHERE id_permissao = :id AND dat_cancelamento_em IS NULL";
        try {
            $st = $pdo->prepare($sql);
            $st->execute([':id' => $id_permissao]);
            return $st->rowCount() > 0;
        } catch (\PDOException $e) {
            Operations::mapearExcecaoPDO($e, [
                'funcao' => 'Permissao::remover_logicamente',
                'id_permissao' => $id_permissao,
            ]);
            return false;
        }
    }
}
