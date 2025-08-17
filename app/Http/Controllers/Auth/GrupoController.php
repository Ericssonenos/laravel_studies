<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Auth\Grupo;
use App\Services\Operations;

class GrupoController extends Controller
{
    /** Lista grupos com filtros/opções */
    public function Lista(Request $request)
    {
        // Gerar ID único para rastreamento de logs
        $requestId = $request->header('X-Request-Id', uniqid('grp-list-', true));

        // Conexão
        $pdo = DB::connection()->getPdo();
        // Módulo Grupo
        $grupoModel = new Grupo($pdo);

        // Obter lista de grupos, passando parâmetros
        $resultadosGrupos = $grupoModel->Lista($request->all());

        // Verifica se houve erro na busca
        if (is_array($resultadosGrupos) && isset($resultadosGrupos['http_status'])) {
            return response()->json(
                data: $resultadosGrupos,
                status: (int)$resultadosGrupos['http_status'],
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
            requestData: $request->all(),
            baseUrl: $request->url(),
            dados: $resultadosGrupos
        );

        // Padronizar resposta de sucesso
        $respostaSucesso = Operations::padronizarRespostaSucesso(
            data: $resultadosGrupos,
            msg: 'Lista de grupos retornada com sucesso.',
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

    /** Cria grupo */
    public function Criar(Request $request)
    {
        // Validação dos dados de entrada
        $regras = [
            'locatario_id' => ['required', 'integer'],
            'txt_nome_grupo' => ['required', 'string', 'max:120'],
            'txt_descricao_grupo' => ['quandoPresente', 'string', 'max:255'],
            'flg_ativo_grupo' => ['quandoPresente', 'boolean']
        ];

        $parametrosValidados = Operations::validarRegras($request->all(), $regras);

        // Verifica se houve erro na validação
        if ($parametrosValidados['http_status'] !== 200) {
            return response()->json($parametrosValidados, (int)$parametrosValidados['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        // Conexão com o banco de dados
        $pdoConnection = DB::connection()->getPdo();

        // Instância do Grupo
        $grupoModel = new Grupo($pdoConnection);

        // chamar o model para criar o grupo
        $resultadoNovoGrupo = $grupoModel->Criar($request->all());

        if (is_array($resultadoNovoGrupo) && isset($resultadoNovoGrupo['http_status'])) {
            return response()->json($resultadoNovoGrupo, (int)$resultadoNovoGrupo['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        $resposta = Operations::padronizarRespostaSucesso(
            data: $resultadoNovoGrupo,
            msg: 'Grupo criado com sucesso.',
            contexto: ['locatario_id' => (int)$request->input('locatario_id', 1), 'txt_nome_grupo' => (string)$request->input('txt_nome_grupo')]
        );
        return response()->json($resposta, 201, [], JSON_UNESCAPED_UNICODE);
    }

    /** Atualiza grupo */
    public function update(Request $request, int $id)
    {
        $regras = [
            'txt_nome_grupo' => ['quandoPresente', 'string', 'max:120'],
            'txt_descricao_grupo' => ['quandoPresente', 'string', 'max:255'],
            'flg_ativo_grupo' => ['quandoPresente', 'boolean'],
        ];

        $validacao = Operations::validarRegras($request->all(), $regras);
        if ($validacao['http_status'] !== 200) {
            return response()->json($validacao, (int)$validacao['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        $pdo = DB::connection()->getPdo();
        $grupoModel = new Grupo($pdo);

        $dados = $request->only(['txt_nome_grupo', 'txt_descricao_grupo', 'flg_ativo_grupo']);
        $resultadoGrupoAtualizado = $grupoModel->atualizar($id, $dados);

        if (is_array($resultadoGrupoAtualizado) && isset($resultadoGrupoAtualizado['http_status'])) {
            return response()->json($resultadoGrupoAtualizado, (int)$resultadoGrupoAtualizado['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        $resposta = Operations::padronizarRespostaSucesso(
            data: $resultadoGrupoAtualizado,
            msg: 'Grupo atualizado com sucesso.',
            contexto: ['id_grupo' => $id]
        );
        return response()->json($resposta, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Soft-delete */
    public function destroy(int $id)
    {
        $pdo = DB::connection()->getPdo();
        $grupoModel = new Grupo($pdo);

        $resultadoRemocaoGrupo = $grupoModel->remover_logicamente($id);
        if (is_array($resultadoRemocaoGrupo) && isset($resultadoRemocaoGrupo['http_status'])) {
            return response()->json($resultadoRemocaoGrupo, (int)$resultadoRemocaoGrupo['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        if ($resultadoRemocaoGrupo === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        $resposta = Operations::padronizarRespostaSucesso(
            data: ['sucesso' => true],
            msg: 'Grupo removido com sucesso.',
            contexto: ['id_grupo' => $id]
        );
        return response()->json($resposta, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /* -------- Relação grupos_papeis -------- */

    /** Atribui papel ao grupo */
    public function atribuirPapel(Request $request, int $id_grupo)
    {
        $pdo = DB::connection()->getPdo();
        $grupoModel = new Grupo($pdo);

        $resultadoAtribuicaoPapel = $grupoModel->atribuir_papel($id_grupo, (int)$request->input('papel_id'));

        if (is_array($resultadoAtribuicaoPapel) && isset($resultadoAtribuicaoPapel['http_status'])) {
            return response()->json($resultadoAtribuicaoPapel, (int)$resultadoAtribuicaoPapel['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        if ($resultadoAtribuicaoPapel === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        $resposta = Operations::padronizarRespostaSucesso(
            data: ['sucesso' => true],
            msg: 'Papel atribuído ao grupo.',
            contexto: ['id_grupo' => $id_grupo, 'papel_id' => (int)$request->input('papel_id')]
        );
        return response()->json($resposta, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Remove papel do grupo */
    public function removerPapel(Request $request, int $id_grupo)
    {
        $pdo = DB::connection()->getPdo();
        $grupoModel = new Grupo($pdo);

        $resultadoRemocaoPapel = $grupoModel->remover_papel($id_grupo, (int)$request->input('papel_id'));

        if (is_array($resultadoRemocaoPapel) && isset($resultadoRemocaoPapel['http_status'])) {
            return response()->json($resultadoRemocaoPapel, (int)$resultadoRemocaoPapel['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        if ($resultadoRemocaoPapel === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        $resposta = Operations::padronizarRespostaSucesso(
            data: ['sucesso' => true],
            msg: 'Papel removido do grupo.',
            contexto: ['id_grupo' => $id_grupo, 'papel_id' => (int)$request->input('papel_id')]
        );
        return response()->json($resposta, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Lista papéis do grupo */
    public function listarPapeis(int $id_grupo)
    {
        $pdo = DB::connection()->getPdo();
        $grupoModel = new Grupo($pdo);

        $resultadoListaPapeisGrupo = $grupoModel->listar_papeis($id_grupo);

        if (is_array($resultadoListaPapeisGrupo) && isset($resultadoListaPapeisGrupo['http_status'])) {
            return response()->json(
                data: $resultadoListaPapeisGrupo,
                status: (int)$resultadoListaPapeisGrupo['http_status'],
                options: JSON_UNESCAPED_UNICODE,
                headers: [
                    'Content-Type' => 'application/problem+json; charset=utf-8'
                ]
            );
        }

        $resposta = Operations::padronizarRespostaSucesso(
            data: $resultadoListaPapeisGrupo,
            msg: 'Lista de papéis do grupo retornada com sucesso.',
            contexto: ['id_grupo' => $id_grupo]
        );
        return response()->json($resposta, 200, [], JSON_UNESCAPED_UNICODE);
    }
}
