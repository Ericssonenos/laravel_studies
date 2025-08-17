# Laravel Studies - Sistema de Controle de Acesso

## 🔐 Controle de Acesso - Guia do Usuário

### Visão Geral do Sistema

O sistema implementa um controle de acesso multinível baseado em **Locatários (Tenants)**, **Usuários**, **Grupos**, **Papéis** e **Permissões**. Esta arquitetura permite flexibilidade total para organizações de qualquer tamanho.

### 🏢 Conceitos Principais

#### **Locatário (Tenant)**
- **O que é**: Representa uma empresa, organização ou cliente no sistema
- **Para que serve**: Isolamento total de dados entre diferentes organizações
- **Exemplo prático**: "Empresa ABC", "Consultoria XYZ", "Departamento de TI"

#### **Usuário**
- **O que é**: Pessoa física que acessa o sistema
- **Relacionamento**: Cada usuário pertence a **um único locatário**
- **Exemplo prático**: "João Silva" da "Empresa ABC"

#### **Grupo**
- **O que é**: Conjunto de usuários com características similares
- **Para que serve**: Facilitar gestão de muitos usuários simultaneamente
- **Exemplo prático**: "Vendedores", "Gerentes", "Suporte Técnico"

#### **Papel (Role)**
- **O que é**: Função específica dentro da organização
- **Para que serve**: Define responsabilidades e nível hierárquico
- **Exemplo prático**: "Admin Geral", "Supervisor de Vendas", "Operador"

#### **Permissão**
- **O que é**: Ação específica que pode ser realizada no sistema
- **Para que serve**: Controle granular de acesso a funcionalidades
- **Exemplo prático**: "criar_usuario", "editar_produto", "gerar_relatorio"

---

## 🎯 Como Funciona na Prática

### Cenário 1: Empresa Pequena (10-50 funcionários)
```
Locatário: "Padaria do João"
├── Usuários: joão.dono, maria.caixa, pedro.padeiro
├── Grupos: 
│   ├── "Administradores" → joão.dono
│   └── "Funcionários" → maria.caixa, pedro.padeiro
├── Papéis:
│   ├── "Proprietário" → acesso total
│   └── "Funcionário" → acesso básico
└── Permissões:
    ├── "ver_vendas", "criar_produto" → Proprietário
    └── "registrar_venda" → Funcionário
```

### Cenário 2: Empresa Média (100+ funcionários)
```
Locatário: "TechCorp Solutions"
├── Usuários: 150+ funcionários
├── Grupos por Departamento:
│   ├── "TI" → desenvolvedores, analistas
│   ├── "RH" → recrutadores, gestores
│   ├── "Vendas" → vendedores, gerentes
│   └── "Financeiro" → contadores, diretores
├── Papéis por Hierarquia:
│   ├── "CEO" → acesso irrestrito
│   ├── "Diretor" → gestão departamental
│   ├── "Gerente" → equipe específica
│   └── "Analista" → operações básicas
└── Permissões por Funcionalidade:
    ├── Módulo Financeiro: "ver_relatorios", "aprovar_despesas"
    ├── Módulo RH: "contratar", "demitir", "avaliar"
    └── Módulo TI: "deploy", "backup", "configurar"
```

---

## 🔄 Fluxos de Trabalho Comuns

### 1. **Onboarding de Novo Funcionário**

#### Passo a Passo:
1. **Admin cria usuário** no locatário correto
2. **Adiciona aos grupos** apropriados (ex: "RH", "Novatos")
3. **Atribui papel** baseado na função (ex: "Analista RH")
4. **Permissões são herdadas** automaticamente dos grupos e papéis

#### Exemplo de API:
```http
# 1. Criar usuário
POST /api/auth/usuarios
{
    "locatario_id": 1,
    "nome": "Ana Santos",
    "email": "ana.santos@empresa.com",
    "senha": "senhaSegura123"
}

# 2. Adicionar ao grupo
POST /api/auth/usuarios/15/grupos
{ "grupo_id": 3 }

# 3. Atribuir papel
POST /api/auth/usuarios/15/papeis
{ "papel_id": 7 }
```

### 2. **Reestruturação de Departamento**

Quando um departamento muda, você pode:
- **Mover usuários** entre grupos
- **Alterar papéis** conforme nova hierarquia
- **Ajustar permissões** dos grupos/papéis (afeta todos automaticamente)

### 3. **Controle de Acesso Temporário**

Para projetos específicos:
- **Criar grupo temporário** (ex: "Projeto X")
- **Adicionar usuários necessários**
- **Atribuir permissões específicas**
- **Remover grupo** quando projeto acabar

---

## 📊 Matriz de Permissões Sugeridas

### Por Nível Hierárquico:

| Papel | Usuários | Grupos | Papéis | Permissões | Locatários |
|-------|----------|---------|---------|------------|------------|
| **Super Admin** | ✅ CRUD | ✅ CRUD | ✅ CRUD | ✅ CRUD | ✅ CRUD |
| **Admin Locatário** | ✅ CRUD | ✅ CRUD | ✅ CRUD | ✅ CRUD | ❌ Só leitura |
| **Gerente RH** | ✅ CRUD | ✅ Criar/Editar | ❌ Só leitura | ❌ Só leitura | ❌ Nenhum |
| **Supervisor** | ❌ Só leitura | ❌ Só leitura | ❌ Nenhum | ❌ Nenhum | ❌ Nenhum |
| **Usuário Comum** | ❌ Só perfil próprio | ❌ Nenhum | ❌ Nenhum | ❌ Nenhum | ❌ Nenhum |

### Por Módulo do Sistema:

| Módulo | Criar | Ler | Editar | Deletar | Aprovar |
|--------|-------|-----|--------|---------|---------|
| **Usuários** | RH, Admin | Todos supervisores | RH, Admin | Só Admin | Admin |
| **Produtos** | Vendas, Admin | Todos | Vendas, Admin | Só Admin | Gerente+ |
| **Financeiro** | Financeiro | Gerente+ | Financeiro | Diretor+ | Diretor+ |
| **Relatórios** | Analista+ | Supervisor+ | Analista+ | Admin | Gerente+ |

---

## 🛡️ Boas Práticas de Segurança

### ✅ Recomendações:

1. **Princípio do Menor Privilégio**
   - Usuários começam com permissões mínimas
   - Permissões são adicionadas conforme necessidade

2. **Separação de Responsabilidades**
   - Nunca uma pessoa sozinha tem controle total
   - Operações críticas requerem aprovação

3. **Auditoria Contínua**
   - Log de todas as ações de acesso
   - Revisão periódica de permissões

4. **Grupos por Função, Papéis por Hierarquia**
   - Grupos: "Vendedores", "Desenvolvedores"
   - Papéis: "Junior", "Pleno", "Senior", "Lead"

### ❌ Evite:

1. **Usuários com muitos papéis simultâneos**
2. **Grupos muito específicos** (ex: "João do financeiro")
3. **Permissões diretas ao usuário** (use sempre grupos/papéis)
4. **Papéis com nomes vagos** (ex: "Usuário1", "Papel_A")

---

## 🚀 Exemplos de Implementação

### Startup (5 pessoas):
```
Locatário: "StartupTech"
Grupos: ["Fundadores", "Desenvolvedores"] 
Papéis: ["CEO", "CTO", "Dev"]
Permissões: Simples, baseadas em módulos
```

### Multinacional (10000+ funcionários):
```
Locatários: Por país/região
Grupos: Por departamento + projeto
Papéis: Hierarquia complexa (L1-L10)
Permissões: Granulares por sistema
```

### E-commerce:
```
Grupos: ["Vendas", "Marketing", "Logística", "Financeiro"]
Papéis: ["Operador", "Coordenador", "Gerente", "Diretor"] 
Permissões: Por módulo do e-commerce
```

---

## 📋 Checklist de Configuração

### Ao configurar um novo locatário:

- [ ] ✅ Criar locatário principal
- [ ] ✅ Definir grupos por departamento/função
- [ ] ✅ Estabelecer hierarquia de papéis
- [ ] ✅ Mapear permissões por módulo
- [ ] ✅ Criar usuário admin inicial
- [ ] ✅ Testar fluxos principais
- [ ] ✅ Documentar estrutura para equipe

### Para cada novo usuário:

- [ ] ✅ Verificar locatário correto
- [ ] ✅ Identificar grupos apropriados
- [ ] ✅ Definir papel baseado na função
- [ ] ✅ Confirmar permissões necessárias
- [ ] ✅ Testar acesso após criação
- [ ] ✅ Programar revisão em 30 dias

---

## 🔧 Configuração Técnica

### Testando o Sistema:

Use o arquivo `routes/restclient/auth/usuario.http` para testar todas as funcionalidades:

1. **Listar usuários** com filtros
2. **Criar usuários** com validações
3. **Gerenciar grupos, papéis e permissões**
4. **Testar casos extremos**

### Estrutura do Banco:

```sql
auth.locatarios      → Empresas/Organizações
auth.usuarios        → Pessoas físicas  
auth.grupos          → Conjuntos de usuários
auth.papeis          → Funções hierárquicas
auth.permissoes      → Ações específicas

-- Tabelas de relacionamento N:M --
auth.usuario_grupos
auth.usuario_papeis  
auth.usuario_permissoes
auth.grupo_permissoes
auth.papel_permissoes
```

**💡 Lembre-se**: Um bom sistema de controle de acesso é invisível para usuários autorizados e intransponível para não autorizados.
