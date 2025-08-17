<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Auth\Permissao;
use App\Services\Operations;

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

        if (is_array($permissoes) && isset($permissoes['http_status'])) {
            return response()->json($permissoes, (int)$permissoes['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(Operations::padronizarRespostaSucesso($permissoes, 200, 'Lista de permissões retornada com sucesso.'), 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Cria permissão */
    public function store(Request $request)
    {
        $regras = [
            'codigo' => ['required', 'string', 'max:80'],
            'descricao' => ['quandoPresente ', 'string', 'max:400'],
            'ativo' => ['quandoPresente ', 'boolean']
        ];

        $validacao = Operations::validarRegras($request->all(), $regras);
        if ($validacao['http_status'] !== 200) {
            return response()->json($validacao, (int)$validacao['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        $pdo = DB::connection()->getPdo();
        $m = new Permissao();

        $novo = $m->inserir(
            $pdo,
            codigo:     (string)$request->input('codigo'),
            descricao:  $request->input('descricao'),
            ativo:      $request->boolean('ativo', true)
        );

        if (is_array($novo) && isset($novo['http_status'])) {
            return response()->json($novo, (int)$novo['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(Operations::padronizarRespostaSucesso($novo, 201, 'Permissão criada com sucesso.', ['cod_permissao' => (string)$request->input('codigo'), 'codigo' => (string)$request->input('codigo')]), 201, [], JSON_UNESCAPED_UNICODE);
    }

    /** Atualiza permissão */
    public function update(Request $request, int $id)
    {
        $regras = [
            'cod_permissao' => ['quandoPresente ', 'string', 'max:80'],
            'txt_descricao_permissao' => ['quandoPresente ', 'string', 'max:400'],
            'flg_ativo_permissao' => ['quandoPresente ', 'boolean'],
        ];

        $validacao = Operations::validarRegras($request->all(), $regras);
        if ($validacao['http_status'] !== 200) {
            return response()->json($validacao, (int)$validacao['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        $pdo = DB::connection()->getPdo();
        $m = new Permissao();

        $dados = $request->only(['cod_permissao','txt_descricao_permissao','flg_ativo_permissao']);
        $atual = $m->atualizar($pdo, $id, $dados);

        if (is_array($atual) && isset($atual['http_status'])) {
            return response()->json($atual, (int)$atual['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(Operations::padronizarRespostaSucesso($atual, 200, 'Permissão atualizada com sucesso.', ['id_permissao' => $id]), 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Soft-delete */
    public function destroy(int $id)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Permissao();

        $ok = $m->remover_logicamente($pdo, $id);
        if (is_array($ok) && isset($ok['http_status'])) {
            return response()->json($ok, (int)$ok['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        if ($ok === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(Operations::padronizarRespostaSucesso(['sucesso' => true], 200, 'Permissão removida com sucesso.', ['id_permissao' => $id]), 200, [], JSON_UNESCAPED_UNICODE);
    }
}
