<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                'usuario_id' => ['required', 'integer'],
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
        $resultadoAtribuicaoGrupo = $this->usuarioModel->AtribuirGrupo($request->all());

        // Padronizar resposta
        return response()->json(
            data: [
                'data' => $resultadoAtribuicaoGrupo['data'],
                'message' => $resultadoAtribuicaoGrupo['message'],
                'params' => $request->all()
            ],
            status: $resultadoAtribuicaoGrupo['pdo_status'],
            headers: Operations::gerarHeadersCompletos(
                request: $request,
                retorno: $resultadoAtribuicaoGrupo
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
        $resultadoAtribuicaoPapel = $this->usuarioModel->AtribuirPapel($request->all());

        // Padronizar resposta
        return response()->json(
            data: [
                'data' => $resultadoAtribuicaoPapel['data'],
                'message' => $resultadoAtribuicaoPapel['message'],
                'params' => $request->all()
            ],
            status: $resultadoAtribuicaoPapel['pdo_status'],
            headers: Operations::gerarHeadersCompletos(
                request: $request,
                retorno: $resultadoAtribuicaoPapel
            ),
            options: JSON_UNESCAPED_UNICODE
        );
    }

    /** Atribui permissão ao usuário */
    public function AtribuirPermissao(Request $request)
    {
        // Validação dos dados de entrada
        $resultadoDaValidacao = Operations::validarRegras(
            params: $request->all(),
            regrasValidacao: [
                'usuario_id' => ['required', 'integer'],
                'permissao_id' => ['required', 'integer']
            ]
        );

        // Verifica se houve erro na validação
        if ($resultadoDaValidacao['params_status'] !== 200) {
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

        // Chamar o método AtribuirPermissao
        $resultadoAtribuicaoPermissao = $this->usuarioModel->AtribuirPermissao($request->all());

        // Padronizar resposta
        return response()->json(
            data: [
                'data' => $resultadoAtribuicaoPermissao['data'],
                'message' => $resultadoAtribuicaoPermissao['message'],
                'params' => $request->all()
            ],
            status: $resultadoAtribuicaoPermissao['pdo_status'],
            headers: Operations::gerarHeadersCompletos(
                request: $request,
                retorno: $resultadoAtribuicaoPermissao
            ),
            options: JSON_UNESCAPED_UNICODE
        );
    }

    /** Lista grupos de um usuário */
    public function ListarGrupos(Request $request)
    {
        // Gerar ID único para rastreamento
        $requestId = $request->header('X-Request-Id', uniqid('usr-list-grp-', true));

        $pdo = DB::connection()->getPdo();
        $usuarioModel = new Usuario($pdo);
        $grupos = $usuarioModel->listar_grupos((int)$request->input('usuario_id'));

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
                contexto: ['usuario_id' => (int)$request->input('usuario_id')]
            ),
            200,
            $headers,
            JSON_UNESCAPED_UNICODE
        );
    }

    /** Lista papéis de um usuário */
    public function ListarPapeis(Request $request)
    {
        // Gerar ID único para rastreamento
        $requestId = $request->header('X-Request-Id', uniqid('usr-list-papel-', true));

        $pdo = DB::connection()->getPdo();
        $usuarioModel = new Usuario($pdo);
        $papeis = $usuarioModel->listar_papeis((int)$request->input('usuario_id'));

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
                contexto: ['usuario_id' => (int)$request->input('usuario_id')]
            ),
            200,
            $headers,
            JSON_UNESCAPED_UNICODE
        );
    }

    /** Lista permissões de um usuário */
    public function ListarPermissoes(Request $request)
    {
        // Gerar ID único para rastreamento
        $requestId = $request->header('X-Request-Id', uniqid('usr-list-perm-', true));

        $pdo = DB::connection()->getPdo();
        $usuarioModel = new Usuario($pdo);
        $permissoes = $usuarioModel->listar_permissoes((int)$request->input('usuario_id'));

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
                contexto: ['usuario_id' => (int)$request->input('usuario_id')]
            ),
            200,
            $headers,
            JSON_UNESCAPED_UNICODE
        );
    }
}
