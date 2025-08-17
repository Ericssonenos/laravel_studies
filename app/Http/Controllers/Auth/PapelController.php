<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Auth\Papel;
use App\Services\Operations;

class PapelController extends Controller
{
    /** Lista papéis */
    public function index(Request $request)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Papel();

        $papeis = $m->procurar(
            $pdo,
            [
                'locatario_id' => (int)$request->input('locatario_id', 1),
                'nome'         => $request->input('nome'),
                'ativo'        => $request->has('ativo') ? $request->boolean('ativo') : null,
                'ids'          => $request->input('ids') ? array_map('intval', (array)$request->input('ids')) : null,
            ],
            [
                'order_by' => $request->input('order_by', 'txt_nome_papel ASC'),
                'limit'    => (int)$request->input('limit', 50),
                'offset'   => (int)$request->input('offset', 0),
            ]
        );

        if (is_array($papeis) && isset($papeis['http_status'])) {
            return response()->json($papeis, (int)$papeis['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(Operations::padronizarRespostaSucesso($papeis, 200, 'Lista de papéis retornada com sucesso.', ['locatario_id' => (int)$request->input('locatario_id', 1)]), 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Cria papel */
    public function store(Request $request)
    {
        $regras = [
            'locatario_id' => ['required', 'integer'],
            'nome' => ['required', 'string', 'max:120'],
            'nivel' => ['quandoPresente ', 'integer'],
            'ativo' => ['quandoPresente ', 'boolean'],
        ];

        $validacao = Operations::validarRegras($request->all(), $regras);
        if ($validacao['http_status'] !== 200) {
            return response()->json($validacao, (int)$validacao['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        $pdo = DB::connection()->getPdo();
        $m = new Papel();

        $novo = $m->inserir(
            $pdo,
            locatario_id: (int)$request->input('locatario_id', 1),
            nome:  (string)$request->input('nome'),
            nivel: (int)$request->input('nivel', 0),
            ativo: $request->boolean('ativo', true)
        );

        if (is_array($novo) && isset($novo['http_status'])) {
            return response()->json($novo, (int)$novo['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(Operations::padronizarRespostaSucesso($novo, 201, 'Papel criado com sucesso.', ['locatario_id' => (int)$request->input('locatario_id', 1), 'nome' => (string)$request->input('nome')]), 201, [], JSON_UNESCAPED_UNICODE);
    }

    /** Atualiza papel */
    public function update(Request $request, int $id)
    {
        $regras = [
            'txt_nome_papel' => ['quandoPresente ', 'string', 'max:120'],
            'num_nivel_papel' => ['quandoPresente ', 'integer'],
            'flg_ativo_papel' => ['quandoPresente ', 'boolean'],
        ];

        $validacao = Operations::validarRegras($request->all(), $regras);
        if ($validacao['http_status'] !== 200) {
            return response()->json($validacao, (int)$validacao['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        $pdo = DB::connection()->getPdo();
        $m = new Papel();

        $dados = $request->only(['txt_nome_papel','num_nivel_papel','flg_ativo_papel']);
        $atual = $m->atualizar($pdo, $id, $dados);

        if (is_array($atual) && isset($atual['http_status'])) {
            return response()->json($atual, (int)$atual['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(Operations::padronizarRespostaSucesso($atual, 200, 'Papel atualizado com sucesso.', ['id_papel' => $id]), 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Soft-delete */
    public function destroy(int $id)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Papel();

        $ok = $m->remover_logicamente($pdo, $id);
        if (is_array($ok) && isset($ok['http_status'])) {
            return response()->json($ok, (int)$ok['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        if ($ok === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(Operations::padronizarRespostaSucesso(['sucesso' => true], 200, 'Papel removido com sucesso.', ['id_papel' => $id]), 200, [], JSON_UNESCAPED_UNICODE);
    }

    /* -------- Relação papeis_permissoes -------- */

    /** Atribui permissão ao papel */
    public function atribuirPermissao(Request $request, int $id_papel)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Papel();

        $ok = $m->atribuir_permissao($pdo, $id_papel, (int)$request->input('permissao_id'));

        if (is_array($ok) && isset($ok['http_status'])) {
            return response()->json($ok, (int)$ok['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        if ($ok === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(Operations::padronizarRespostaSucesso(['sucesso' => true], 200, 'Permissão atribuída ao papel.', ['id_papel' => $id_papel, 'permissao_id' => (int)$request->input('permissao_id')]), 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Remove permissão do papel */
    public function removerPermissao(Request $request, int $id_papel)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Papel();

        $ok = $m->remover_permissao($pdo, $id_papel, (int)$request->input('permissao_id'));

        if (is_array($ok) && isset($ok['http_status'])) {
            return response()->json($ok, (int)$ok['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        if ($ok === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(Operations::padronizarRespostaSucesso(['sucesso' => true], 200, 'Permissão removida do papel.', ['id_papel' => $id_papel, 'permissao_id' => (int)$request->input('permissao_id')]), 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Lista permissões do papel */
    public function listarPermissoes(int $id_papel)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Papel();

        $permissoes = $m->listar_permissoes($pdo, $id_papel);

        if (is_array($permissoes) && isset($permissoes['http_status'])) {
            return response()->json($permissoes, (int)$permissoes['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(Operations::padronizarRespostaSucesso($permissoes, 200, 'Lista de permissões do papel retornada com sucesso.', ['id_papel' => $id_papel]), 200, [], JSON_UNESCAPED_UNICODE);
    }
}
