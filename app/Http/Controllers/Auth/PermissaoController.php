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
    public function Lista(Request $request)
    {
        // Gerar ID único para rastreamento de logs
        $requestId = $request->header('X-Request-Id', uniqid('perm-list-', true));

        // Conexão
        $pdo = DB::connection()->getPdo();
        // Módulo Permissão
        $permissaoModel = new Permissao($pdo);

        // Obter lista de permissões, passando parâmetros
        $resultadoPermissoes = $permissaoModel->Lista($request->all());

        // Verifica se houve erro na busca
        if (is_array($resultadoPermissoes) && isset($resultadoPermissoes['pdo_status'])) {
            return response()->json(
                data: $resultadoPermissoes,
                status: (int)$resultadoPermissoes['pdo_status'],
                options: JSON_UNESCAPED_UNICODE,
                headers: [
                    'Content-Type' => 'application/problem+json; charset=utf-8',
                    'X-Request-Id' => $requestId
                ]
            );
        }

        // Gerar headers completos (segurança + paginação quando aplicável)
        $headers = Operations::gerarHeadersCompletos(
            requestId: $requestId,
            requestData: $request->all(),
            baseUrl: $request->url(),
            dados: $resultadoPermissoes
        );

        // Padronizar resposta de sucesso
        $respostaSucesso = Operations::padronizarRespostaSucesso(
            data: $resultadoPermissoes,
            msg: 'Lista de permissões retornada com sucesso.',
            contexto: $request->all()
        );

        return response()->json(
            data: $respostaSucesso,
            status: 200,
            headers: $headers,
            options: JSON_UNESCAPED_UNICODE
        );
    }

    /** Cria permissão */
    public function Criar(Request $request)
    {
        // Validação dos dados de entrada (mesmo padrão de UsuarioController::Criar)
        $resultadoDaValidacao = Operations::validarRegras(
            params: $request->all(),
            regrasValidacao: [
                'cod_permissao' => ['required', 'string', 'max:160'],
                'txt_descricao_permissao' => ['quandoPresente', 'string', 'max:255'],
                'flg_ativo_permissao' => ['quandoPresente', 'boolean']
            ]
        );

        if ($resultadoDaValidacao['params_status'] !== 200) {
            return response()->json(
                data: $resultadoDaValidacao,
                status: (int)$resultadoDaValidacao['params_status'],
                headers: Operations::gerarHeadersCompletos($request),
                options: JSON_UNESCAPED_UNICODE
            );
        }

        $pdo = DB::connection()->getPdo();
        $permissaoModel = new Permissao($pdo);

        $params = $request->all();
        $resultadoInsercao = $permissaoModel->Criar($params);

        if (is_array($resultadoInsercao) && isset($resultadoInsercao['pdo_status']) && $resultadoInsercao['pdo_status'] !== 201) {
            return response()->json($resultadoInsercao, (int)$resultadoInsercao['pdo_status'], [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(
            data: [
                'data' => $resultadoInsercao['data'] ?? $resultadoInsercao,
                'params' => $request->all(),
                'message' => $resultadoInsercao['message'] ?? 'Permissão criada com sucesso.'
            ],
            status: $resultadoInsercao['pdo_status'] ?? 201,
            headers: Operations::gerarHeadersCompletos(
                request: $request,
                retorno: $resultadoInsercao
            ),
            options: JSON_UNESCAPED_UNICODE
        );
    }

    /** Atualiza permissão */
    public function update(Request $request, int $id)
    {
        $regras = [
            'cod_permissao' => ['quandoPresente', 'string', 'max:160'],
            'txt_descricao_permissao' => ['quandoPresente', 'string', 'max:255'],
            'flg_ativo_permissao' => ['quandoPresente', 'boolean'],
        ];

        $validacao = Operations::validarRegras($request->all(), $regras);
        if ($validacao['pdo_status'] !== 200) {
            return response()->json($validacao, (int)$validacao['pdo_status'], [], JSON_UNESCAPED_UNICODE);
        }

        $pdo = DB::connection()->getPdo();
        $permissaoModel = new Permissao($pdo);

        $dados = $request->only(['cod_permissao','txt_descricao_permissao','flg_ativo_permissao']);
        $resultadoPermissaoAtualizada = $permissaoModel->atualizar($id, $dados);

        if (is_array($resultadoPermissaoAtualizada) && isset($resultadoPermissaoAtualizada['pdo_status'])) {
            return response()->json($resultadoPermissaoAtualizada, (int)$resultadoPermissaoAtualizada['pdo_status'], [], JSON_UNESCAPED_UNICODE);
        }

        $resposta = Operations::padronizarRespostaSucesso(
            data: $resultadoPermissaoAtualizada,
            msg: 'Permissão atualizada com sucesso.',
            contexto: ['id_permissao' => $id]
        );
        return response()->json($resposta, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Soft-delete */
    public function destroy(int $id)
    {
        $pdo = DB::connection()->getPdo();
        $permissaoModel = new Permissao($pdo);

        $resultadoRemocaoPermissao = $permissaoModel->remover_logicamente($id);
        if (is_array($resultadoRemocaoPermissao) && isset($resultadoRemocaoPermissao['pdo_status'])) {
            return response()->json($resultadoRemocaoPermissao, (int)$resultadoRemocaoPermissao['pdo_status'], [], JSON_UNESCAPED_UNICODE);
        }

        if ($resultadoRemocaoPermissao === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        $resposta = Operations::padronizarRespostaSucesso(
            data: ['sucesso' => true],
            msg: 'Permissão removida com sucesso.',
            contexto: ['id_permissao' => $id]
        );
        return response()->json($resposta, 200, [], JSON_UNESCAPED_UNICODE);
    }
}
