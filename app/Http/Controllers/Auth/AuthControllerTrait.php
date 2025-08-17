<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Operations;

/**
 * Trait com funcionalidades comuns para controllers de autenticação
 */
trait AuthControllerTrait
{
    /**
     * Configuração base para controllers Auth
     */
    protected function configurarAuth(Request $request, string $modelClass): array
    {
        // Extrair Request ID
        $requestId = Operations::extrairRequestId($request, strtolower(class_basename($modelClass)));

        // Conexão PDO
        $pdo = DB::connection()->getPdo();

        // Instância do modelo
        $modelo = Operations::criarInstanciaModelo($modelClass, $pdo);

        return [
            'requestId' => $requestId,
            'pdo' => $pdo,
            'modelo' => $modelo,
            'baseUrl' => $request->url()
        ];
    }

    /**
     * Processa resposta de listagem com paginação e headers
     */
    protected function processarRespostaLista(
        $resultados,
        Request $request,
        string $mensagem = 'Lista retornada com sucesso.',
        array $contextoAdicional = []
    ) {
        $requestId = Operations::extrairRequestId($request);

        // Verifica se é erro
        if (is_array($resultados) && isset($resultados['http_status'])) {
            $resposta = Operations::processarRespostaErro($resultados, $requestId);
            return response()->json(
                $resposta['dados'],
                $resposta['status'],
                $resposta['headers'],
                JSON_UNESCAPED_UNICODE
            );
        }

        // Contexto padrão para auth
        $contextoBase = ['locatario_id' => (int)$request->input('locatario_id', 1)];
        $contexto = array_merge($contextoBase, $contextoAdicional);

        // Processar resposta de sucesso com headers
        $resposta = Operations::processarRespostaComHeaders(
            dados: $resultados,
            httpStatus: 200,
            mensagem: $mensagem,
            contexto: $contexto,
            requestData: $request->all(),
            baseUrl: $request->url(),
            requestId: $requestId
        );

        return response()->json(
            $resposta['dados'],
            $resposta['status'],
            $resposta['headers'],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Processa resposta de criação com headers
     */
    protected function processarRespostaCriacao(
        $resultado,
        Request $request,
        string $mensagem = 'Registro criado com sucesso.',
        array $contextoAdicional = []
    ) {
        $requestId = Operations::extrairRequestId($request);

        // Verifica se é erro
        if (is_array($resultado) && isset($resultado['http_status'])) {
            $resposta = Operations::processarRespostaErro($resultado, $requestId);
            return response()->json(
                $resposta['dados'],
                $resposta['status'],
                $resposta['headers'],
                JSON_UNESCAPED_UNICODE
            );
        }

        // Contexto padrão
        $contextoBase = ['locatario_id' => (int)$request->input('locatario_id', 1)];
        $contexto = array_merge($contextoBase, $contextoAdicional);

        // Headers específicos para criação (201)
        $headers = Operations::gerarHeadersSeguranca($requestId);

        // Adicionar Location header se houver ID
        if (is_array($resultado) && isset($resultado['id_usuario'])) {
            $headers['Location'] = $request->url() . '/' . $resultado['id_usuario'];
        } elseif (is_array($resultado) && isset($resultado['id_grupo'])) {
            $headers['Location'] = $request->url() . '/' . $resultado['id_grupo'];
        } elseif (is_array($resultado) && isset($resultado['id_papel'])) {
            $headers['Location'] = $request->url() . '/' . $resultado['id_papel'];
        } elseif (is_array($resultado) && isset($resultado['id_permissao'])) {
            $headers['Location'] = $request->url() . '/' . $resultado['id_permissao'];
        }

    $respostaPadronizada = Operations::padronizarRespostaSucesso(data: $resultado, msg: $mensagem, contexto: $contexto);

        return response()->json(
            $respostaPadronizada,
            201,
            $headers,
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Processa resposta de operação simples (atribuição, remoção, etc.)
     */
    protected function processarRespostaOperacao(
        $resultado,
        Request $request,
        string $mensagem = 'Operação realizada com sucesso.',
        array $contextoAdicional = []
    ) {
        $requestId = Operations::extrairRequestId($request);

        // Verifica se é erro
        if (is_array($resultado) && isset($resultado['http_status'])) {
            $resposta = Operations::processarRespostaErro($resultado, $requestId);
            return response()->json(
                $resposta['dados'],
                $resposta['status'],
                $resposta['headers'],
                JSON_UNESCAPED_UNICODE
            );
        }

        // Falha genérica (model retorna false)
        if ($resultado === false) {
            $dadosErro = [
                'http_status' => 500,
                'error_code' => 'operation_failed',
                'msg' => 'Falha na operação. Tente novamente.',
                'detail' => 'A operação não pôde ser completada.',
                'contexto' => $contextoAdicional
            ];

            $resposta = Operations::processarRespostaErro($dadosErro, $requestId);
            return response()->json(
                $resposta['dados'],
                $resposta['status'],
                $resposta['headers'],
                JSON_UNESCAPED_UNICODE
            );
        }

        // Sucesso
        $headers = Operations::gerarHeadersSeguranca($requestId);
        $dadosSucesso = is_array($resultado) ? $resultado : ['sucesso' => true];
    $respostaPadronizada = Operations::padronizarRespostaSucesso(data: $dadosSucesso, msg: $mensagem, contexto: $contextoAdicional);

        return response()->json(
            $respostaPadronizada,
            200,
            $headers,
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Processa validação com headers de erro apropriados
     */
    protected function processarValidacao(array $dados, array $regras, Request $request): ?array
    {
        $validacao = Operations::validarRegras($dados, $regras);

        if ($validacao['http_status'] !== 200) {
            $requestId = Operations::extrairRequestId($request);
            $resposta = Operations::processarRespostaErro($validacao, $requestId);

            return [
                'response' => response()->json(
                    $resposta['dados'],
                    $resposta['status'],
                    $resposta['headers'],
                    JSON_UNESCAPED_UNICODE
                )
            ];
        }

        return null; // Validação OK
    }
}
