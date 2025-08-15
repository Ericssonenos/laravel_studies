<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\auth\Permissao;

class PermissaoController extends Controller
{
    /** Lista permissões */
    public function index(Request $request)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Permissao();

        $permissoes = $m->procurar(
            $pdo,
            [
                'codigo'    => $request->input('codigo'),
                'descricao' => $request->input('descricao'),
                'ativo'     => $request->has('ativo') ? $request->boolean('ativo') : null,
                'ids'       => $request->input('ids') ? array_map('intval', (array)$request->input('ids')) : null,
            ],
            [
                'order_by' => $request->input('order_by', 'cod_permissao ASC'),
                'limit'    => (int)$request->input('limit', 50),
                'offset'   => (int)$request->input('offset', 0),
            ]
        );

        return response()->json($permissoes);
    }

    /** Cria permissão */
    public function store(Request $request)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Permissao();

        $novo = $m->inserir(
            $pdo,
            codigo:     (string)$request->input('codigo'),
            descricao:  $request->input('descricao'),
            ativo:      $request->boolean('ativo', true)
        );

        return response()->json($novo, 201);
    }

    /** Atualiza permissão */
    public function update(Request $request, int $id)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Permissao();

        $dados = $request->only(['cod_permissao','txt_descricao_permissao','flg_ativo_permissao']);
        $atual = $m->atualizar($pdo, $id, $dados);

        return response()->json($atual);
    }

    /** Soft-delete */
    public function destroy(int $id)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Permissao();

        $ok = $m->remover_logicamente($pdo, $id);
        return response()->json(['sucesso' => $ok]);
    }
}
