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
            return $comando->fetchAll(PDO::FETCH_ASSOC);
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
            return Operations::mapearExcecaoPDO($e, array_merge(['funcao' => 'Grupo::procurar_por_id'], $contexto));
        }
    }

    function Criar(array $params): array
    {
        $sql = "INSERT INTO auth.grupos (
                    locatario_id, txt_nome_grupo, txt_descricao_grupo, flg_ativo_grupo
                ) VALUES (
                    :locatario_id, :txt_nome_grupo, :txt_descricao_grupo, :flg_ativo_grupo
                ) RETURNING *";

        $st = $this->pdo->prepare($sql);
        $st->bindValue(':locatario_id',         $params["locatario_id"], PDO::PARAM_INT);
        $st->bindValue(':txt_nome_grupo',       $params["txt_nome_grupo"]);
        $st->bindValue(':txt_descricao_grupo',  $params["txt_descricao_grupo"]);
        $st->bindValue(':flg_ativo_grupo',      $params["flg_ativo_grupo"], PDO::PARAM_BOOL);

        try {
            $st->execute();
            $dados = $st->fetch(PDO::FETCH_ASSOC);
            return $dados;
        } catch (\PDOException $e) {
            return Operations::mapearExcecaoPDO($e, array_merge(['funcao' => 'Grupo::Criar'], $params));
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
            return Operations::mapearExcecaoPDO($e, array_merge(['funcao' => 'Grupo::atualizar'], $contexto));
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
            Operations::mapearExcecaoPDO($e, array_merge(['funcao' => 'Grupo::remover_logicamente'], $contexto));
            return false;
        }
    }

    /* ==============================
       RELAÇÃO grupos_papeis
       ============================== */

    /** Atribui um papel a um grupo (evita duplicata) */
    function atribuir_papel(int $id_grupo, int $id_papel): bool
    {
        $contexto = ['id_grupo' => $id_grupo, 'papel_id' => $id_papel];
        $sql = "INSERT INTO auth.grupos_papeis (grupo_id, papel_id)
                VALUES (:grupo, :papel)
                ON CONFLICT (grupo_id, papel_id) DO NOTHING";
        $st = $this->pdo->prepare($sql);
        try {
            return $st->execute([':grupo' => $id_grupo, ':papel' => $id_papel]);
        } catch (\PDOException $e) {
            Operations::mapearExcecaoPDO($e, array_merge(['funcao' => 'Grupo::atribuir_papel'], $contexto));
            return false;
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
            Operations::mapearExcecaoPDO($e, array_merge(['funcao' => 'Grupo::remover_papel'], $contexto));
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
            return Operations::mapearExcecaoPDO($e, array_merge(['funcao' => 'Grupo::listar_papeis'], $contexto));
        }
    }
}
