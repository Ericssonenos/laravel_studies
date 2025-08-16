<?php

namespace App\Models\Auth;

use InvalidArgumentException;
use PDO;

class Grupo
{
    /* ==============================
       CRUD PRINCIPAL DE GRUPO
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
            $where[] = 'txt_nome_grupo ILIKE :nome';
            $params[':nome'] = '%' . $filtros['nome'] . '%';
        }
        if (isset($filtros['ativo'])) {
            $where[] = 'flg_ativo_grupo = :ativo';
            $params[':ativo'] = (bool)$filtros['ativo'];
        }
        if (!empty($filtros['ids'])) {
            $ids = array_values(array_map('intval', $filtros['ids']));
            $in = implode(',', array_fill(0, count($ids), '?'));
            $where[] = "id_grupo IN ($in)";
        }

        $orderBy = $opts['order_by'] ?? 'id_grupo DESC';
        $limit   = isset($opts['limit'])  ? (int)$opts['limit']  : 50;
        $offset  = isset($opts['offset']) ? (int)$opts['offset'] : 0;

        $sql = "SELECT *
                FROM auth.grupos
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

    function procurar_por_id(PDO $pdo, int $id_grupo): ?array
    {
        $sql = "SELECT * FROM auth.grupos
                WHERE id_grupo = :id AND dat_cancelamento_em IS NULL";
        $st = $pdo->prepare($sql);
        $st->execute([':id' => $id_grupo]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    function inserir(PDO $pdo, int $locatario_id, string $nome, ?string $descricao = null, bool $ativo = true): array
    {
        $sql = "INSERT INTO auth.grupos (
                    locatario_id, txt_nome_grupo, txt_descricao_grupo, flg_ativo_grupo
                ) VALUES (
                    :loc, :nome, :desc, :ativo
                ) RETURNING *";
        $st = $pdo->prepare($sql);
        $st->bindValue(':loc',  $locatario_id, PDO::PARAM_INT);
        $st->bindValue(':nome', $nome);
        $st->bindValue(':desc', $descricao);
        $st->bindValue(':ativo', $ativo, PDO::PARAM_BOOL);
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    function atualizar(PDO $pdo, int $id_grupo, array $data): array
    {
        unset($data['dat_criado_em'], $data['dat_atualizado_em'], $data['dat_cancelamento_em'], $data['id_grupo']);
        if (!$data) throw new InvalidArgumentException('Nada para atualizar.');

        $sets = [];
        foreach ($data as $col => $_) $sets[] = "$col = :$col";

        $sql = "UPDATE auth.grupos SET " . implode(', ', $sets) . "
                WHERE id_grupo = :id
                RETURNING *";

        $st = $pdo->prepare($sql);
        foreach ($data as $col => $val) $st->bindValue(":$col", $val);
        $st->bindValue(':id', $id_grupo, PDO::PARAM_INT);
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    function remover_logicamente(PDO $pdo, int $id_grupo): bool
    {
        $sql = "UPDATE auth.grupos
                SET dat_cancelamento_em = now()
                WHERE id_grupo = :id AND dat_cancelamento_em IS NULL";
        $st = $pdo->prepare($sql);
        $st->execute([':id' => $id_grupo]);
        return $st->rowCount() > 0;
    }

    /* ==============================
       RELAÇÃO grupos_papeis
       ============================== */

    /** Atribui um papel a um grupo (evita duplicata) */
    function atribuir_papel(PDO $pdo, int $id_grupo, int $id_papel): bool
    {
        $sql = "INSERT INTO auth.grupos_papeis (grupo_id, papel_id)
                VALUES (:grupo, :papel)
                ON CONFLICT (grupo_id, papel_id) DO NOTHING";
        $st = $pdo->prepare($sql);
        return $st->execute([':grupo' => $id_grupo, ':papel' => $id_papel]);
    }

    /** Remove um papel de um grupo */
    function remover_papel(PDO $pdo, int $id_grupo, int $id_papel): bool
    {
        $sql = "DELETE FROM auth.grupos_papeis
                WHERE grupo_id = :grupo AND papel_id = :papel";
        $st = $pdo->prepare($sql);
        $st->execute([':grupo' => $id_grupo, ':papel' => $id_papel]);
        return $st->rowCount() > 0;
    }

    /** Lista papéis de um grupo */
    function listar_papeis(PDO $pdo, int $id_grupo): array
    {
        $sql = "SELECT p.*
                FROM auth.papeis p
                INNER JOIN auth.grupos_papeis gp ON gp.papel_id = p.id_papel
                WHERE gp.grupo_id = :grupo
                  AND p.dat_cancelamento_em IS NULL";
        $st = $pdo->prepare($sql);
        $st->execute([':grupo' => $id_grupo]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
