-- Banco de Questões Schema
-- Execute este script no banco de dados 'escola'

-- Tabela de matérias
CREATE TABLE IF NOT EXISTS materias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de questões
CREATE TABLE IF NOT EXISTS questoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enunciado TEXT NOT NULL,
    alternativa_a VARCHAR(500) NOT NULL,
    alternativa_b VARCHAR(500) NOT NULL,
    alternativa_c VARCHAR(500) NOT NULL,
    alternativa_d VARCHAR(500) NOT NULL,
    resposta_correta ENUM('A', 'B', 'C', 'D') NOT NULL,
    materia_id INT NOT NULL,
    professor_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE CASCADE,
    FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE CASCADE
);

-- Tabela de provas
CREATE TABLE IF NOT EXISTS provas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    descricao TEXT,
    materia_id INT NOT NULL,
    professor_id INT NOT NULL,
    total_questoes INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE CASCADE,
    FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE CASCADE
);

-- Tabela de relacionamento prova-questões
CREATE TABLE IF NOT EXISTS prova_questoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prova_id INT NOT NULL,
    questao_id INT NOT NULL,
    ordem INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prova_id) REFERENCES provas(id) ON DELETE CASCADE,
    FOREIGN KEY (questao_id) REFERENCES questoes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_prova_questao (prova_id, questao_id)
);

-- Inserir algumas matérias padrão
INSERT IGNORE INTO materias (nome, descricao) VALUES
('Matemática', 'Disciplina de Matemática'),
('Português', 'Disciplina de Língua Portuguesa'),
('História', 'Disciplina de História'),
('Geografia', 'Disciplina de Geografia'),
('Ciências', 'Disciplina de Ciências'),
('Inglês', 'Disciplina de Língua Inglesa'),
('Física', 'Disciplina de Física'),
('Química', 'Disciplina de Química'),
('Biologia', 'Disciplina de Biologia'),
('Educação Física', 'Disciplina de Educação Física');