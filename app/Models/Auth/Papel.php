<?php

namespace App\Models\Auth;

use InvalidArgumentException;
use PDO;

class Papel
{
    /* ==============================
       CRUD PRINCIPAL DE PAPEL
       ============================== */

    function procurar(PDO $pdo, array $filtros = [], array $opts = []): array
    {
        $where = ['dat_cancelamento_em IS NULL'];
        $params = [];

        if (isset($filtros['locatario_id'])) {
            $where[] = 'locatario_id = :locatario_id';
            $params[':locatario_id'] = (int)$filtros['locatario_id'];
        }
        if (!empty($filtros['nome'])) {
            $where[] = 'txt_nome_papel ILIKE :nome';
            $params[':nome'] = '%' . $filtros['nome'] . '%';
        }
        if (isset($filtros['ativo'])) {
            $where[] = 'flg_ativo_papel = :ativo';
            $params[':ativo'] = (bool)$filtros['ativo'];
        }
        if (!empty($filtros['ids'])) {
            $ids = array_values(array_map('intval', $filtros['ids']));
            $in = implode(',', array_fill(0, count($ids), '?'));
            $where[] = "id_papel IN ($in)";
        }

        $orderBy = $opts['order_by'] ?? 'id_papel DESC';
        $limit   = isset($opts['limit'])  ? (int)$opts['limit']  : 50;
        $offset  = isset($opts['offset']) ? (int)$opts['offset'] : 0;

        $sql = "SELECT *
                FROM papeis
                WHERE " . implode(' AND ', $where) . "
                ORDER BY $orderBy
                LIMIT :_limit OFFSET :_offset";

        $stmt = $pdo->prepare($sql);

        foreach ($params as $k => $v) {
            if ($k !== ':ativo') $stmt->bindValue($k, $v);
            else $stmt->bindValue($k, $v, PDO::PARAM_BOOL);
        }

        if (!empty($filtros['ids'])) {
            $stmt = $pdo->prepare(str_replace("IN ($in)", "IN (" . implode(',', $ids) . ")", $sql));
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        }

        $stmt->bindValue(':_limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':_offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    function procurar_por_id(PDO $pdo, int $id_papel): ?array
    {
        $sql = "SELECT * FROM papeis
                WHERE id_papel = :id AND dat_cancelamento_em IS NULL";
        $st = $pdo->prepare($sql);
        $st->execute([':id' => $id_papel]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    function inserir(PDO $pdo, int $locatario_id, string $nome, int $nivel, bool $ativo = true): array
    {
        $sql = "INSERT INTO papeis (
                    locatario_id, txt_nome_papel, num_nivel_papel, flg_ativo_papel
                ) VALUES (
                    :loc, :nome, :nivel, :ativo
                ) RETURNING *";
        $st = $pdo->prepare($sql);
        $st->bindValue(':loc',   $locatario_id, PDO::PARAM_INT);
        $st->bindValue(':nome',  $nome);
        $st->bindValue(':nivel', $nivel, PDO::PARAM_INT);
        $st->bindValue(':ativo', $ativo, PDO::PARAM_BOOL);
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    function atualizar(PDO $pdo, int $id_papel, array $data): array
    {
        unset($data['dat_criado_em'], $data['dat_atualizado_em'], $data['dat_cancelamento_em'], $data['id_papel']);
        if (!$data) throw new InvalidArgumentException('Nada para atualizar.');

        $sets = [];
        foreach ($data as $col => $_) $sets[] = "$col = :$col";

        $sql = "UPDATE papeis SET " . implode(', ', $sets) . "
                WHERE id_papel = :id
                RETURNING *";

        $st = $pdo->prepare($sql);
        foreach ($data as $col => $val) $st->bindValue(":$col", $val);
        $st->bindValue(':id', $id_papel, PDO::PARAM_INT);
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    function remover_logicamente(PDO $pdo, int $id_papel): bool
    {
        $sql = "UPDATE papeis
                SET dat_cancelamento_em = now()
                WHERE id_papel = :id AND dat_cancelamento_em IS NULL";
        $st = $pdo->prepare($sql);
        $st->execute([':id' => $id_papel]);
        return $st->rowCount() > 0;
    }

    /* ==============================
       RELAÇÃO papeis_permissoes
       ============================== */

    /** Atribui permissão a papel */
    function atribuir_permissao(PDO $pdo, int $id_papel, int $id_permissao): bool
    {
        $sql = "INSERT INTO papeis_permissoes (papel_id, permissao_id)
                VALUES (:papel, :permissao)
                ON CONFLICT (papel_id, permissao_id) DO NOTHING";
        $st = $pdo->prepare($sql);
        return $st->execute([':papel' => $id_papel, ':permissao' => $id_permissao]);
    }

    /** Remove permissão de papel */
    function remover_permissao(PDO $pdo, int $id_papel, int $id_permissao): bool
    {
        $sql = "DELETE FROM papeis_permissoes
                WHERE papel_id = :papel AND permissao_id = :permissao";
        $st = $pdo->prepare($sql);
        $st->execute([':papel' => $id_papel, ':permissao' => $id_permissao]);
        return $st->rowCount() > 0;
    }

    /** Lista permissões do papel */
    function listar_permissoes(PDO $pdo, int $id_papel): array
    {
        $sql = "SELECT p.*
                FROM permissoes p
                INNER JOIN papeis_permissoes pp ON pp.permissao_id = p.id_permissao
                WHERE pp.papel_id = :papel
                  AND p.dat_cancelamento_em IS NULL";
        $st = $pdo->prepare($sql);
        $st->execute([':papel' => $id_papel]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
