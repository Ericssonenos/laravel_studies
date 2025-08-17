CONVENÇÕES DE NOMENCLATURA E PADRÕES DE CLAREZA (Regra registrada)

Objetivo

Este documento registra, de forma definitiva e consultável, as regras de nomenclatura, estilo e legibilidade a serem aplicadas em todo o código do projeto antes de qualquer alteração. Ele existe para garantir: clareza absoluta do fluxo de dados, rastreabilidade fácil durante debugging, consistência entre desenvolvedores e manutenção sem ambiguidade.

Princípios gerais (regra nº 1 — sempre consultar)

1) Clareza acima da economia
   - Prefira nomes completos e descritivos a abreviações indecifráveis. Se houver dúvida entre um nome curto e um nome explícito, escolha o explícito.
   - Exemplo: use `$id_grupo_usuario`.

2) Nomes em português (exceto termos técnicos universais)
   - Todos os identificadores (variáveis, parâmetros, nomes de métodos, nomes de classes auxiliares, mensagens, comentários) devem estar em português claro e preciso.
   - Exceção: termos técnicos que não têm tradução prática ou reconhecida, como `UUID`, `API`, `OAuth`, `JSON`, podem permanecer em inglês.

3) Verbosidade consciente
   - Seja verboso quando isso trouxer ganho de entendimento. Nomes longos são aceitáveis se fornecerem contexto direto e reduzirem a necessidade de comentários adicionais.
  

4) Consistência com o estilo já presente no projeto
   - O código existente usa `snake_case` para nomes relacionados a banco de dados e funções/métodos (ex.: `procurar_por_id`, `id_grupo`). Siga esse padrão salvo motivos fortes e justificados para divergir.



A) Foreach com pares chave/valor
   - Nunca usar variáveis genéricas e ambíguas como `$k` / `$v` em loops que lidam com arrays associativos. Use  `$chave` e `$valor` ou nomes mais específicos quando o contexto permitir.
   - Exemplos:
   - Quando o array representa algo mais concreto, prefira nomes específicos: ex.: `$nome_coluna => $valor_parametro`, `$coluna => $valor`, `$campo => $valorCampo`.

B) Índices numéricos em loops
   - Para loops indexados, use `$indice` e `$valor` ou nomes contextuais (`$posicao`, `$contador`).

C) Nomes de variáveis booleanas
   - Devem iniciar por `flg_` ou usar prefixo `esta_`/`tem_` para clareza. Ex.: `flg_ativo`, `esta_autenticado`, `tem_permissao`.

D) Identificadores (IDs)
   - Use sufixos claros: `id_usuario`, `id_grupo`, `id_locatario`.
   - Para evitar ambiguidade, não use apenas `id` em funções que manipulam múltiplos tipos; prefira `id_<entidade>`.

E) Dados de contexto para logging/erros
   - Ao passar contexto para mapeadores de erro (ex.: `Operations::mapearExcecaoPDO`), passe sempre um array com chaves descritivas: ex.: `['funcao' => 'Grupo::inserir', 'locatario_id' => $locatario_id, 'nome_grupo' => $nome]`.

F) Mensagens e comentários
   - Mensagens para usuário (erros, validações) em português uniforme (PT-BR). Comentários de código também em português.

G) Métodos e funções
   - Nomes de métodos e variaveis devem ser verbosos e descrever claramente a ação e o alvo: ex.: `atribuir_papel`, `remover_logicamente`, `listar_papeis`, `Retorno_Usuario`.

H) Tratamento de exceções
   - Em métodos que retornam booleanos, mapear/logar a exceção e retornar `false` — não retornar um array de erro diretamente (isso quebra o contrato de tipo). Ex.:
     ```php
     try {
         // operação
     } catch (\PDOException $e) {
         Operations::mapearExcecaoPDO($e, ['funcao' => 'MeuModelo::acao', 'id' => $id]);
         return false;
     }
     ```

I) Quando retornar payloads padronizados
   - Métodos que retornam dados (arrays de entidades ou objetos) podem devolver, em caso de erro, o array padronizado gerado por `Operations::mapearExcecaoPDO`.
   - Controllers devem interpretar esse padrão: se o retorno contém `http_status` trate como erro já mapeado.

Regra de verificação pré-editar

Antes de executar qualquer alteração automatizada no código:
1. Abra este arquivo `docs/CONVENCOES_DE_NOMENCLATURA.md` e confirme a(s) regra(s) aplicáveis.




Arquivo localizado em: `docs/CONVENCOES_DE_NOMENCLATURA.md`

## Regras detalhadas para banco de dados (práticas e convencões)

Esta seção complementa as convenções gerais de nomenclatura com regras específicas para modelagem, estruturação física e governança de objetos no banco de dados.
Leia e siga estas regras antes de criar ou alterar tabelas, colunas, índices, constraints, funções, triggers e outros objetos.

1) Idioma e formato
   - Tudo em português (PT-BR) com snake_case para nomes de tabelas, colunas, índices e constraints.
   - Use inglês apenas para termos técnicos sem tradução prática (`UUID`, `API`, `JSON`).

2) Schema e organização
   - Separe objetos por schema funcional quando fizer sentido (ex.: `auth`, `faturas`, `inventario`).
   - Prefira sempre referenciar objetos com schema qualificado em DDLs e em funções críticas: `auth.usuarios`.
   - Não dependa exclusivamente de `search_path` em scripts de criação; use nomes qualificados para evitar ambiguidades entre ambientes.

3) Tabelas
   - Nome das tabelas: plural em português (ex.: `usuarios`, `locatarios`, `grupos`).
   - Colunas obrigatórias para todas as tabelas transacionais:
     - `dat_criado_em TIMESTAMPTZ NOT NULL DEFAULT now()`
     - `dat_atualizado_em TIMESTAMPTZ NOT NULL DEFAULT now()` (trigger atualiza automaticamente)
     - `dat_cancelamento_em TIMESTAMPTZ NULL` (soft delete)
   - Evite deletes físicos em tabelas transacionais; caso necessário crie política documentada e revisada.

4) Tipos e prefixos de colunas
   - Use prefixos padronizados (conforme tabela nesta mesma documentação): `id_`, `txt_`, `cod_`, `num_`, `dat_`, `flg_`, `jsn_`, etc.
   - Para textos curtos use `VARCHAR(n)` com n definido (ex.: `txt_nome_usuario VARCHAR(255)`), para textos longos use `TEXT`.
   - Use `timestamptz` (timestamp with time zone) para eventos temporais globais.
   - Para campos que guardarão JSON use `jsonb` quando for necessário indexação/consulta por chave; prefixo `jsn_`.

5) IDs e chaves primárias
   - PK: `id_<entidade>` como `BIGSERIAL` ou `BIGINT` com sequence, salvo quando usar `UUID`.
   - Nome da PK (constraint): `pk_<tabela>` (ex.: `pk_usuarios`).
   - Se usar `UUID`, campo `id_<entidade> UUID DEFAULT gen_random_uuid()` e mantenha comentário justificando escolha.

6) Chaves estrangeiras
   - Nome das FKs: `fk_<tabela>_<tabela_referenciada>` (ex.: `fk_usuarios_locatarios`).
   - Tipos devem coincidir entre PK e FK (ex.: ambos BIGINT ou ambos UUID).
   - Preferência por `ON DELETE RESTRICT` ou `ON DELETE NO ACTION` para preservar integridade; use `SET NULL` apenas quando a regra de negócio permitir a nulificação e documente-a.
   - Em modelos multi-tenant, inclua `locatario_id` (ou `tenant_id`) em todas as tabelas relevantes e considere FK para `locatarios.id_locatario` quando aplicável.

7) Constraints e unicidade
   - Nome das constraints de unicidade: `uq_<tabela>_<campo>` ou `uq_<tabela>_<campo1>_<campo2>`.
   - Para unicidade por locatário (multi-tenant), defina o índice/constraint incluindo `locatario_id`. Ex.: `UNIQUE (locatario_id, lower(txt_email_usuario))` com constraint `uq_usuarios_email_por_locatario`.
   - Para comparações case-insensitive, considere criar índices usando `lower()` e declarar unicidade sobre `lower(txt_coluna)`.

8) Índices
   - Nomenclatura: `ix_<tabela>_<campo>` ou `ix_<tabela>_<campo1>_<campo2>` para índices compostos.
   - Crie índices em colunas FK, colunas usadas em filtros frequentes e em colunas usadas em ORDER BY quando necessário.
   - Para consultas que filtram por prefixos ou padrões, prefira índices específicos (GIN/BTREE conforme tipo e consulta).
   - Evite criar índices desnecessários; cada índice tem custo de escrita.

9) Junction tables (N:N)
   - Nome: `<entidade1>_<entidade2>` em ordem alfabética quando não houver uma entidade dominante (ex.: `grupos_papeis`).
   - PK: combinação das FKs ou adição de PK `id_<junction>` se necessário.
   - Constraints: `UNIQUE (entidade1_id, entidade2_id)` com nome `uq_<junction>_<cols>`.

10) Funções e triggers
   - Guardar funções e triggers no schema funcional apropriado (ex.: funções de `auth` dentro de `auth` quando referente ao módulo).
   - Usar nomenclatura descritiva: `fn_<acao>_<tabela>` ou `tg_<acao>_<tabela>` (ex.: `fn_set_dat_atualizado_em`, `tg_set_dat_atualizado_em`).
   - Documente a finalidade e os efeitos colaterais das funções com `COMMENT ON FUNCTION`/`COMMENT ON TRIGGER`.
   - Ao criar triggers que atualizam timestamps, prefira implementações idempotentes e com checks para evitar loops.

11) Padrões de escrita de DDL/DDL idempotente
   - Use construções condicionais quando possível (ex.: `CREATE TABLE IF NOT EXISTS`, blocos `DO $$ BEGIN ... END $$` para criação de funções/trigger quando não existirem).
   - Sempre testar DDL em ambiente de staging antes de aplicar em produção.


17) Nomeclatura de objetos auxiliares
   - Sequences: `seq_<tabela>_<coluna>` (ex.: `seq_usuarios_id_usuario`) — quando criadas manualmente.
   - Views: `vw_<area>_<descricao>` (ex.: `vw_relatorio_vendas_mensal`).
   - Materialized views: `mv_<area>_<descricao>`.

18) Convenções para nomes de constraints e índices (resumo)
   - PK: `pk_<tabela>`
   - FK: `fk_<tabela>_<tabela_referenciada>`
   - UNIQUE: `uq_<tabela>_<campo[_campo]>`
   - INDEX: `ix_<tabela>_<campo[_campo]>`
   - CHECK: `ck_<tabela>_<descricao>`

--
Fim da seção: Regras detalhadas para banco de dados.

## Fluxo e filosofia para cadastro e gestão de entidades (modelo reutilizável)

Objetivo

Princípios centrais

- Desacoplamento máximo: manter services e models independentes do framework; controllers atuam apenas como adaptadores HTTP/CLI. Serviços e modelos devem ser testáveis isoladamente e receber dependências (ex.: PDO) por parâmetro.
- Nomes e contratos em português: parâmetros, chaves de contexto e mensagens em PT-BR; termos técnicos universais (UUID, JSON, API) podem ficar em inglês.


Responsabilidades e contratos

1) Controller (adapter HTTP)
   - Função: receber Request, chamar validação desacoplada, orquestrar chamada ao model/service e formatar a resposta final.
   - Deve usar apenas recursos do framework estritamente necessários (rotas, request/response helpers). Não deve conter regras de negócio.

2) Service de operações (ex.: `App\Services\Operations`)
   - Validação desacoplada: `validarRegras(array $dados, array $regras, array $mensagens = [], array $contexto = []): array` — retorna objeto padronizado com `http_status`, `msg`, `detail`, `contexto`.
   - Mapeamento de erros: `mapearExcecaoPDO(PDOException $e, array $contexto = []): array` — converte erros do banco em mensagens e códigos apropriados.
   - Helpers de resposta: `padronizarRespostaSucesso(array $dados, int $httpStatus = 201, string $mensagem = '', array $contexto = []): array`.


4) Uso obrigatório de `App\Services\Operations` (regras do projeto)
    - Todos os controllers e models devem usar `App\Services\Operations` como utilitário central para:
       - validação desacoplada: `Operations::validarRegras(...)`
       - mapeamento de exceções de banco: `Operations::mapearExcecaoPDO(...)`
       - padronização de respostas de sucesso: `Operations::padronizarRespostaSucesso(...)`
    - Regra de propagação de erros: sempre que um model retornar um array que contenha `http_status`, o controller deve repassar esse payload diretamente para o cliente, preservando `http_status`, `msg`, `detail` e `contexto`. Exemplo:


Padronização de mensagens e contexto

- Sempre passar contexto em português para logs/erros: ex.: `['funcao' => 'Produto::inserir', 'produto_cod' => $codigo]`.
- Mensagens para o cliente em PT-BR, curtas e informativas. Manter o detalhe técnico apenas em logs.
- Resposta padronizada (estrutura única para sucesso e erro):


- Regra de codificação JSON (obrigatória)
    - Todas as respostas JSON retornadas pelos controllers devem usar a flag PHP `JSON_UNESCAPED_UNICODE` para evitar que caracteres acentuados sejam escapados (ex.: "Usuário" aparecendo como "Usu\u00e1rio").
    - Exemplo de uso em controller Laravel:

        ```php
        return response()->json($payload, $status, [], JSON_UNESCAPED_UNICODE);
        ```




## Regras de commits e fluxo de documentação

Objetivo

Formato recomendado de mensagem de commit

Linha de título (imperativo, conciso)

Corpo com seções claras:
- Breve descrição expandida (1-2 linhas)
- Lista de mudanças (bullets) com caminhos ou módulos afetados
- Próximos passos ou observações (quando aplicável)

Exemplo:

commit -m "Validação e padronização de erros/respostas para usuários

- Adiciona App\Services\Operations:
   - mapearExcecaoPDO(): normaliza PDOException para payload padronizado (http_status, error_code, sqlstate, msg, detail, contexto).
   - validarRegras(): validador desacoplado (sem dependência do Illuminate) com mensagens dinâmicas e retorno padronizado.
   - padronizarRespostaSucesso(): helper para resposta uniforme de sucesso.

- Atualiza App\Models\Auth\Usuario:
   - Métodos CRUD retornam dados crus em sucesso.
   - Tratamento de erros DB usando Operations::mapearExcecaoPDO().

- Atualiza App\Http\Controllers\Auth\UsuarioController:
   - Criar(): valida entrada via Operations::validarRegras(), gera senha hash, chama model e repassa resposta padronizada.

Próximos passos:
- Padronizar demais models (Grupo, Papel, Permissao, Locatario).
- Atualizar controllers para usar Operations::validarRegras e padronizar respostas.
"

Checklist rápido antes de commitar

A IA não ira gerar comando do git. mas irá solicitar quais comandos deve ser feito juntamente com o texto
1. `git status` — verifique o que está sendo incluído.
2. `git add` apenas os arquivos relacionados à responsabilidade do commit.
3. Use o template acima e ajuste o conteúdo.
4. Rode testes/linters rápidos se aplicável.
5. Faça o commit e abra PR com descrição resumida e link para `docs/CONVENCOES_DE_NOMENCLATURA.md` se uma regra nova foi adicionada.


