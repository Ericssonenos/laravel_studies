<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\auth\Grupo;

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

        return response()->json($grupos);
    }

    /** Cria grupo */
    public function store(Request $request)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Grupo();

        $novo = $m->inserir(
            $pdo,
            locatario_id: (int)$request->input('locatario_id', 1),
            nome:        (string)$request->input('nome'),
            descricao:   $request->input('descricao'),
            ativo:       $request->boolean('ativo', true)
        );

        return response()->json($novo, 201);
    }

    /** Atualiza grupo */
    public function update(Request $request, int $id)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Grupo();

        $dados = $request->only(['txt_nome_grupo','txt_descricao_grupo','flg_ativo_grupo']);
        $atual = $m->atualizar($pdo, $id, $dados);

        return response()->json($atual);
    }

    /** Soft-delete */
    public function destroy(int $id)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Grupo();

        $ok = $m->remover_logicamente($pdo, $id);
        return response()->json(['sucesso' => $ok]);
    }

    /* -------- Relação grupos_papeis -------- */

    /** Atribui papel ao grupo */
    public function atribuirPapel(Request $request, int $id_grupo)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Grupo();

        $ok = $m->atribuir_papel($pdo, $id_grupo, (int)$request->input('papel_id'));
        return response()->json(['sucesso' => $ok]);
    }

    /** Remove papel do grupo */
    public function removerPapel(Request $request, int $id_grupo)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Grupo();

        $ok = $m->remover_papel($pdo, $id_grupo, (int)$request->input('papel_id'));
        return response()->json(['sucesso' => $ok]);
    }

    /** Lista papéis do grupo */
    public function listarPapeis(int $id_grupo)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Grupo();

        $papeis = $m->listar_papeis($pdo, $id_grupo);
        return response()->json($papeis);
    }
}
