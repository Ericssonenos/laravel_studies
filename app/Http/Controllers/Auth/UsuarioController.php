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
        // Gerar ID único para rastreamento de logs
        $requestId = $request->header('X-Request-Id', uniqid('usr-list-', true));

        //Conexao
        $pdo = DB::connection()->getPdo();
        // Modulo Usuário
        $usuarioModel = new Usuario($pdo);

        // Obter lista de Usuários, passando parametros
        $resultadoUsuarios = $usuarioModel->Lista($request->all());

        //Verifica se houve erro na busca
        if (is_array($resultadoUsuarios) && isset($resultadoUsuarios['http_status'])) {
            return response()->json(
                data: $resultadoUsuarios,
                status: (int)$resultadoUsuarios['http_status'],
                options: JSON_UNESCAPED_UNICODE,
                headers: [
                    'Content-Type' => 'application/problem+json; charset=utf-8',
                    'X-Request-Id' => $requestId
                ]
            );
        }

        // Se retorno com sucesso, gerar headers automaticamente
        $headers = Operations::gerarHeadersCompletos(
            requestId: $requestId,
            requestData: $request->all(),
            baseUrl: $request->url(),
            dados: $resultadoUsuarios
        );

        // Se retorno com sucesso, padronizar retorno
        $respostaSucesso = Operations::padronizarRespostaSucesso(
            data: $resultadoUsuarios,
            msg: 'Lista de usuários retornada com sucesso.',
            contexto: $request->all()
        );

        // Retornar com a Lista
        return response()->json(
            data: $respostaSucesso,
            status: 200,
            headers: $headers,
            options: JSON_UNESCAPED_UNICODE
        );
    }

    /** Cria novo usuário */
    public function Criar(Request $request)
    {
        // Gerar ID único para rastreamento
        $requestId = $request->header('X-Request-Id', uniqid('usr-create-', true));

        // Validação dos dados de entrada
        $regras = [
            'locatario_id' => ['required', 'integer'],
            'txt_nome_usuario' => ['required', 'string', 'max:120'],
            'txt_email_usuario' => ['required', 'string', 'max:160'],
            'txt_senha_usuario' => ['required', 'string', 'min:6'],
            'flg_ativo_usuario' => ['quandoPresente', 'boolean']
        ];

        $resultadoDaValidacao = Operations::validarRegras($request->all(), $regras);

        // Verifica se houve erro na validação
        if ($resultadoDaValidacao['http_status'] !== 200) {
            return response()->json(
                $resultadoDaValidacao,
                (int)$resultadoDaValidacao['http_status'],
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        // Conexão com o banco de dados
        $pdoConnection = DB::connection()->getPdo();

        // Instância do Usuario
        $usuarioModel = new Usuario($pdoConnection);

        // gerar hash e chamar model
        $senhaHash = password_hash($request->input('txt_senha_usuario'), PASSWORD_DEFAULT);

        $params = $request->all();
        $params['txt_senha_usuario'] = $senhaHash;

        // Chamar o model para criar o usuário
        $resultadoInsercao = $usuarioModel->Criar($params);

        // repassa diretamente se for objeto padronizado (erro ou sucesso já padronizado pelo model)
        if (is_array($resultadoInsercao) && isset($resultadoInsercao['http_status'])) {
            return response()->json(
                $resultadoInsercao,
                (int)$resultadoInsercao['http_status'],
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        $contextoResposta = [
            'locatario_id' => (int)$request->input('locatario_id', 1),
            'txt_email_usuario' => (string)$request->input('txt_email_usuario')
        ];

        // Gerar headers para criação bem-sucedida
        $headers = Operations::gerarHeadersSeguranca($requestId);

        $respostaSucesso = Operations::padronizarRespostaSucesso(
            $resultadoInsercao,
            201,
            'Usuário criado com sucesso.',
            $contextoResposta
        );

        return response()->json(
            $respostaSucesso,
            201,
            $headers,
            JSON_UNESCAPED_UNICODE
        );
    }

    /** Atribui grupo ao usuário */
    public function AtribuirGrupo(Request $request, $id_usuario)
    {
        // Gerar ID único para rastreamento
        $requestId = $request->header('X-Request-Id', uniqid('usr-atrib-grp-', true));

        $pdo = DB::connection()->getPdo();
        $usuarioModel = new Usuario($pdo);
        $Retorno_Grupo_atribuido = $usuarioModel->atribuir_grupo(
            $id_usuario,
            (int)$request->input('grupo_id')
        );

        // se o model retornou payload padronizado, repassa
        if (is_array($Retorno_Grupo_atribuido) && isset($Retorno_Grupo_atribuido['http_status'])) {
            return response()->json(
                $Retorno_Grupo_atribuido,
                (int)$Retorno_Grupo_atribuido['http_status'],
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        // falha genérica (model retorna false)
        if ($Retorno_Grupo_atribuido === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        // sucesso - gerar headers
        $headers = Operations::gerarHeadersSeguranca($requestId);

        return response()->json(
            Operations::padronizarRespostaSucesso(
                ['sucesso' => true],
                200,
                'Grupo atribuído ao usuário com sucesso.',
                ['id_usuario' => $id_usuario, 'grupo_id' => (int)$request->input('grupo_id')]
            ),
            200,
            $headers,
            JSON_UNESCAPED_UNICODE
        );
    }

    /** Atribui papel ao usuário */
    public function AtribuirPapel(Request $request, $id_usuario)
    {
        // Gerar ID único para rastreamento
        $requestId = $request->header('X-Request-Id', uniqid('usr-atrib-papel-', true));

        $pdo = DB::connection()->getPdo();
        $usuarioModel = new Usuario($pdo);
        $Retorno_Papel_atribuido = $usuarioModel->atribuir_papel(
            $id_usuario,
            (int)$request->input('papel_id')
        );

        if (is_array($Retorno_Papel_atribuido) && isset($Retorno_Papel_atribuido['http_status'])) {
            return response()->json(
                data: $Retorno_Papel_atribuido,
                status: (int)$Retorno_Papel_atribuido['http_status'],
                options: JSON_UNESCAPED_UNICODE,
                headers: [
                    'Content-Type' => 'application/problem+json; charset=utf-8',
                    'X-Request-Id' => $requestId
                ]
            );
        }

        if ($Retorno_Papel_atribuido === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        // sucesso - gerar headers
        $headers = Operations::gerarHeadersSeguranca($requestId);

        return response()->json(
            Operations::padronizarRespostaSucesso(
                data: ['sucesso' => true],
                msg: 'Papel atribuído ao usuário com sucesso.',
                contexto: ['id_usuario' => $id_usuario, 'papel_id' => (int)$request->input('papel_id')]
            ),
            200,
            $headers,
            JSON_UNESCAPED_UNICODE
        );
    }

    /** Atribui permissão ao usuário */
    public function AtribuirPermissao(Request $request, $id_usuario)
    {
        // Gerar ID único para rastreamento
        $requestId = $request->header('X-Request-Id', uniqid('usr-atrib-perm-', true));

        $pdo = DB::connection()->getPdo();
        $usuarioModel = new Usuario($pdo);
        $Retorno_Permissao_atribuido = $usuarioModel->atribuir_permissao(
            $id_usuario,
            (int)$request->input('permissao_id')
        );

        if (is_array($Retorno_Permissao_atribuido) && isset($Retorno_Permissao_atribuido['http_status'])) {
            return response()->json(
                data: $Retorno_Permissao_atribuido,
                status: (int)$Retorno_Permissao_atribuido['http_status'],
                options: JSON_UNESCAPED_UNICODE,
                headers: [
                    'Content-Type' => 'application/problem+json; charset=utf-8',
                    'X-Request-Id' => $requestId
                ]
            );
        }

        if ($Retorno_Permissao_atribuido === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        // sucesso - gerar headers
        $headers = Operations::gerarHeadersSeguranca($requestId);

        return response()->json(
            Operations::padronizarRespostaSucesso(
                data: ['sucesso' => true],
                msg: 'Permissão atribuída ao usuário com sucesso.',
                contexto: ['id_usuario' => $id_usuario, 'permissao_id' => (int)$request->input('permissao_id')]
            ),
            200,
            $headers,
            JSON_UNESCAPED_UNICODE
        );
    }

    /** Lista grupos de um usuário */
    public function ListarGrupos(Request $request, $id_usuario)
    {
        // Gerar ID único para rastreamento
        $requestId = $request->header('X-Request-Id', uniqid('usr-list-grp-', true));

        $pdo = DB::connection()->getPdo();
        $usuarioModel = new Usuario($pdo);
        $grupos = $usuarioModel->listar_grupos($id_usuario);

        if (is_array($grupos) && isset($grupos['http_status'])) {
            return response()->json(
                data: $grupos,
                status: (int)$grupos['http_status'],
                options: JSON_UNESCAPED_UNICODE,
                headers: [
                    'Content-Type' => 'application/problem+json; charset=utf-8',
                    'X-Request-Id' => $requestId
                ]
            );
        }

        // gerar headers
        $headers = Operations::gerarHeadersSeguranca($requestId);

        return response()->json(
            Operations::padronizarRespostaSucesso(
                data: $grupos,
                msg: 'Grupos do usuário retornados com sucesso.',
                contexto: ['id_usuario' => $id_usuario]
            ),
            200,
            $headers,
            JSON_UNESCAPED_UNICODE
        );
    }

    /** Lista papéis de um usuário */
    public function ListarPapeis(Request $request, $id_usuario)
    {
        // Gerar ID único para rastreamento
        $requestId = $request->header('X-Request-Id', uniqid('usr-list-papel-', true));

        $pdo = DB::connection()->getPdo();
        $usuarioModel = new Usuario($pdo);
        $papeis = $usuarioModel->listar_papeis($id_usuario);

        if (is_array($papeis) && isset($papeis['http_status'])) {
            return response()->json(
                data: $papeis,
                status: (int)$papeis['http_status'],
                options: JSON_UNESCAPED_UNICODE,
                headers: [
                    'Content-Type' => 'application/problem+json; charset=utf-8',
                    'X-Request-Id' => $requestId
                ]
            );
        }

        // gerar headers
        $headers = Operations::gerarHeadersSeguranca($requestId);

        return response()->json(
            Operations::padronizarRespostaSucesso(
                data: $papeis,
                msg: 'Papéis do usuário retornados com sucesso.',
                contexto: ['id_usuario' => $id_usuario]
            ),
            200,
            $headers,
            JSON_UNESCAPED_UNICODE
        );
    }

    /** Lista permissões de um usuário */
    public function ListarPermissoes(Request $request, $id_usuario)
    {
        // Gerar ID único para rastreamento
        $requestId = $request->header('X-Request-Id', uniqid('usr-list-perm-', true));

        $pdo = DB::connection()->getPdo();
        $usuarioModel = new Usuario($pdo);
        $permissoes = $usuarioModel->listar_permissoes($id_usuario);

        if (is_array($permissoes) && isset($permissoes['http_status'])) {
            return response()->json(
                data: $permissoes,
                status: (int)$permissoes['http_status'],
                options: JSON_UNESCAPED_UNICODE,
                headers: [
                    'Content-Type' => 'application/problem+json; charset=utf-8',
                    'X-Request-Id' => $requestId
                ]
            );
        }

        // gerar headers
        $headers = Operations::gerarHeadersSeguranca($requestId);

        return response()->json(
            Operations::padronizarRespostaSucesso(
                data: $permissoes,
                msg: 'Permissões do usuário retornadas com sucesso.',
                contexto: ['id_usuario' => $id_usuario]
            ),
            200,
            $headers,
            JSON_UNESCAPED_UNICODE
        );
    }
}
