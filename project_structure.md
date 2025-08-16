exemplo de commit {
git commit -m "Validação e padronização de erros/respostas para usuários

- Adiciona App\Services\Operations:
  - mapearExcecaoPDO(): normaliza PDOException para payload padronizado (http_status, error_code, sqlstate, message, detail, contexto).
  - validarRegras(): validador desacoplado (sem dependência do Illuminate) com mensagens dinâmicas e retorno padronizado.
  - padronizarRespostaSucesso()/formatarRespostaSucesso(): (opcional) helper para resposta uniforme de sucesso.

- Atualiza App\Models\Auth\Usuario:
  - Métodos CRUD retornam dados crus em sucesso.
  - Tratamento de erros DB usando Operations::mapearExcecaoPDO() (retorna payload padronizado).

- Atualiza App\Http\Controllers\Auth\UsuarioController:
  - store(): valida entrada via Operations::validarRegras(), gera senha hash, chama model e repassa resposta padronizada (erro ou sucesso).
  - Variáveis com nomes mais descritivos (resultadoDaValidacao, resultadoInsercao, respostaSucesso, etc.).
  - Mensagens e comentários em português.

- Ajustes relacionados: mensagens amigáveis, extração de coluna/valor de DETAIL do Postgres para mensagens mais explícitas.

Próximos passos:
- Padronizar os demais models (Grupo, Papel, Permissao, Locatario, etc.) para retornar dados crus e usar mapearExcecaoPDO em erros.
- Padronizar os demais controllers para usar Operations::validarRegras e montar respostas padronizadas (reaproveitar lógica do UsuarioController).
- Criar FormRequests para endpoints críticos quando conveniente.
- Adicionar testes unitários/integracao cobrindo validação e mapeamento de erros (incluindo race conditions).
- Revisar logging/telemetria para enviar 'detail' apenas a logs, não ao cliente.
"
}
