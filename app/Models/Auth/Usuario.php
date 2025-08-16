<?php

namespace App\Models\Auth;

use InvalidArgumentException;
use App\Services\Operations;
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
        $contexto = [];

        if (isset($filtros['locatario_id'])) {
            $where[] = 'locatario_id = :locatario_id';
            $params[':locatario_id'] = (int)$filtros['locatario_id'];
            $contexto['locatario_id'] = $filtros['locatario_id'];
        }
        if (!empty($filtros['nome'])) {
            $where[] = 'txt_nome_usuario ILIKE :nome';
            $params[':nome'] = '%' . $filtros['nome'] . '%';
            $contexto['nome'] = $filtros['nome'];
        }
        if (!empty($filtros['email'])) {
            $where[] = 'txt_email_usuario ILIKE :email';
            $params[':email'] = '%' . $filtros['email'] . '%';
            $contexto['email'] = $filtros['email'];
        }
        if (isset($filtros['ativo'])) {
            $where[] = 'flg_ativo_usuario = :ativo';
            $params[':ativo'] = (bool)$filtros['ativo'];
            $contexto['ativo'] = $filtros['ativo'];
        }
        if (!empty($filtros['ids'])) {
            $ids = array_values(array_map('intval', $filtros['ids']));
            $in = implode(',', array_fill(0, count($ids), '?'));
            $where[] = "id_usuario IN ($in)";
            $contexto['ids'] = $filtros['ids'];
        }

        $orderBy = $opts['order_by'] ?? 'id_usuario DESC';
        $limit   = isset($opts['limit'])  ? (int)$opts['limit']  : 1000;
        $offset  = isset($opts['offset']) ? (int)$opts['offset'] : 0;

        $sql = "SELECT *
                FROM auth.usuarios
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

        try {
        $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, $contexto);
        }
    }

    function procurar_por_id(PDO $pdo, int $id_usuario): ?array
    {
        $contexto = ['id_usuario' => $id_usuario];
        $sql = "SELECT * FROM auth.usuarios
                WHERE id_usuario = :id AND dat_cancelamento_em IS NULL";
        $st = $pdo->prepare($sql);
        try {
            $st->execute([':id' => $id_usuario]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, $contexto);
        }
    }

    function inserir(PDO $pdo, int $locatario_id, string $nome, string $email, string $senha_hash, bool $ativo = true): array
    {
        $contexto = ['locatario_id' => $locatario_id, 'email' => $email];
        $sql = "INSERT INTO auth.usuarios (
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

        try {
            $st->execute();
            $dados = $st->fetch(PDO::FETCH_ASSOC);
            return [
                'http_status' => 201,
                'error_code'  => null,
                'sqlstate'    => null,
                'message'     => 'Usuário criado com sucesso.',
                'detail'      => $dados,
                'contexto'    => $contexto,
            ];
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, $contexto);
        }
    }

    function atualizar(PDO $pdo, int $id_usuario, array $data): array
    {
        $contexto = ['id_usuario' => $id_usuario, 'data' => $data];
        unset($data['dat_criado_em'], $data['dat_atualizado_em'], $data['dat_cancelamento_em'], $data['id_usuario']);
        if (!$data) throw new InvalidArgumentException('Nada para atualizar.');

        $sets = [];
        foreach ($data as $col => $_) $sets[] = "$col = :$col";

        $sql = "UPDATE auth.usuarios SET " . implode(', ', $sets) . "
                WHERE id_usuario = :id
                RETURNING *";

        $st = $pdo->prepare($sql);
        foreach ($data as $col => $val) $st->bindValue(":$col", $val);
        $st->bindValue(':id', $id_usuario, PDO::PARAM_INT);

        try {
            $st->execute();
            return $st->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, $contexto);
        }
    }

    function remover_logicamente(PDO $pdo, int $id_usuario): bool
    {
        $contexto = ['id_usuario' => $id_usuario];
        $sql = "UPDATE auth.usuarios
                SET dat_cancelamento_em = now()
                WHERE id_usuario = :id AND dat_cancelamento_em IS NULL";
        $st = $pdo->prepare($sql);
        try {
            $st->execute([':id' => $id_usuario]);
            return $st->rowCount() > 0;
        } catch (\PDOException $e) {
            Operations::mapearExcecaoPDO($e, $contexto);
            return false;
        }
    }

    /* ==============================
       RELAÇÕES DE USUÁRIO
       ============================== */

    /** Atribui grupo a usuário */
    function atribuir_grupo(PDO $pdo, int $id_usuario, int $id_grupo): bool
    {
        $contexto = ['id_usuario' => $id_usuario, 'grupo_id' => $id_grupo];
        $sql = "INSERT INTO auth.usuarios_grupos (usuario_id, grupo_id)
                VALUES (:usuario, :grupo)
                ON CONFLICT (usuario_id, grupo_id) DO NOTHING";
        $st = $pdo->prepare($sql);
        try {
            return $st->execute([':usuario' => $id_usuario, ':grupo' => $id_grupo]);
        } catch (\PDOException $e) {
            Operations::mapearExcecaoPDO($e, $contexto);
            return false;
        }
    }

    /** Remove grupo de usuário */
    function remover_grupo(PDO $pdo, int $id_usuario, int $id_grupo): bool
    {
        $contexto = ['id_usuario' => $id_usuario, 'grupo_id' => $id_grupo];
        $sql = "DELETE FROM auth.usuarios_grupos
                WHERE usuario_id = :usuario AND grupo_id = :grupo";
        $st = $pdo->prepare($sql);
        try {
            $st->execute([':usuario' => $id_usuario, ':grupo' => $id_grupo]);
            return $st->rowCount() > 0;
        } catch (\PDOException $e) {
            Operations::mapearExcecaoPDO($e, $contexto);
            return false;
        }
    }

    /** Lista grupos do usuário */
    function listar_grupos(PDO $pdo, int $id_usuario): array
    {
        $contexto = ['id_usuario' => $id_usuario];
        $sql = "SELECT g.*
                FROM auth.grupos g
                INNER JOIN auth.usuarios_grupos ug ON ug.grupo_id = g.id_grupo
                WHERE ug.usuario_id = :usuario
                  AND g.dat_cancelamento_em IS NULL";
        $st = $pdo->prepare($sql);
        try {
            $st->execute([':usuario' => $id_usuario]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, $contexto);
        }
    }

    /** Atribui papel a usuário */
    function atribuir_papel(PDO $pdo, int $id_usuario, int $id_papel): bool
    {
        $contexto = ['id_usuario' => $id_usuario, 'papel_id' => $id_papel];
        $sql = "INSERT INTO auth.usuarios_papeis (usuario_id, papel_id)
                VALUES (:usuario, :papel)
                ON CONFLICT (usuario_id, papel_id) DO NOTHING";
        $st = $pdo->prepare($sql);
        try {
            return $st->execute([':usuario' => $id_usuario, ':papel' => $id_papel]);
        } catch (\PDOException $e) {
            Operations::mapearExcecaoPDO($e, $contexto);
            return false;
        }
    }

    /** Remove papel de usuário */
    function remover_papel(PDO $pdo, int $id_usuario, int $id_papel): bool
    {
        $contexto = ['id_usuario' => $id_usuario, 'papel_id' => $id_papel];
        $sql = "DELETE FROM auth.usuarios_papeis
                WHERE usuario_id = :usuario AND papel_id = :papel";
        $st = $pdo->prepare($sql);
        try {
            $st->execute([':usuario' => $id_usuario, ':papel' => $id_papel]);
            return $st->rowCount() > 0;
        } catch (\PDOException $e) {
            Operations::mapearExcecaoPDO($e, $contexto);
            return false;
        }
    }

    /** Lista papéis do usuário */
    function listar_papeis(PDO $pdo, int $id_usuario): array
    {
        $contexto = ['id_usuario' => $id_usuario];
        $sql = "SELECT p.*
                FROM auth.papeis p
                INNER JOIN auth.usuarios_papeis up ON up.papel_id = p.id_papel
                WHERE up.usuario_id = :usuario
                  AND p.dat_cancelamento_em IS NULL";
        $st = $pdo->prepare($sql);
        try {
            $st->execute([':usuario' => $id_usuario]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, $contexto);
        }
    }

    /** Atribui permissão a usuário */
    function atribuir_permissao(PDO $pdo, int $id_usuario, int $id_permissao): bool
    {
        $contexto = ['id_usuario' => $id_usuario, 'permissao_id' => $id_permissao];
        $sql = "INSERT INTO auth.usuarios_permissoes (usuario_id, permissao_id)
                VALUES (:usuario, :permissao)
                ON CONFLICT (usuario_id, permissao_id) DO NOTHING";
        $st = $pdo->prepare($sql);
        try {
            return $st->execute([':usuario' => $id_usuario, ':permissao' => $id_permissao]);
        } catch (\PDOException $e) {
            Operations::mapearExcecaoPDO($e, $contexto);
            return false;
        }
    }

    /** Remove permissão de usuário */
    function remover_permissao(PDO $pdo, int $id_usuario, int $id_permissao): bool
    {
        $contexto = ['id_usuario' => $id_usuario, 'permissao_id' => $id_permissao];
        $sql = "DELETE FROM auth.usuarios_permissoes
                WHERE usuario_id = :usuario AND permissao_id = :permissao";
        $st = $pdo->prepare($sql);
        try {
            $st->execute([':usuario' => $id_usuario, ':permissao' => $id_permissao]);
            return $st->rowCount() > 0;
        } catch (\PDOException $e) {
            Operations::mapearExcecaoPDO($e, $contexto);
            return false;
        }
    }

    /** Lista permissões do usuário */
    function listar_permissoes(PDO $pdo, int $id_usuario): array
    {
        $contexto = ['id_usuario' => $id_usuario];
        $sql = "SELECT p.*
                FROM auth.permissoes p
                INNER JOIN auth.usuarios_permissoes up ON up.permissao_id = p.id_permissao
                WHERE up.usuario_id = :usuario
                  AND p.dat_cancelamento_em IS NULL";
        $st = $pdo->prepare($sql);
        try {
            $st->execute([':usuario' => $id_usuario]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, $contexto);
        }
    }
}
