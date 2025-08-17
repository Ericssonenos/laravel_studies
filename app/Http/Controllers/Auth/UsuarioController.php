<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Auth\Usuario;
use App\Http\Controllers\Controller;
use App\Services\Operations;

class UsuarioController extends Controller
{
    /** Lista usuários */
    public function Lista(Request $request)
    {
        //Conexao
        $pdo = DB::connection()->getPdo();
        // Modulo Usuário
        $usuarioModel = new Usuario();

        // Obter lista de Usuários, passando conexão e parametros
        $resultadoUsuarios = $usuarioModel->Lista(
            pdo: $pdo,
            filtros: $request->all()
        );

        //Verifica se houve erro na busca
        if (is_array($resultadoUsuarios) && isset($resultadoUsuarios['http_status'])) {
            return response()->json($resultadoUsuarios, (int)$resultadoUsuarios['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        // Se retorno com sucesso, padronizar retorno
        $respostaSucesso = Operations::padronizarRespostaSucesso(
            $resultadoUsuarios,
            200,
            'Registros retornados com sucesso.',
            $request->all()
        );

        // Retornar com a Lista
        return response()->json(
            $respostaSucesso,
            200, [], JSON_UNESCAPED_UNICODE
        );
    }

    /** Cria novo usuário */
    public function Criar(Request $request)
    {

        /**A criação do usuário
         * será basica com
         * e-mail e senha
         * as demais informações serão adicionadas
         * nas configurações do usuário
         * conforme permissões
         */

        // Regras de validação
        $regrasValidacao = [
            'locatario_id' => ['required', 'integer'],
            'txt_email_usuario'        => ['required', 'email', 'max:160'],
            'txt_senha_usuario'        => ['required', 'password', 'min:8', 'max:14'],
        ];

        // Validação dos dados de entrada
        $resultadoDaValidacao = Operations::validarRegras(
             $request->all()
            , $regrasValidacao);

        if ($resultadoDaValidacao['http_status'] !== 200) {
            return response()->json($resultadoDaValidacao, $resultadoDaValidacao['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        $pdoConnection = DB::connection()->getPdo();
        $usuarioModel = new Usuario();

        // gerar hash e chamar model (ainda tratar PDOException depois)
        $senhaHash = password_hash($request->input('senha'), PASSWORD_DEFAULT);

        $resultadoInsercao = $usuarioModel->inserir(
            $pdoConnection,
            locatario_id: $request->input('locatario_id', 1),
            nome: $request->input('nome'),
            email: $request->input('email'),
            senha_hash: $senhaHash,
            ativo: $request->boolean('ativo', true)
        );

        // repassa diretamente se for objeto padronizado (erro ou sucesso já padronizado pelo model)
        if (is_array($resultadoInsercao) && isset($resultadoInsercao['http_status'])) {
            return response()->json($resultadoInsercao, (int)$resultadoInsercao['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        $contextoResposta = [
            'locatario_id' => (int)$request->input('locatario_id', 1),
            'email' => $request->input('email')
        ];

        $respostaSucesso = Operations::padronizarRespostaSucesso(
            $resultadoInsercao,
            201,
            'Registro criado com sucesso.',
            $contextoResposta
        );

        return response()->json($respostaSucesso, 201, [], JSON_UNESCAPED_UNICODE);
    }

    /** Atribui grupo ao usuário */
    public function AtribuirGrupo(Request $request, $id_usuario)
    {
        $pdo = DB::connection()->getPdo();
        $usuarioModel = new Usuario();
        $Retorno_Grupo_atribuido = $usuarioModel->atribuir_grupo(
            $pdo,
            id_usuario: $id_usuario,
            id_grupo: $request->input('grupo_id')
        );

        // se o model retornou payload padronizado, repassa
        if (is_array($Retorno_Grupo_atribuido) && isset($Retorno_Grupo_atribuido['http_status'])) {
            return response()->json($Retorno_Grupo_atribuido, (int)$Retorno_Grupo_atribuido['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        // falha genérica (model retorna false)
        if ($Retorno_Grupo_atribuido === false) {
            // fallback mínimo: não criar payload no controller — o model deve fornecer o payload quando possível
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        // sucesso
        return response()->json(
            Operations::padronizarRespostaSucesso(
                ['sucesso' => true],
                200,
                'Grupo atribuído ao usuário com sucesso.',
                ['id_usuario' => $id_usuario, 'grupo_id' => $request->input('grupo_id')]
            ),
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /** Atribui papel ao usuário */
    public function AtribuirPapel(Request $request, $id_usuario)
    {
        $pdo = DB::connection()->getPdo();
        $usuarioModel = new Usuario();
        $Retorno_Papel_atribuido = $usuarioModel->atribuir_papel(
            $pdo,
            id_usuario: $id_usuario,
            id_papel: $request->input('papel_id')
        );

        if (is_array($Retorno_Papel_atribuido) && isset($Retorno_Papel_atribuido['http_status'])) {
            return response()->json($Retorno_Papel_atribuido, (int)$Retorno_Papel_atribuido['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        if ($Retorno_Papel_atribuido === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(
            Operations::padronizarRespostaSucesso(
                ['sucesso' => true],
                200,
                'Papel atribuído ao usuário com sucesso.',
                ['id_usuario' => $id_usuario, 'papel_id' => $request->input('papel_id')]
            ),
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /** Atribui permissão ao usuário */
    public function AtribuirPermissao(Request $request, $id_usuario)
    {
        $pdo = DB::connection()->getPdo();
        $usuarioModel = new Usuario();
        $Retorno_Permissao_atribuido = $usuarioModel->atribuir_permissao(
            $pdo,
            id_usuario: $id_usuario,
            id_permissao: $request->input('permissao_id')
        );

        if (is_array($Retorno_Permissao_atribuido) && isset($Retorno_Permissao_atribuido['http_status'])) {
            return response()->json($Retorno_Permissao_atribuido, (int)$Retorno_Permissao_atribuido['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        if ($Retorno_Permissao_atribuido === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(
            Operations::padronizarRespostaSucesso(
                ['sucesso' => true],
                200,
                'Permissão atribuída ao usuário com sucesso.',
                ['id_usuario' => $id_usuario, 'permissao_id' => $request->input('permissao_id')]
            ),
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /** Lista grupos de um usuário */
    public function ListarGrupos($id_usuario)
    {
        $pdo = DB::connection()->getPdo();
        $usuarioModel = new Usuario();
        $grupos = $usuarioModel->listar_grupos($pdo, $id_usuario);
        if (is_array($grupos) && isset($grupos['http_status'])) {
            return response()->json($grupos, (int)$grupos['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json($grupos, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Lista papéis de um usuário */
    public function ListarPapeis($id_usuario)
    {
        $pdo = DB::connection()->getPdo();
        $usuarioModel = new Usuario();
        $papeis = $usuarioModel->listar_papeis($pdo, $id_usuario);
        if (is_array($papeis) && isset($papeis['http_status'])) {
            return response()->json($papeis, (int)$papeis['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json($papeis, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Lista permissões de um usuário */
    public function ListarPermissoes($id_usuario)
    {
        $pdo = DB::connection()->getPdo();
        $usuarioModel = new Usuario();
        $permissoes = $usuarioModel->listar_permissoes($pdo, $id_usuario);
        if (is_array($permissoes) && isset($permissoes['http_status'])) {
            return response()->json($permissoes, (int)$permissoes['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json($permissoes, 200, [], JSON_UNESCAPED_UNICODE);
    }
}
