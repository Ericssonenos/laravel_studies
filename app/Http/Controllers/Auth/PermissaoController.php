<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Auth\Permissao;
use App\Services\Operations;

class PermissaoController extends Controller
{
    private Permissao $permissaoModel;

    public function __construct()
    {
        //[ ] Middleware para autenticação e autorização pode ser adicionado aqui
        $this->permissaoModel = new Permissao();
    }

    /** Lista permissões */
    public function Lista(Request $request)
    {
        // Obter lista de Permissões, passando parametros
        $resultadoPermissoes = $this->permissaoModel->Lista($request->all());

        // Retornar com a Lista
        return response()->json(
            data: [
                'data' => $resultadoPermissoes['data'],
                'message' => $resultadoPermissoes['message'],
                'params' => $request->all()
            ],
            status: $resultadoPermissoes['pdo_status'],
            headers: Operations::gerarHeadersCompletos(
                request: $request,
                retorno: $resultadoPermissoes
            ),
            options: JSON_UNESCAPED_UNICODE
        );
    }

    /** Cria permissão */
    public function Criar(Request $request)
    {
        // Validação dos dados de entrada
        $resultadoDaValidacao = Operations::validarRegras(
            params: $request->all(),
            regrasValidacao: [
                'cod_permissao' => ['required', 'string', 'max:160'],
                'txt_descricao_permissao' => ['quandoPresente', 'string', 'max:255'],
                'flg_ativo_permissao' => ['quandoPresente', 'boolean']
            ]
        );

        // Verifica se houve erro na validação
        if ($resultadoDaValidacao['params_status'] !== 200) {
            return response()->json(
                data: $resultadoDaValidacao,
                status: (int)$resultadoDaValidacao['params_status'],
                headers: Operations::gerarHeadersCompletos($request),
                options: JSON_UNESCAPED_UNICODE
            );
        }

        // Chamar o model para criar a permissão
        $resultadoInsercao = $this->permissaoModel->Criar($request->all());

        // Padronizar resposta
        return response()->json(
            data: [
                'data' => $resultadoInsercao['data'],
                'params' => $request->all(),
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

    /** Atualiza permissão */
    public function update(Request $request, int $id)
    {
        // Validação dos dados de entrada
        $resultadoDaValidacao = Operations::validarRegras(
            params: $request->all(),
            regrasValidacao: [
                'cod_permissao' => ['quandoPresente', 'string', 'max:160'],
                'txt_descricao_permissao' => ['quandoPresente', 'string', 'max:255'],
                'flg_ativo_permissao' => ['quandoPresente', 'boolean']
            ]
        );

        // Verifica se houve erro na validação
        if ($resultadoDaValidacao['params_status'] !== 200) {
            return response()->json(
                data: $resultadoDaValidacao,
                status: (int)$resultadoDaValidacao['params_status'],
                headers: Operations::gerarHeadersCompletos($request),
                options: JSON_UNESCAPED_UNICODE
            );
        }

        // Preparar dados para atualização
        $dados = $request->only(['cod_permissao','txt_descricao_permissao','flg_ativo_permissao']);
        $resultadoPermissaoAtualizada = $this->permissaoModel->atualizar($id, $dados);

        // Padronizar resposta
        return response()->json(
            data: [
                'data' => $resultadoPermissaoAtualizada,
                'params' => $request->all(),
                'message' => 'Permissão atualizada com sucesso.'
            ],
            status: 200,
            headers: Operations::gerarHeadersCompletos(
                request: $request,
                retorno: ['data' => $resultadoPermissaoAtualizada]
            ),
            options: JSON_UNESCAPED_UNICODE
        );
    }

    /** Soft-delete */
    public function destroy(int $id)
    {
        // Chamar o método de remoção lógica
        $resultadoRemocaoPermissao = $this->permissaoModel->remover_logicamente($id);

        // Padronizar resposta
        return response()->json(
            data: [
                'data' => ['sucesso' => true],
                'message' => 'Permissão removida com sucesso.',
                'contexto' => ['id_permissao' => $id]
            ],
            status: 200,
            headers: Operations::gerarHeadersCompletos(
                request: request(),
                retorno: ['data' => $resultadoRemocaoPermissao]
            ),
            options: JSON_UNESCAPED_UNICODE
        );
    }
}
