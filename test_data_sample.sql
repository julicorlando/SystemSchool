-- Script SQL para criar dados de teste
-- Execute este script após rodar migration_add_tipo_atividade.php

-- Limpar dados de teste anteriores (opcional)
-- DELETE FROM respostas WHERE atividade_id IN (SELECT id FROM atividades WHERE titulo LIKE '%TESTE%');
-- DELETE FROM atividades WHERE titulo LIKE '%TESTE%';

-- Inserir atividades de teste (assumindo que já existem professor_id=1 e turma_id=1)
-- Ajuste os IDs conforme necessário para seu banco

-- Atividade tipo 'atividade' - deve aparecer como PENDENTE
INSERT INTO atividades (titulo, descricao, tipo, turma_id, professor_id, data_envio, arquivo) 
VALUES ('TESTE - Atividade para responder', 'Esta é uma atividade que requer resposta do aluno', 'atividade', 1, 1, NOW(), 'test.pdf');

-- Atividade tipo 'conteudo' - NÃO deve aparecer como PENDENTE
INSERT INTO atividades (titulo, descricao, tipo, turma_id, professor_id, data_envio, arquivo) 
VALUES ('TESTE - Conteúdo para leitura', 'Este é apenas um conteúdo para leitura, não requer resposta', 'conteudo', 1, 1, NOW(), 'test.pdf');

-- Para testar resposta enviada:
-- Primeiro, obtenha o ID da atividade e do aluno
-- SELECT id FROM atividades WHERE titulo = 'TESTE - Atividade para responder';
-- SELECT id FROM alunos WHERE nome = 'Nome do Aluno';

-- Então insira uma resposta (substitua os IDs apropriadamente):
-- INSERT INTO respostas (atividade_id, aluno_id, arquivo, data_envio) 
-- VALUES (1, 1, 'resposta_test.pdf', NOW());

-- Verificar contagem de pendentes para um aluno específico (substitua IDs):
-- SELECT COUNT(*) as total FROM atividades a 
-- WHERE a.turma_id=1 
-- AND (a.tipo='atividade' OR a.tipo IS NULL)
-- AND NOT EXISTS (
--     SELECT 1 FROM respostas r 
--     WHERE r.atividade_id=a.id AND r.aluno_id=1
-- );

-- Verificar todas as atividades e seus tipos:
-- SELECT id, titulo, tipo, 
--        (SELECT COUNT(*) FROM respostas WHERE atividade_id=atividades.id) as respostas_count
-- FROM atividades 
-- WHERE turma_id=1;
