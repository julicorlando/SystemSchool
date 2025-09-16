# Banco de Questões - Documentação

## Visão Geral
Este módulo implementa um sistema completo de banco de questões para a aplicação sischool, permitindo que professores criem, gerenciem e organizem questões para provas de múltipla escolha.

## Funcionalidades Implementadas

### 1. Gestão de Matérias
- **Arquivo**: `listar_materias.php`
- **Funcionalidades**:
  - Cadastro de novas matérias
  - Listagem de todas as matérias
  - Exclusão de matérias (apenas para administradores)
  - Acesso tanto para professores quanto administradores

### 2. Gestão de Questões
- **Cadastro**: `cadastro_questao.php`
  - Formulário completo para criação de questões
  - Campos: enunciado, 4 alternativas (A-D), resposta correta, matéria
  - Validação e sanitização de dados
  - Vinculação automática ao professor logado

- **Listagem**: `listar_questoes.php`
  - Exibição de todas as questões do professor
  - Filtro por matéria
  - Destaque da resposta correta
  - Ações: editar e excluir
  - Layout responsivo e intuitivo

- **Edição**: `editar_questao.php`
  - Formulário pré-preenchido para edição
  - Validação de propriedade (professor só edita suas questões)
  - Atualização com timestamp

### 3. Gestão de Provas
- **Criação**: `cadastro_prova.php`
  - Formulário para criação de provas
  - Seleção de matéria e quantidade de questões
  - Sorteio automático de questões
  - Validação de questões disponíveis
  - JavaScript para feedback dinâmico

- **Listagem**: `listar_provas.php`
  - Exibição de todas as provas do professor
  - Informações completas (matéria, quantidade, data)
  - Ações: visualizar, editar, imprimir, excluir

- **Visualização**: `visualizar_prova.php`
  - Pré-visualização completa da prova
  - Destaque das respostas corretas
  - Gabarito visível para o professor
  - Layout educacional profissional

- **Edição**: `editar_prova.php`
  - Edição de informações da prova
  - Função de re-sorteio de questões
  - Validações de segurança

- **Impressão**: `imprimir_prova.php`
  - Layout otimizado para impressão
  - Modo com e sem gabarito
  - Cabeçalho profissional
  - Espaços para dados do aluno
  - Gabarito final formatado

### 4. Navegação e Interface
- **Dashboard do Professor**: Atualizado com links para o Banco de Questões
- **Dashboard do Admin**: Link para gestão de matérias
- **Interface Consistente**: Utiliza o CSS existente do sistema
- **Responsividade**: Funciona em dispositivos móveis

## Estrutura do Banco de Dados

### Tabela: `materias`
```sql
- id (INT, AUTO_INCREMENT, PRIMARY KEY)
- nome (VARCHAR(100), NOT NULL)
- descricao (TEXT)
- created_at (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)
```

### Tabela: `questoes`
```sql
- id (INT, AUTO_INCREMENT, PRIMARY KEY)
- enunciado (TEXT, NOT NULL)
- alternativa_a (VARCHAR(500), NOT NULL)
- alternativa_b (VARCHAR(500), NOT NULL)
- alternativa_c (VARCHAR(500), NOT NULL)
- alternativa_d (VARCHAR(500), NOT NULL)
- resposta_correta (ENUM('A', 'B', 'C', 'D'), NOT NULL)
- materia_id (INT, FK para materias.id)
- professor_id (INT, FK para professores.id)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)
```

### Tabela: `provas`
```sql
- id (INT, AUTO_INCREMENT, PRIMARY KEY)
- titulo (VARCHAR(200), NOT NULL)
- descricao (TEXT)
- materia_id (INT, FK para materias.id)
- professor_id (INT, FK para professores.id)
- total_questoes (INT, DEFAULT 0)
- created_at (TIMESTAMP)
```

### Tabela: `prova_questoes`
```sql
- id (INT, AUTO_INCREMENT, PRIMARY KEY)
- prova_id (INT, FK para provas.id)
- questao_id (INT, FK para questoes.id)
- ordem (INT, NOT NULL)
- created_at (TIMESTAMP)
- UNIQUE KEY (prova_id, questao_id)
```

## Fluxo de Trabalho

1. **Professor acessa o sistema** → Dashboard Professor
2. **Gerencia matérias** → `listar_materias.php`
3. **Cadastra questões** → `cadastro_questao.php`
4. **Visualiza suas questões** → `listar_questoes.php`
5. **Cria prova** → `cadastro_prova.php` (questões sorteadas automaticamente)
6. **Gerencia provas** → `listar_provas.php`
7. **Visualiza/Imprime prova** → `visualizar_prova.php` / `imprimir_prova.php`

## Recursos de Segurança

- **Autenticação**: Verificação de sessão em todas as páginas
- **Autorização**: Professores só acessam suas próprias questões/provas
- **Validação**: Sanitização de dados de entrada
- **Prepared Statements**: Proteção contra SQL injection
- **Validação de Propriedade**: Verificação de ownership antes de operações

## Características Técnicas

- **PHP Puro**: Sem frameworks, usando apenas PHP nativo
- **MySQL**: Banco de dados relacional com chaves estrangeiras
- **Responsive Design**: Interface adaptável a diferentes telas
- **Print-Ready**: Layouts otimizados para impressão
- **JavaScript Minimal**: Apenas para melhorar UX (não obrigatório)
- **Integração Completa**: Funciona com o sistema existente

## Instalação

1. Execute o script `banco_questoes_schema.sql` no banco de dados 'escola'
2. Os arquivos PHP já estão prontos para uso
3. Acesse via Dashboard do Professor após login

## Matérias Pré-Cadastradas

O sistema inclui 10 matérias pré-cadastradas:
- Matemática, Português, História, Geografia, Ciências
- Inglês, Física, Química, Biologia, Educação Física