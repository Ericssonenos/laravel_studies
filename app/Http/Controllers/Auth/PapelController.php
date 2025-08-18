<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Auth\Papel;
use App\Services\Operations;

class PapelController extends Controller
{
    /** Lista papéis */
    public function Lista(Request $request)
    {


        // Conexão
        $pdo = DB::connection()->getPdo();
        // Módulo Papel
        $papelModel = new Papel($pdo);

        // Obter lista de papéis, passando parâmetros
        $resultadoPapeis = $papelModel->Lista($request->all());

        // Verifica se houve erro na busca
        if (is_array($resultadoPapeis) && isset($resultadoPapeis['pdo_status'])) {
            return response()->json(
                data: $resultadoPapeis,
                status: (int)$resultadoPapeis['pdo_status'],
                options: JSON_UNESCAPED_UNICODE,
                headers: [
                    'Content-Type' => 'application/problem+json; charset=utf-8',
                    'X-Request-Id' => $requestId
                ]
            );
        }

        // Gerar headers completos
        $headers = Operations::gerarHeadersCompletos(
            requestId: $requestId,
            params: $request->all(),
            baseUrl: $request->url(),
            data: $resultadoPapeis
        );

        // Padronizar resposta de sucesso
        $respostaSucesso = Operations::padronizarRespostaSucesso(
            data: $resultadoPapeis,
            msg: 'Lista de papéis retornada com sucesso.',
            contexto: $request->all()
        );

        return response()->json(
            data: $respostaSucesso,
            status: 200,
            headers: $headers,
            options: JSON_UNESCAPED_UNICODE
        );
    }

        /** Cria papel */
    public function store(Request $request)
    {
        $regras = [
            'locatario_id' => ['required', 'integer'],
            'txt_nome_papel' => ['required', 'string', 'max:120'],
            'num_nivel_papel' => ['required', 'integer'],
            'flg_ativo_papel' => ['quandoPresente', 'boolean']
        ];

        $validacao = Operations::validarRegras($request->all(), $regras);
        if ($validacao['pdo_status'] !== 200) {
            return response()->json($validacao, (int)$validacao['pdo_status'], [], JSON_UNESCAPED_UNICODE);
        }

        $pdo = DB::connection()->getPdo();
        $papelModel = new Papel($pdo);

        $resultadoNovoPapel = $papelModel->Criar(
            (int)$request->input('locatario_id'),
            (string)$request->input('txt_nome_papel'),
            (int)$request->input('num_nivel_papel'),
            $request->boolean('flg_ativo_papel', true)
        );

        if (is_array($resultadoNovoPapel) && isset($resultadoNovoPapel['pdo_status'])) {
            return response()->json($resultadoNovoPapel, (int)$resultadoNovoPapel['pdo_status'], [], JSON_UNESCAPED_UNICODE);
        }

        $resposta = Operations::padronizarRespostaSucesso(
            data: $resultadoNovoPapel,
            msg: 'Papel criado com sucesso.',
            contexto: ['locatario_id' => (int)$request->input('locatario_id', 1), 'txt_nome_papel' => (string)$request->input('txt_nome_papel')]
        );
        return response()->json($resposta, 201, [], JSON_UNESCAPED_UNICODE);
    }

    /** Atualiza papel */
    public function update(Request $request, int $id)
    {
        $regras = [
            'txt_nome_papel' => ['quandoPresente', 'string', 'max:120'],
            'num_nivel_papel' => ['quandoPresente', 'integer'],
            'flg_ativo_papel' => ['quandoPresente', 'boolean'],
        ];

        $validacao = Operations::validarRegras($request->all(), $regras);
        if ($validacao['pdo_status'] !== 200) {
            return response()->json($validacao, (int)$validacao['pdo_status'], [], JSON_UNESCAPED_UNICODE);
        }

        $pdo = DB::connection()->getPdo();
        $papelModel = new Papel($pdo);

        $dados = $request->only(['txt_nome_papel','num_nivel_papel','flg_ativo_papel']);
        $resultadoPapelAtualizado = $papelModel->atualizar($id, $dados);

        if (is_array($resultadoPapelAtualizado) && isset($resultadoPapelAtualizado['pdo_status'])) {
            return response()->json($resultadoPapelAtualizado, (int)$resultadoPapelAtualizado['pdo_status'], [], JSON_UNESCAPED_UNICODE);
        }

        $resposta = Operations::padronizarRespostaSucesso(
            data: $resultadoPapelAtualizado,
            msg: 'Papel atualizado com sucesso.',
            contexto: ['id_papel' => $id]
        );
        return response()->json($resposta, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Soft-delete */
    public function destroy(int $id)
    {
        $pdo = DB::connection()->getPdo();
        $papelModel = new Papel($pdo);

        $resultadoRemocaoPapel = $papelModel->remover_logicamente($id);
        if (is_array($resultadoRemocaoPapel) && isset($resultadoRemocaoPapel['pdo_status'])) {
            return response()->json($resultadoRemocaoPapel, (int)$resultadoRemocaoPapel['pdo_status'], [], JSON_UNESCAPED_UNICODE);
        }

        if ($resultadoRemocaoPapel === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        $resposta = Operations::padronizarRespostaSucesso(
            data: ['sucesso' => true],
            msg: 'Papel removido com sucesso.',
            contexto: ['id_papel' => $id]
        );
        return response()->json($resposta, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /* -------- Relação papeis_permissoes -------- */

    /** Atribui permissão ao papel */
    public function atribuirPermissao(Request $request, int $id_papel)
    {
        $pdo = DB::connection()->getPdo();
        $papelModel = new Papel($pdo);

        $resultadoAtribuicaoPermissao = $papelModel->atribuir_permissao($id_papel, (int)$request->input('permissao_id'));

        if (is_array($resultadoAtribuicaoPermissao) && isset($resultadoAtribuicaoPermissao['pdo_status'])) {
            return response()->json($resultadoAtribuicaoPermissao, (int)$resultadoAtribuicaoPermissao['pdo_status'], [], JSON_UNESCAPED_UNICODE);
        }

        if ($resultadoAtribuicaoPermissao === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        $resposta = Operations::padronizarRespostaSucesso(
            data: ['sucesso' => true],
            msg: 'Permissão atribuída ao papel.',
            contexto: ['id_papel' => $id_papel, 'permissao_id' => (int)$request->input('permissao_id')]
        );
        return response()->json($resposta, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Remove permissão do papel */
    public function removerPermissao(Request $request, int $id_papel)
    {
        $pdo = DB::connection()->getPdo();
        $papelModel = new Papel($pdo);

        $resultadoRemocaoPermissao = $papelModel->remover_permissao($id_papel, (int)$request->input('permissao_id'));

        if (is_array($resultadoRemocaoPermissao) && isset($resultadoRemocaoPermissao['pdo_status'])) {
            return response()->json($resultadoRemocaoPermissao, (int)$resultadoRemocaoPermissao['pdo_status'], [], JSON_UNESCAPED_UNICODE);
        }

        if ($resultadoRemocaoPermissao === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        $resposta = Operations::padronizarRespostaSucesso(
            data: ['sucesso' => true],
            msg: 'Permissão removida do papel.',
            contexto: ['id_papel' => $id_papel, 'permissao_id' => (int)$request->input('permissao_id')]
        );
        return response()->json($resposta, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Lista permissões do papel */
    public function listarPermissoes(int $id_papel)
    {
        $pdo = DB::connection()->getPdo();
        $papelModel = new Papel($pdo);

        $resultadoListaPermissoesPapel = $papelModel->listar_permissoes($id_papel);

        if (is_array($resultadoListaPermissoesPapel) && isset($resultadoListaPermissoesPapel['pdo_status'])) {
            return response()->json(
                data: $resultadoListaPermissoesPapel,
                status: (int)$resultadoListaPermissoesPapel['pdo_status'],
                options: JSON_UNESCAPED_UNICODE,
                headers: [
                    'Content-Type' => 'application/problem+json; charset=utf-8'
                ]
            );
        }

        $resposta = Operations::padronizarRespostaSucesso(
            data: $resultadoListaPermissoesPapel,
            msg: 'Lista de permissões do papel retornada com sucesso.',
            contexto: ['id_papel' => $id_papel]
        );
        return response()->json($resposta, 200, [], JSON_UNESCAPED_UNICODE);
    }
}
