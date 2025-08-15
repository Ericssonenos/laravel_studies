<?php

namespace App\Models\Auth;

use InvalidArgumentException;
use PDO;

class Usuario
{
    /* ==============================
       CRUD PRINCIPAL DE USUÁRIO
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
            $where[] = 'txt_nome_usuario ILIKE :nome';
            $params[':nome'] = '%' . $filtros['nome'] . '%';
        }
        if (!empty($filtros['email'])) {
            $where[] = 'txt_email_usuario ILIKE :email';
            $params[':email'] = '%' . $filtros['email'] . '%';
        }
        if (isset($filtros['ativo'])) {
            $where[] = 'flg_ativo_usuario = :ativo';
            $params[':ativo'] = (bool)$filtros['ativo'];
        }
        if (!empty($filtros['ids'])) {
            $ids = array_values(array_map('intval', $filtros['ids']));
            $in = implode(',', array_fill(0, count($ids), '?'));
            $where[] = "id_usuario IN ($in)";
        }

        $orderBy = $opts['order_by'] ?? 'id_usuario DESC';
        $limit   = isset($opts['limit'])  ? (int)$opts['limit']  : 50;
        $offset  = isset($opts['offset']) ? (int)$opts['offset'] : 0;

        $sql = "SELECT *
                FROM usuarios
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

    function procurar_por_id(PDO $pdo, int $id_usuario): ?array
    {
        $sql = "SELECT * FROM usuarios
                WHERE id_usuario = :id AND dat_cancelamento_em IS NULL";
        $st = $pdo->prepare($sql);
        $st->execute([':id' => $id_usuario]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    function inserir(PDO $pdo, int $locatario_id, string $nome, string $email, string $senha_hash, bool $ativo = true): array
    {
        $sql = "INSERT INTO usuarios (
                    locatario_id, txt_nome_usuario, txt_email_usuario, txt_senha_usuario, flg_ativo_usuario
                ) VALUES (
                    :loc, :nome, :email, :senha, :ativo
                ) RETURNING *";
        $st = $pdo->prepare($sql);
        $st->bindValue(':loc',   $locatario_id, PDO::PARAM_INT);
        $st->bindValue(':nome',  $nome);
        $st->bindValue(':email', $email);
        $st->bindValue(':senha', $senha_hash);
        $st->bindValue(':ativo', $ativo, PDO::PARAM_BOOL);
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    function atualizar(PDO $pdo, int $id_usuario, array $data): array
    {
        unset($data['dat_criado_em'], $data['dat_atualizado_em'], $data['dat_cancelamento_em'], $data['id_usuario']);
        if (!$data) throw new InvalidArgumentException('Nada para atualizar.');

        $sets = [];
        foreach ($data as $col => $_) $sets[] = "$col = :$col";

        $sql = "UPDATE usuarios SET " . implode(', ', $sets) . "
                WHERE id_usuario = :id
                RETURNING *";

        $st = $pdo->prepare($sql);
        foreach ($data as $col => $val) $st->bindValue(":$col", $val);
        $st->bindValue(':id', $id_usuario, PDO::PARAM_INT);
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    function remover_logicamente(PDO $pdo, int $id_usuario): bool
    {
        $sql = "UPDATE usuarios
                SET dat_cancelamento_em = now()
                WHERE id_usuario = :id AND dat_cancelamento_em IS NULL";
        $st = $pdo->prepare($sql);
        $st->execute([':id' => $id_usuario]);
        return $st->rowCount() > 0;
    }

    /* ==============================
       RELAÇÕES DE USUÁRIO
       ============================== */

    /** Atribui grupo a usuário */
    function atribuir_grupo(PDO $pdo, int $id_usuario, int $id_grupo): bool
    {
        $sql = "INSERT INTO usuarios_grupos (usuario_id, grupo_id)
                VALUES (:usuario, :grupo)
                ON CONFLICT (usuario_id, grupo_id) DO NOTHING";
        $st = $pdo->prepare($sql);
        return $st->execute([':usuario' => $id_usuario, ':grupo' => $id_grupo]);
    }

    /** Remove grupo de usuário */
    function remover_grupo(PDO $pdo, int $id_usuario, int $id_grupo): bool
    {
        $sql = "DELETE FROM usuarios_grupos
                WHERE usuario_id = :usuario AND grupo_id = :grupo";
        $st = $pdo->prepare($sql);
        $st->execute([':usuario' => $id_usuario, ':grupo' => $id_grupo]);
        return $st->rowCount() > 0;
    }

    /** Lista grupos do usuário */
    function listar_grupos(PDO $pdo, int $id_usuario): array
    {
        $sql = "SELECT g.*
                FROM grupos g
                INNER JOIN usuarios_grupos ug ON ug.grupo_id = g.id_grupo
                WHERE ug.usuario_id = :usuario
                  AND g.dat_cancelamento_em IS NULL";
        $st = $pdo->prepare($sql);
        $st->execute([':usuario' => $id_usuario]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Atribui papel a usuário */
    function atribuir_papel(PDO $pdo, int $id_usuario, int $id_papel): bool
    {
        $sql = "INSERT INTO usuarios_papeis (usuario_id, papel_id)
                VALUES (:usuario, :papel)
                ON CONFLICT (usuario_id, papel_id) DO NOTHING";
        $st = $pdo->prepare($sql);
        return $st->execute([':usuario' => $id_usuario, ':papel' => $id_papel]);
    }

    /** Remove papel de usuário */
    function remover_papel(PDO $pdo, int $id_usuario, int $id_papel): bool
    {
        $sql = "DELETE FROM usuarios_papeis
                WHERE usuario_id = :usuario AND papel_id = :papel";
        $st = $pdo->prepare($sql);
        $st->execute([':usuario' => $id_usuario, ':papel' => $id_papel]);
        return $st->rowCount() > 0;
    }

    /** Lista papéis do usuário */
    function listar_papeis(PDO $pdo, int $id_usuario): array
    {
        $sql = "SELECT p.*
                FROM papeis p
                INNER JOIN usuarios_papeis up ON up.papel_id = p.id_papel
                WHERE up.usuario_id = :usuario
                  AND p.dat_cancelamento_em IS NULL";
        $st = $pdo->prepare($sql);
        $st->execute([':usuario' => $id_usuario]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Atribui permissão a usuário */
    function atribuir_permissao(PDO $pdo, int $id_usuario, int $id_permissao): bool
    {
        $sql = "INSERT INTO usuarios_permissoes (usuario_id, permissao_id)
                VALUES (:usuario, :permissao)
                ON CONFLICT (usuario_id, permissao_id) DO NOTHING";
        $st = $pdo->prepare($sql);
        return $st->execute([':usuario' => $id_usuario, ':permissao' => $id_permissao]);
    }

    /** Remove permissão de usuário */
    function remover_permissao(PDO $pdo, int $id_usuario, int $id_permissao): bool
    {
        $sql = "DELETE FROM usuarios_permissoes
                WHERE usuario_id = :usuario AND permissao_id = :permissao";
        $st = $pdo->prepare($sql);
        $st->execute([':usuario' => $id_usuario, ':permissao' => $id_permissao]);
        return $st->rowCount() > 0;
    }

    /** Lista permissões do usuário */
    function listar_permissoes(PDO $pdo, int $id_usuario): array
    {
        $sql = "SELECT p.*
                FROM permissoes p
                INNER JOIN usuarios_permissoes up ON up.permissao_id = p.id_permissao
                WHERE up.usuario_id = :usuario
                  AND p.dat_cancelamento_em IS NULL";
        $st = $pdo->prepare($sql);
        $st->execute([':usuario' => $id_usuario]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
