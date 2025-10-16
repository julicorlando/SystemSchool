# 📋 Quick Reference - Atividades Pendentes

## 🚀 Instalação Rápida

```bash
# 1. Execute a migração (UMA VEZ APENAS)
http://seu-servidor/migration_add_tipo_atividade.php

# 2. Pronto para usar!
```

## 📁 Arquivos Importantes

| Arquivo | Propósito |
|---------|-----------|
| `migration_add_tipo_atividade.php` | Script de migração (executar 1x) |
| `IMPLEMENTATION_SUMMARY.md` | Documentação completa com fluxos |
| `README_ATIVIDADES_PENDENTES.md` | Guia detalhado de implementação |
| `test_data_sample.sql` | Dados de teste SQL |
| `QUICK_REFERENCE.md` | Este arquivo - referência rápida |

## 🎯 Principais Mudanças

### 1️⃣ Campo Novo no Banco
```sql
ALTER TABLE atividades 
ADD COLUMN tipo ENUM('conteudo', 'atividade') 
NOT NULL DEFAULT 'atividade' 
AFTER descricao;
```

### 2️⃣ Formulário Professor (atividades.php)
```html
<select name="tipo_atividade" required>
    <option value="atividade">Atividade (requer resposta)</option>
    <option value="conteudo">Conteúdo (apenas leitura)</option>
</select>
```

### 3️⃣ Query de Contagem de Pendentes
```sql
SELECT COUNT(*) FROM atividades a 
WHERE a.turma_id = ?
  AND (a.tipo='atividade' OR a.tipo IS NULL)
  AND NOT EXISTS (
      SELECT 1 FROM respostas r 
      WHERE r.atividade_id=a.id AND r.aluno_id=?
  )
```

### 4️⃣ Badge no Dashboard (dashboard_aluno.php)
```php
<?php if($pendentes > 0) 
    echo "<span style='...'>$pendentes</span>"; 
?>
```

## 🎨 Badges Visuais

| Status | Cor | Quando Aparece |
|--------|-----|----------------|
| 🔵 CONTEÚDO | Azul (#3498db) | tipo='conteudo' |
| 🔴 PENDENTE | Vermelho (#e74c3c) | tipo='atividade' SEM resposta |
| 🟢 RESPONDIDA | Verde (#27ae60) | tipo='atividade' COM resposta |

## ⚡ Teste Rápido

```bash
# 1. Executar migração
# 2. Login professor → Criar 1 conteúdo + 1 atividade
# 3. Login aluno → Ver contador (deve mostrar 1)
# 4. Responder atividade → Contador vai para 0
# 5. Login professor → Ver coluna "Pendente" em Notas/Faltas
```

## 🔍 Debug Útil

### Ver atividades e tipos
```sql
SELECT id, titulo, tipo, turma_id FROM atividades;
```

### Ver pendentes de um aluno
```sql
SELECT a.titulo, a.tipo, 
       EXISTS(SELECT 1 FROM respostas r 
              WHERE r.atividade_id=a.id AND r.aluno_id=1) as tem_resposta
FROM atividades a 
WHERE a.turma_id=1;
```

### Contar pendentes manualmente
```sql
SELECT COUNT(*) FROM atividades a 
WHERE a.turma_id=1 
  AND (a.tipo='atividade' OR a.tipo IS NULL)
  AND NOT EXISTS (
      SELECT 1 FROM respostas WHERE atividade_id=a.id AND aluno_id=1
  );
```

## 🐛 Troubleshooting

### Contador não aparece
- ✅ Migração executada?
- ✅ Aluno tem turma_id válido?
- ✅ Existem atividades tipo 'atividade' sem resposta?

### Badge errada
- ✅ Campo `tipo` existe na tabela?
- ✅ Valor é 'atividade' ou 'conteudo'?
- ✅ Resposta registrada na tabela `respostas`?

### Erro no INSERT
- ✅ Migração foi executada antes de criar atividades?
- ✅ Campo `tipo` tem valor padrão?

## 📊 Impacto por Usuário

### 👨‍🎓 Aluno
- Badge no dashboard com contador de pendentes
- Status visual (PENDENTE/RESPONDIDA/CONTEÚDO)
- Botão "Enviar Resposta" apenas quando aplicável

### 👨‍🏫 Professor
- Selector de tipo ao criar atividade
- Badge de tipo na lista de atividades
- Coluna "Pendente" em Notas/Faltas

### 👨‍💼 Admin
- Coluna "Pendente" por aluno
- Visão completa de todas as turmas

## 🔐 Segurança

- ✅ Valores do enum são validados pelo MySQL
- ✅ IDs são convertidos com intval()
- ✅ Queries usam parametrização quando possível
- ⚠️ **ATENÇÃO**: Código usa concatenação direta em algumas queries (considerar prepared statements)

## 💾 Backup

```bash
# Antes da migração, faça backup:
mysqldump -u root -p escola > backup_antes_migracao.sql

# Para restaurar se necessário:
mysql -u root -p escola < backup_antes_migracao.sql
```

## 📞 Suporte

Consulte:
- `IMPLEMENTATION_SUMMARY.md` - Documentação visual completa
- `README_ATIVIDADES_PENDENTES.md` - Guia técnico detalhado
- `test_data_sample.sql` - Scripts de teste

## ✅ Checklist de Deploy

- [ ] Backup do banco de dados
- [ ] Executar `migration_add_tipo_atividade.php`
- [ ] Verificar se campo `tipo` foi criado
- [ ] Testar criação de atividade como professor
- [ ] Testar visualização como aluno
- [ ] Verificar contador no dashboard
- [ ] Verificar badges nas atividades
- [ ] Verificar coluna "Pendente" em Notas/Faltas
- [ ] Testar envio de resposta
- [ ] Confirmar atualização de contador

---

**Versão**: 1.0  
**Data**: Outubro 2025  
**Status**: ✅ Implementação Completa
