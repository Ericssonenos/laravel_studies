<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\auth\Papel;

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

        return response()->json($papeis);
    }

    /** Cria papel */
    public function store(Request $request)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Papel();

        $novo = $m->inserir(
            $pdo,
            locatario_id: (int)$request->input('locatario_id', 1),
            nome:  (string)$request->input('nome'),
            nivel: (int)$request->input('nivel', 0),
            ativo: $request->boolean('ativo', true)
        );

        return response()->json($novo, 201);
    }

    /** Atualiza papel */
    public function update(Request $request, int $id)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Papel();

        $dados = $request->only(['txt_nome_papel','num_nivel_papel','flg_ativo_papel']);
        $atual = $m->atualizar($pdo, $id, $dados);

        return response()->json($atual);
    }

    /** Soft-delete */
    public function destroy(int $id)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Papel();

        $ok = $m->remover_logicamente($pdo, $id);
        return response()->json(['sucesso' => $ok]);
    }

    /* -------- Relação papeis_permissoes -------- */

    /** Atribui permissão ao papel */
    public function atribuirPermissao(Request $request, int $id_papel)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Papel();

        $ok = $m->atribuir_permissao($pdo, $id_papel, (int)$request->input('permissao_id'));
        return response()->json(['sucesso' => $ok]);
    }

    /** Remove permissão do papel */
    public function removerPermissao(Request $request, int $id_papel)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Papel();

        $ok = $m->remover_permissao($pdo, $id_papel, (int)$request->input('permissao_id'));
        return response()->json(['sucesso' => $ok]);
    }

    /** Lista permissões do papel */
    public function listarPermissoes(int $id_papel)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Papel();

        $permissoes = $m->listar_permissoes($pdo, $id_papel);
        return response()->json($permissoes);
    }
}
