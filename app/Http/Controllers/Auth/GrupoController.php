<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Auth\Grupo;
use App\Services\Operations;

class GrupoController extends Controller
{
    private Grupo $grupoModel;

    public function __construct()
    {
        $this->grupoModel = new Grupo(DB::connection()->getPdo());
    }

    /** Lista grupos */
    public function Lista(Request $request)
    {
        // Obter lista de grupos, passando parâmetros
        $resultadosGrupos = $this->grupoModel->Lista($request->all());

        // Retornar com a Lista
        return response()->json(
            data: [
                'data' => $resultadosGrupos['data'],
                'message' => $resultadosGrupos['message'],
                'params' => $request->all()
            ],
            status: $resultadosGrupos['pdo_status'],
            headers: Operations::gerarHeadersCompletos(
                request: $request,
                retorno: $resultadosGrupos
            ),
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
        if ($parametrosValidados['params_status'] !== 200) {
            return response()->json(
                data: $parametrosValidados,
                status: (int)$parametrosValidados['params_status'],
                headers: Operations::gerarHeadersCompletos($request),
                options: JSON_UNESCAPED_UNICODE
            );
        }

        // Conexão com o banco de dados
        $pdoConnection = DB::connection()->getPdo();

        // Instância do Grupo
        $grupoModel = new Grupo($pdoConnection);

        // chamar o model para criar o grupo
        $resultadoNovoGrupo = $grupoModel->Criar($request->all());

        if (is_array($resultadoNovoGrupo) && isset($resultadoNovoGrupo['pdo_status'])) {
            return response()->json($resultadoNovoGrupo, (int)$resultadoNovoGrupo['pdo_status'], [], JSON_UNESCAPED_UNICODE);
        }

        $resposta = Operations::padronizarRespostaSucesso(
            data: $resultadoNovoGrupo,
            msg: 'Grupo criado com sucesso.',
            contexto: ['locatario_id' => (int)$request->input('locatario_id', 1), 'txt_nome_grupo' => (string)$request->input('txt_nome_grupo')]
        );
        return response()->json($resposta, 201, [], JSON_UNESCAPED_UNICODE);
    }

        /** Atualiza grupo */
    public function Update(Request $request)
    {
        $regras = [
            'id_grupo' => ['required', 'integer'],
            'txt_nome_grupo' => ['quandoPresente', 'string', 'max:120'],
            'txt_descricao_grupo' => ['quandoPresente', 'string', 'max:255'],
            'flg_ativo_grupo' => ['quandoPresente', 'boolean'],
        ];

        $validacao = Operations::validarRegras($request->all(), $regras);
        if ($validacao['pdo_status'] !== 200) {
            return response()->json($validacao, (int)$validacao['pdo_status'], [], JSON_UNESCAPED_UNICODE);
        }

        $dados = $request->only(['txt_nome_grupo', 'txt_descricao_grupo', 'flg_ativo_grupo']);
        $resultadoGrupoAtualizado = $this->grupoModel->atualizar((int)$request->input('id_grupo'), $dados);

        if (is_array($resultadoGrupoAtualizado) && isset($resultadoGrupoAtualizado['pdo_status'])) {
            return response()->json($resultadoGrupoAtualizado, (int)$resultadoGrupoAtualizado['pdo_status'], [], JSON_UNESCAPED_UNICODE);
        }

        $resposta = Operations::padronizarRespostaSucesso(
            data: $resultadoGrupoAtualizado,
            msg: 'Grupo atualizado com sucesso.',
            contexto: ['id_grupo' => (int)$request->input('id_grupo')]
        );
        return response()->json($resposta, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Soft-delete */
    public function Destroy(Request $request)
    {
        $resultadoRemocaoGrupo = $this->grupoModel->remover_logicamente((int)$request->input('id_grupo'));

        if (is_array($resultadoRemocaoGrupo) && isset($resultadoRemocaoGrupo['pdo_status'])) {
            return response()->json($resultadoRemocaoGrupo, (int)$resultadoRemocaoGrupo['pdo_status'], [], JSON_UNESCAPED_UNICODE);
        }

        if ($resultadoRemocaoGrupo === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        $resposta = Operations::padronizarRespostaSucesso(
            data: ['sucesso' => true],
            msg: 'Grupo removido com sucesso.',
            contexto: ['id_grupo' => (int)$request->input('id_grupo')]
        );
        return response()->json($resposta, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /* -------- Relação grupos_papeis -------- */

        /** Atribui papel ao grupo */
    public function AtribuirPapel(Request $request)
    {
        $resultadoAtribuicaoPapel = $this->grupoModel->AtribuirPapel($request->all());

        if (is_array($resultadoAtribuicaoPapel) && isset($resultadoAtribuicaoPapel['pdo_status'])) {
            return response()->json($resultadoAtribuicaoPapel, (int)$resultadoAtribuicaoPapel['pdo_status'], [], JSON_UNESCAPED_UNICODE);
        }

        if ($resultadoAtribuicaoPapel === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        $resposta = Operations::padronizarRespostaSucesso(
            data: ['sucesso' => true],
            msg: 'Papel atribuído ao grupo.',
            contexto: ['grupo_id' => (int)$request->input('grupo_id'), 'papel_id' => (int)$request->input('papel_id')]
        );
        return response()->json($resposta, 200, [], JSON_UNESCAPED_UNICODE);
    }

        /** Remove papel do grupo */
    public function RemoverPapel(Request $request)
    {
        $resultadoRemocaoPapel = $this->grupoModel->remover_papel((int)$request->input('grupo_id'), (int)$request->input('papel_id'));

        if (is_array($resultadoRemocaoPapel) && isset($resultadoRemocaoPapel['pdo_status'])) {
            return response()->json($resultadoRemocaoPapel, (int)$resultadoRemocaoPapel['pdo_status'], [], JSON_UNESCAPED_UNICODE);
        }

        if ($resultadoRemocaoPapel === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        $resposta = Operations::padronizarRespostaSucesso(
            data: ['sucesso' => true],
            msg: 'Papel removido do grupo.',
            contexto: ['grupo_id' => (int)$request->input('grupo_id'), 'papel_id' => (int)$request->input('papel_id')]
        );
        return response()->json($resposta, 200, [], JSON_UNESCAPED_UNICODE);
    }

        /** Lista papéis do grupo */
    public function ListarPapeis(Request $request)
    {
        $resultadoListaPapeisGrupo = $this->grupoModel->listar_papeis((int)$request->input('grupo_id'));

        if (is_array($resultadoListaPapeisGrupo) && isset($resultadoListaPapeisGrupo['pdo_status'])) {
            return response()->json(
                data: $resultadoListaPapeisGrupo,
                status: (int)$resultadoListaPapeisGrupo['pdo_status'],
                options: JSON_UNESCAPED_UNICODE,
                headers: [
                    'Content-Type' => 'application/problem+json; charset=utf-8'
                ]
            );
        }

        $resposta = Operations::padronizarRespostaSucesso(
            data: $resultadoListaPapeisGrupo,
            msg: 'Lista de papéis do grupo retornada com sucesso.',
            contexto: ['grupo_id' => (int)$request->input('grupo_id')]
        );
        return response()->json($resposta, 200, [], JSON_UNESCAPED_UNICODE);
    }
}
