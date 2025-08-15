<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\auth\Usuario;
use app\Http\Controllers\Controller;


class UsuarioController extends Controller
{
    /** Lista usuários */
    public function index(Request $request)
    {
        $pdo = DB::connection()->getPdo();
        $usuarioModel = new Usuario();

        $usuarios = $usuarioModel->procurar(
            $pdo,
            [
                'ativo' => $request->boolean('ativo', true),
                'locatario_id' => $request->input('locatario_id', 1)
            ],
            [
                'order_by' => 'txt_nome_usuario ASC',
                'limit' => $request->input('limit', 50),
                'offset' => $request->input('offset', 0)
            ]
        );

        return response()->json($usuarios);
    }

    /** Cria novo usuário */
    public function store(Request $request)
    {
        $pdo = DB::connection()->getPdo();
        $usuarioModel = new Usuario();

        $novo = $usuarioModel->inserir(
            $pdo,
            locatario_id: $request->input('locatario_id', 1),
            nome:  $request->input('nome'),
            email: $request->input('email'),
            senha_hash: password_hash($request->input('senha'), PASSWORD_DEFAULT),
            ativo: $request->boolean('ativo', true)
        );

        return response()->json($novo, 201);
    }

    /** Atribui grupo ao usuário */
    public function atribuirGrupo(Request $request, $id_usuario)
    {
        $pdo = DB::connection()->getPdo();
        $usuarioModel = new Usuario();

        $ok = $usuarioModel->atribuir_grupo(
            $pdo,
            id_usuario: $id_usuario,
            id_grupo: $request->input('grupo_id')
        );

        return response()->json(['sucesso' => $ok]);
    }

    /** Atribui papel ao usuário */
    public function atribuirPapel(Request $request, $id_usuario)
    {
        $pdo = DB::connection()->getPdo();
        $usuarioModel = new Usuario();

        $ok = $usuarioModel->atribuir_papel(
            $pdo,
            id_usuario: $id_usuario,
            id_papel: $request->input('papel_id')
        );

        return response()->json(['sucesso' => $ok]);
    }

    /** Atribui permissão ao usuário */
    public function atribuirPermissao(Request $request, $id_usuario)
    {
        $pdo = DB::connection()->getPdo();
        $usuarioModel = new Usuario();

        $ok = $usuarioModel->atribuir_permissao(
            $pdo,
            id_usuario: $id_usuario,
            id_permissao: $request->input('permissao_id')
        );

        return response()->json(['sucesso' => $ok]);
    }

    /** Lista grupos de um usuário */
    public function listarGrupos($id_usuario)
    {
        $pdo = DB::connection()->getPdo();
        $usuarioModel = new Usuario();
        $grupos = $usuarioModel->listar_grupos($pdo, $id_usuario);

        return response()->json($grupos);
    }

    /** Lista papéis de um usuário */
    public function listarPapeis($id_usuario)
    {
        $pdo = DB::connection()->getPdo();
        $usuarioModel = new Usuario();
        $papeis = $usuarioModel->listar_papeis($pdo, $id_usuario);

        return response()->json($papeis);
    }

    /** Lista permissões de um usuário */
    public function listarPermissoes($id_usuario)
    {
        $pdo = DB::connection()->getPdo();
        $usuarioModel = new Usuario();
        $permissoes = $usuarioModel->listar_permissoes($pdo, $id_usuario);

        return response()->json($permissoes);
    }
}
