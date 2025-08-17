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
        //Conexão
        $pdo = DB::connection()->getPdo();
        //Modulo Grupo
        $grupoModel = new Grupo($pdo);

        // Obter lista de grupos, passando parâmetros
        $resultadosGrupos = $grupoModel->Lista($request->all());

        //Verifica se houve erro na busca
        if (is_array($resultadosGrupos) && isset($resultadosGrupos['http_status'])) {
            return response()->json($resultadosGrupos, (int)$resultadosGrupos['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        // Se retorno com sucesso, padronizar retorno
        $respostaSucesso = Operations::padronizarRespostaSucesso(
            $resultadosGrupos,
            200,
            'Lista de grupos retornada com sucesso.',
            ['locatario_id' => (int)$request->input('locatario_id', 1)]
        );

        // Retornar com a Lista
        return response()->json(
            $respostaSucesso, 200, [], JSON_UNESCAPED_UNICODE
        );
    }

    /** Cria grupo */
    public function Criar(Request $request)
    {
        // Validação dos dados de entrada
        $regras = [
            'locatario_id' => ['required', 'integer'],
            'txt_nome_grupo' => ['required', 'string', 'max:120'],
            'txt_descricao_grupo' => ['quandoPresente ', 'string', 'max:255'],
            'flg_ativo_grupo' => ['quandoPresente ', 'boolean']
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
        $novo = $grupoModel->Criar($request->all());

        if (is_array($novo) && isset($novo['http_status'])) {
            return response()->json($novo, (int)$novo['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(Operations::padronizarRespostaSucesso($novo, 201, 'Grupo criado com sucesso.', ['locatario_id' => (int)$request->input('locatario_id', 1), 'txt_nome_grupo' => (string)$request->input('txt_nome_grupo')]), 201, [], JSON_UNESCAPED_UNICODE);
    }

    /** Atualiza grupo */
    public function update(Request $request, int $id)
    {
        $regras = [
            'txt_nome_grupo' => ['quandoPresente ', 'string', 'max:120'],
            'txt_descricao_grupo' => ['quandoPresente ', 'string', 'max:255'],
            'flg_ativo_grupo' => ['quandoPresente ', 'boolean'],
        ];

        $validacao = Operations::validarRegras($request->all(), $regras);
        if ($validacao['http_status'] !== 200) {
            return response()->json($validacao, (int)$validacao['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        $pdo = DB::connection()->getPdo();
        $m = new Grupo($pdo);

        $dados = $request->only(['txt_nome_grupo', 'txt_descricao_grupo', 'flg_ativo_grupo']);
        $atual = $m->atualizar($id, $dados);

        if (is_array($atual) && isset($atual['http_status'])) {
            return response()->json($atual, (int)$atual['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(Operations::padronizarRespostaSucesso($atual, 200, 'Grupo atualizado com sucesso.', ['id_grupo' => $id]), 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Soft-delete */
    public function destroy(int $id)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Grupo($pdo);

        $ok = $m->remover_logicamente($id);
        if (is_array($ok) && isset($ok['http_status'])) {
            return response()->json($ok, (int)$ok['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        if ($ok === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(Operations::padronizarRespostaSucesso(['sucesso' => true], 200, 'Grupo removido com sucesso.', ['id_grupo' => $id]), 200, [], JSON_UNESCAPED_UNICODE);
    }

    /* -------- Relação grupos_papeis -------- */

    /** Atribui papel ao grupo */
    public function atribuirPapel(Request $request, int $id_grupo)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Grupo($pdo);

        $ok = $m->atribuir_papel($id_grupo, (int)$request->input('papel_id'));

        if (is_array($ok) && isset($ok['http_status'])) {
            return response()->json($ok, (int)$ok['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        if ($ok === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(Operations::padronizarRespostaSucesso(['sucesso' => true], 200, 'Papel atribuído ao grupo.', ['id_grupo' => $id_grupo, 'papel_id' => (int)$request->input('papel_id')]), 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Remove papel do grupo */
    public function removerPapel(Request $request, int $id_grupo)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Grupo($pdo);

        $ok = $m->remover_papel($id_grupo, (int)$request->input('papel_id'));

        if (is_array($ok) && isset($ok['http_status'])) {
            return response()->json($ok, (int)$ok['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        if ($ok === false) {
            return response()->json(null, 500, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(Operations::padronizarRespostaSucesso(['sucesso' => true], 200, 'Papel removido do grupo.', ['id_grupo' => $id_grupo, 'papel_id' => (int)$request->input('papel_id')]), 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Lista papéis do grupo */
    public function listarPapeis(int $id_grupo)
    {
        $pdo = DB::connection()->getPdo();
        $m = new Grupo($pdo);

        $papeis = $m->listar_papeis($id_grupo);

        if (is_array($papeis) && isset($papeis['http_status'])) {
            return response()->json($papeis, (int)$papeis['http_status'], [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json($papeis, 200, [], JSON_UNESCAPED_UNICODE);
    }
}
