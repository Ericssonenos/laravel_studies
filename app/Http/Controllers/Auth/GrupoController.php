<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Auth\Grupo;
use App\Services\Operations;

class GrupoController extends Controller
{
    /** Lista grupos com filtros/opções */
    public function index(Request $request)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Grupo();

    $grupos = $m->procurar(
            $pdo,
            [
                'locatario_id' => (int)$request->input('locatario_id', 1),
                'nome'         => $request->input('nome'),
                'ativo'        => $request->has('ativo') ? $request->boolean('ativo') : null,
                'ids'          => $request->input('ids') ? array_map('intval', (array)$request->input('ids')) : null,
            ],
            [
                'order_by' => $request->input('order_by', 'txt_nome_grupo ASC'),
                'limit'    => (int)$request->input('limit', 50),
                'offset'   => (int)$request->input('offset', 0),
            ]
        );

        if (is_array($grupos) && isset($grupos['http_status'])) {
            return response()->json($grupos, (int)$grupos['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(Operations::padronizarRespostaSucesso($grupos, 200, 'Lista de grupos retornada com sucesso.', ['locatario_id' => (int)$request->input('locatario_id', 1)]), 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Cria grupo */
    public function store(Request $request)
    {
        $regras = [
            'locatario_id' => ['required', 'integer'],
            'nome' => ['required', 'string', 'max:120'],
            'descricao' => ['sometimes', 'string', 'max:400'],
            'ativo' => ['sometimes', 'boolean'],
        ];

        $validacao = Operations::validarRegras($request->all(), $regras);
        if ($validacao['http_status'] !== 200) {
            return response()->json($validacao, (int)$validacao['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        $pdo = DB::connection()->getPdo();
        $m = new Grupo();

        $novo = $m->inserir(
            $pdo,
            locatario_id: (int)$request->input('locatario_id', 1),
            nome:        (string)$request->input('nome'),
            descricao:   $request->input('descricao'),
            ativo:       $request->boolean('ativo', true)
        );

        if (is_array($novo) && isset($novo['http_status'])) {
            return response()->json($novo, (int)$novo['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(Operations::padronizarRespostaSucesso($novo, 201, 'Grupo criado com sucesso.', ['locatario_id' => (int)$request->input('locatario_id', 1), 'nome' => (string)$request->input('nome')]), 201, [], JSON_UNESCAPED_UNICODE);
    }

    /** Atualiza grupo */
    public function update(Request $request, int $id)
    {
        $regras = [
            'txt_nome_grupo' => ['sometimes', 'string', 'max:120'],
            'txt_descricao_grupo' => ['sometimes', 'string', 'max:400'],
            'flg_ativo_grupo' => ['sometimes', 'boolean'],
        ];

        $validacao = Operations::validarRegras($request->all(), $regras);
        if ($validacao['http_status'] !== 200) {
            return response()->json($validacao, (int)$validacao['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        $pdo = DB::connection()->getPdo();
        $m = new Grupo();

        $dados = $request->only(['txt_nome_grupo','txt_descricao_grupo','flg_ativo_grupo']);
        $atual = $m->atualizar($pdo, $id, $dados);

        if (is_array($atual) && isset($atual['http_status'])) {
            return response()->json($atual, (int)$atual['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(Operations::padronizarRespostaSucesso($atual, 200, 'Grupo atualizado com sucesso.', ['id_grupo' => $id]), 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Soft-delete */
    public function destroy(int $id)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Grupo();

        $ok = $m->remover_logicamente($pdo, $id);
        if (is_array($ok) && isset($ok['http_status'])) {
            return response()->json($ok, (int)$ok['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        if ($ok === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(Operations::padronizarRespostaSucesso(['sucesso' => true], 200, 'Grupo removido com sucesso.', ['id_grupo' => $id]), 200, [], JSON_UNESCAPED_UNICODE);
    }

    /* -------- Relação grupos_papeis -------- */

    /** Atribui papel ao grupo */
    public function atribuirPapel(Request $request, int $id_grupo)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Grupo();

        $ok = $m->atribuir_papel($pdo, $id_grupo, (int)$request->input('papel_id'));

        if (is_array($ok) && isset($ok['http_status'])) {
            return response()->json($ok, (int)$ok['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        if ($ok === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(Operations::padronizarRespostaSucesso(['sucesso' => true], 200, 'Papel atribuído ao grupo.', ['id_grupo' => $id_grupo, 'papel_id' => (int)$request->input('papel_id')]), 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Remove papel do grupo */
    public function removerPapel(Request $request, int $id_grupo)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Grupo();

        $ok = $m->remover_papel($pdo, $id_grupo, (int)$request->input('papel_id'));

        if (is_array($ok) && isset($ok['http_status'])) {
            return response()->json($ok, (int)$ok['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        if ($ok === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(Operations::padronizarRespostaSucesso(['sucesso' => true], 200, 'Papel removido do grupo.', ['id_grupo' => $id_grupo, 'papel_id' => (int)$request->input('papel_id')]), 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Lista papéis do grupo */
    public function listarPapeis(int $id_grupo)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Grupo();

        $papeis = $m->listar_papeis($pdo, $id_grupo);

        if (is_array($papeis) && isset($papeis['http_status'])) {
            return response()->json($papeis, (int)$papeis['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json($papeis, 200, [], JSON_UNESCAPED_UNICODE);
    }
}
