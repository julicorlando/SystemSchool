# Correção de Contagem e Exibição de Atividades Pendentes

## Resumo das Alterações

Este documento descreve as alterações implementadas para corrigir a contagem e exibição de atividades pendentes para alunos conforme os requisitos:

1. **Não considerar como pendentes atividades do tipo 'conteudo'**
2. **Não considerar como pendentes atividades do tipo 'atividade' que o aluno já enviou resposta**

## Alterações Implementadas

### 1. Migração de Banco de Dados (migration_add_tipo_atividade.php)

Um script de migração foi criado para adicionar o campo `tipo` na tabela `atividades`:

- Campo: `tipo ENUM('conteudo', 'atividade') NOT NULL DEFAULT 'atividade'`
- Posição: Após o campo `descricao`
- Valor padrão: `'atividade'` (para manter compatibilidade com atividades existentes)

**Como executar a migração:**
1. Acesse o arquivo via navegador: `http://seu-servidor/migration_add_tipo_atividade.php`
2. O script verificará se o campo já existe antes de adicioná-lo
3. Execute apenas uma vez

### 2. Atualização do Formulário de Criação de Atividades (atividades.php)

**Alterações no formulário do professor:**
- Adicionado campo de seleção de tipo antes do título:
  - "Atividade (requer resposta do aluno)"
  - "Conteúdo (apenas para leitura)"
- O campo `tipo_atividade` é incluído no INSERT da atividade

**Alterações na exibição de atividades do professor:**
- Cada atividade exibe uma badge colorida indicando seu tipo:
  - ATIVIDADE (roxo #9b59b6)
  - CONTEÚDO (azul #3498db)

### 3. Visualização de Atividades para Alunos (atividades.php)

**Badges de Status:**
- **CONTEÚDO** (azul): Atividades do tipo 'conteudo' - não requerem resposta
- **PENDENTE** (vermelho): Atividades do tipo 'atividade' sem resposta enviada
- **RESPONDIDA** (verde): Atividades do tipo 'atividade' com resposta já enviada

**Lógica de envio de resposta:**
- Botão "Enviar Resposta" aparece APENAS para:
  - Atividades do tipo 'atividade'
  - Quando a turma não está finalizada
  - Quando o aluno ainda não enviou resposta

### 4. Contador de Atividades Pendentes no Dashboard do Aluno (dashboard_aluno.php)

**Nova funcionalidade:**
- Badge numérico vermelho no botão "Atividades"
- Mostra a quantidade de atividades pendentes
- Aparece apenas quando há atividades pendentes (> 0)

**Query de contagem:**
```sql
SELECT COUNT(*) as total FROM atividades a 
WHERE a.turma_id=$turma_id 
AND (a.tipo='atividade' OR a.tipo IS NULL)
AND NOT EXISTS (
    SELECT 1 FROM respostas r 
    WHERE r.atividade_id=a.id AND r.aluno_id=$id
)
```

**Critérios:**
- Considera apenas atividades da turma do aluno
- Inclui atividades do tipo 'atividade' (ou NULL para compatibilidade)
- Exclui atividades do tipo 'conteudo'
- Exclui atividades que já possuem resposta do aluno

### 5. Exibição de Pendências por Aluno (notas_faltas.php)

**Nova coluna "Pendente" adicionada nas tabelas de:**
- Admin (visualização por turma)
- Professor (todas as turmas do professor)

**Conteúdo da coluna:**
- Número de atividades pendentes por aluno
- Formato: "X atividade(s)"
- Usa a mesma query de contagem do dashboard do aluno

## Como Testar

### 1. Preparação
1. Execute a migração: `migration_add_tipo_atividade.php`
2. Faça login como professor

### 2. Criar Atividades de Teste
1. Acesse "Enviar/Ver Atividades"
2. Crie uma atividade do tipo "Conteúdo"
3. Crie uma atividade do tipo "Atividade"
4. Verifique se as badges de tipo aparecem corretamente na lista

### 3. Testar Visualização do Aluno
1. Faça login como aluno
2. Verifique o dashboard:
   - O botão "Atividades" deve mostrar um badge com o número de pendentes
   - Deve contar apenas a atividade do tipo "Atividade"
3. Entre em "Atividades":
   - Conteúdo deve ter badge azul "CONTEÚDO"
   - Atividade sem resposta deve ter badge vermelha "PENDENTE"
   - Botão "Enviar Resposta" deve aparecer apenas para atividades pendentes
4. Envie uma resposta para a atividade
5. Volte à lista:
   - Badge deve mudar para verde "RESPONDIDA"
   - Contador no dashboard deve diminuir

### 4. Testar Visualização Professor/Admin
1. Faça login como professor ou admin
2. Acesse "Notas e Faltas"
3. Selecione uma turma (se admin)
4. Verifique a coluna "Pendente":
   - Deve mostrar "1 atividade" para alunos que não responderam
   - Deve mostrar "0 atividades" para alunos que responderam
   - Não deve contar conteúdos

## Compatibilidade com Dados Existentes

- Atividades criadas antes da migração são tratadas como tipo 'atividade' por padrão
- A query usa `(a.tipo='atividade' OR a.tipo IS NULL)` para compatibilidade retroativa
- Após a migração, todas as atividades terão valor 'atividade' devido ao DEFAULT

## Arquivos Modificados

1. `migration_add_tipo_atividade.php` - NOVO
2. `atividades.php` - Modificado
3. `dashboard_aluno.php` - Modificado
4. `notas_faltas.php` - Modificado

## Queries SQL Importantes

### Contagem de Pendentes (usada em múltiplos lugares)
```sql
SELECT COUNT(*) as total FROM atividades a 
WHERE a.turma_id=? 
AND (a.tipo='atividade' OR a.tipo IS NULL)
AND NOT EXISTS (
    SELECT 1 FROM respostas r 
    WHERE r.atividade_id=a.id AND r.aluno_id=?
)
```

### Verificar se aluno já enviou resposta
```sql
SELECT id FROM respostas 
WHERE atividade_id=? AND aluno_id=?
```

## Estilo Visual

### Cores das Badges
- **CONTEÚDO**: #3498db (azul)
- **ATIVIDADE** (professor): #9b59b6 (roxo)
- **PENDENTE**: #e74c3c (vermelho)
- **RESPONDIDA**: #27ae60 (verde)
- **Contador**: #e74c3c (vermelho) com border-radius: 50%

## Notas Técnicas

- Todas as queries consideram `tipo IS NULL` para compatibilidade retroativa
- O campo tipo é obrigatório (NOT NULL) mas tem valor padrão 'atividade'
- Atividades do tipo 'conteudo' nunca mostram botão de enviar resposta
- O contador usa NOT EXISTS para melhor performance em vez de LEFT JOIN
