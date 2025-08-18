<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Models\Auth\Usuario;
use App\Http\Controllers\Controller;
use App\Services\Operations;

class UsuarioController extends Controller
{
    private Usuario $usuarioModel;

    public function __construct()
    {
        //[ ] Middleware para autenticação e autorização pode ser adicionado aqui
        $this->usuarioModel = new Usuario();
    }
    /** Lista usuários */
    public function Lista(Request $request)
    {
        // Obter lista de Usuários, passando parametros
        $resultadoUsuarios = $this->usuarioModel->Lista($request->all());

        // Retornar com a Lista
        return response()->json(
            data: [
                'data' => $resultadoUsuarios['data'],
                'message' => $resultadoUsuarios['message'],
                'params' => $request->all()
            ],
            status: $resultadoUsuarios['pdo_status'],
            headers: Operations::gerarHeadersCompletos(
                request: $request,
                retorno: $resultadoUsuarios
            ),
            options: JSON_UNESCAPED_UNICODE
        );
    }

    /** Cria novo usuário */
    public function Criar(Request $request)
    {
        // Validação dos dados de entrada
        $resultadoDaValidacao = Operations::validarRegras(
            params: $request->all(),
            regrasValidacao: [
                'locatario_id' => ['required', 'integer'],
                'txt_nome_usuario' => ['quandoPresente', 'string', 'max:60'],
                'txt_email_usuario' => ['required', 'email', 'max:120'],
                'txt_senha_usuario' => ['required', 'password', 'min:6'],
                'flg_ativo_usuario' => ['quandoPresente', 'boolean']
            ]
        );

        // Verifica se houve erro na validação
        if ($resultadoDaValidacao['params_status']  !== 200) {
            return response()->json(
                data: $resultadoDaValidacao,
                status: (int)$resultadoDaValidacao['params_status'],
                headers: Operations::gerarHeadersCompletos($request),
                options: JSON_UNESCAPED_UNICODE
            );
        }

        // gerar hash e chamar model
        $senhaHash = password_hash($request->input('txt_senha_usuario'), PASSWORD_DEFAULT);
        $params = $request->all();
        $params['txt_senha_usuario'] = $senhaHash;

        // Chamar o model para criar o usuário
        $resultadoInsercao = $this->usuarioModel->Criar($params);

        // Padronizar resposta
        return response()->json(
            data: [
                'data' => $resultadoInsercao['data'],
                'params' => request()->all(),
                'message' => $resultadoInsercao['message']
            ],
            status: $resultadoInsercao['pdo_status'],
            headers: Operations::gerarHeadersCompletos(
                request: $request,
                retorno: $resultadoInsercao
            ),
            options: JSON_UNESCAPED_UNICODE
        );
    }

    /** Atribui grupo ao usuário */
    public function AtribuirGrupo(Request $request)
    {
        // Validação dos dados de entrada
        $resultadoDaValidacao = Operations::validarRegras(
            params: $request->all(),
            regrasValidacao: [
                'id_usuario' => ['required', 'integer'],
                'grupo_id' => ['required', 'integer']
            ]
        );

        // Verifica se houve erro na validação
        if ($resultadoDaValidacao['params_status']  !== 200) {
            return response()->json(
                data: $resultadoDaValidacao,
                status: (int)$resultadoDaValidacao['params_status'],
                headers: Operations::gerarHeadersCompletos(
                    request: $request,
                    retorno: []
                ),
                options: JSON_UNESCAPED_UNICODE
            );
        }
        // Chamar o método AtribuirGrupo
        $Retorno_Grupo_atribuido = $this->usuarioModel->AtribuirGrupo($request->all());

        // Padronizar resposta
        return response()->json(
            data: [
                'data' => $Retorno_Grupo_atribuido['data'],
                'message' => $Retorno_Grupo_atribuido['message'],
                'params' => $request->all()
            ],
            status: $Retorno_Grupo_atribuido['pdo_status'],
            headers: Operations::gerarHeadersCompletos(
                request: $request,
                retorno: $Retorno_Grupo_atribuido
            ),
            options: JSON_UNESCAPED_UNICODE
        );
    }

    /** Atribui papel ao usuário */
    public function AtribuirPapel(Request $request)
    {
        // Validação dos dados de entrada
        $resultadoDaValidacao = Operations::validarRegras(
            params: $request->all(),
            regrasValidacao: [
                'papel_id' => ['required', 'integer'],
                'usuario_id' => ['required', 'integer']
            ]
        );

        // Verifica se houve erro na validação
        if ($resultadoDaValidacao['params_status']  !== 200) {
            return response()->json(
                data: $resultadoDaValidacao,
                status: (int)$resultadoDaValidacao['params_status'],
                headers: Operations::gerarHeadersCompletos(
                    request: $request,
                    retorno: []
                ),
                options: JSON_UNESCAPED_UNICODE
            );
        }

        // Chamar o método AtribuirPapel
        $Retorno_Papel_atribuido = $this->usuarioModel->AtribuirPapel($request->all());

        // Padronizar resposta
        return response()->json(
            data: [
                'data' => $Retorno_Papel_atribuido['data'],
                'message' => $Retorno_Papel_atribuido['message'],
                'params' => $request->all()
            ],
            status: $Retorno_Papel_atribuido['pdo_status'],
            headers: Operations::gerarHeadersCompletos(
                request: $request,
                retorno: $Retorno_Papel_atribuido
            ),
            options: JSON_UNESCAPED_UNICODE
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

        if (is_array($Retorno_Permissao_atribuido) && isset($Retorno_Permissao_atribuido['pdo_status'])) {
            return response()->json(
                data: $Retorno_Permissao_atribuido,
                status: (int)$Retorno_Permissao_atribuido['pdo_status'],
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

        if (is_array($grupos) && isset($grupos['pdo_status'])) {
            return response()->json(
                data: $grupos,
                status: (int)$grupos['pdo_status'],
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

        if (is_array($papeis) && isset($papeis['pdo_status'])) {
            return response()->json(
                data: $papeis,
                status: (int)$papeis['pdo_status'],
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

        if (is_array($permissoes) && isset($permissoes['pdo_status'])) {
            return response()->json(
                data: $permissoes,
                status: (int)$permissoes['pdo_status'],
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
