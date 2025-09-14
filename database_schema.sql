-- Banco de Dados: escola
-- Sistema Escolar SiSchool - Schema Completo

-- Tabelas Existentes (baseadas no código atual)

-- Administradores
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `usuario` varchar(100) NOT NULL UNIQUE,
  `senha` varchar(255) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);

-- Professores
CREATE TABLE IF NOT EXISTS `professores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `usuario` varchar(100) NOT NULL UNIQUE,
  `senha` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);

-- Turmas
CREATE TABLE IF NOT EXISTS `turmas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `turno` enum('Manhã','Tarde','Noite') NOT NULL,
  `professor_id` int(11) NOT NULL,
  `finalizada` tinyint(1) DEFAULT 0,
  `ano_letivo` int(4) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`professor_id`) REFERENCES `professores`(`id`)
);

-- Alunos
CREATE TABLE IF NOT EXISTS `alunos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `matricula` varchar(50) NOT NULL UNIQUE,
  `nome` varchar(255) NOT NULL,
  `usuario` varchar(100) NOT NULL UNIQUE,
  `senha` varchar(255) NOT NULL,
  `turma_id` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `endereco` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`turma_id`) REFERENCES `turmas`(`id`)
);

-- Atividades
CREATE TABLE IF NOT EXISTS `atividades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) NOT NULL,
  `descricao` text NOT NULL,
  `turma_id` int(11) NOT NULL,
  `professor_id` int(11) NOT NULL,
  `arquivo` varchar(255) DEFAULT NULL,
  `data_envio` timestamp DEFAULT CURRENT_TIMESTAMP,
  `data_entrega` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`turma_id`) REFERENCES `turmas`(`id`),
  FOREIGN KEY (`professor_id`) REFERENCES `professores`(`id`)
);

-- Respostas dos Alunos
CREATE TABLE IF NOT EXISTS `respostas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `atividade_id` int(11) NOT NULL,
  `aluno_id` int(11) NOT NULL,
  `arquivo` varchar(255) NOT NULL,
  `data_envio` timestamp DEFAULT CURRENT_TIMESTAMP,
  `nota` decimal(4,2) DEFAULT NULL,
  `comentario` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`atividade_id`) REFERENCES `atividades`(`id`),
  FOREIGN KEY (`aluno_id`) REFERENCES `alunos`(`id`)
);

-- Notas e Faltas
CREATE TABLE IF NOT EXISTS `notas_faltas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aluno_id` int(11) NOT NULL,
  `nota1` decimal(4,2) DEFAULT NULL,
  `nota2` decimal(4,2) DEFAULT NULL,
  `media` decimal(4,2) DEFAULT NULL,
  `faltas` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_aluno` (`aluno_id`),
  FOREIGN KEY (`aluno_id`) REFERENCES `alunos`(`id`)
);

-- NOVAS TABELAS PARA MÓDULOS AVANÇADOS

-- Responsáveis
CREATE TABLE IF NOT EXISTS `responsaveis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `usuario` varchar(100) NOT NULL UNIQUE,
  `senha` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telefone` varchar(20) NOT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `endereco` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);

-- Relacionamento Responsável-Aluno
CREATE TABLE IF NOT EXISTS `aluno_responsavel` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aluno_id` int(11) NOT NULL,
  `responsavel_id` int(11) NOT NULL,
  `parentesco` varchar(50) NOT NULL,
  `principal` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`aluno_id`) REFERENCES `alunos`(`id`),
  FOREIGN KEY (`responsavel_id`) REFERENCES `responsaveis`(`id`)
);

-- Tipos de Avaliação
CREATE TABLE IF NOT EXISTS `tipos_avaliacao` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `peso` decimal(3,2) DEFAULT 1.00,
  `ativo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
);

-- Avaliações Detalhadas
CREATE TABLE IF NOT EXISTS `avaliacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aluno_id` int(11) NOT NULL,
  `tipo_avaliacao_id` int(11) NOT NULL,
  `professor_id` int(11) NOT NULL,
  `nota` decimal(4,2) NOT NULL,
  `data_avaliacao` date NOT NULL,
  `observacoes` text DEFAULT NULL,
  `bimestre` int(1) NOT NULL,
  `ano_letivo` int(4) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`aluno_id`) REFERENCES `alunos`(`id`),
  FOREIGN KEY (`tipo_avaliacao_id`) REFERENCES `tipos_avaliacao`(`id`),
  FOREIGN KEY (`professor_id`) REFERENCES `professores`(`id`)
);

-- Frequência/Presença
CREATE TABLE IF NOT EXISTS `frequencia` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aluno_id` int(11) NOT NULL,
  `turma_id` int(11) NOT NULL,
  `data` date NOT NULL,
  `presente` tinyint(1) NOT NULL DEFAULT 1,
  `observacao` varchar(255) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_aluno_data` (`aluno_id`, `data`),
  FOREIGN KEY (`aluno_id`) REFERENCES `alunos`(`id`),
  FOREIGN KEY (`turma_id`) REFERENCES `turmas`(`id`)
);

-- Mensagens/Comunicação Interna
CREATE TABLE IF NOT EXISTS `mensagens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `remetente_id` int(11) NOT NULL,
  `remetente_tipo` enum('admin','professor','aluno','responsavel') NOT NULL,
  `destinatario_id` int(11) NOT NULL,
  `destinatario_tipo` enum('admin','professor','aluno','responsavel') NOT NULL,
  `assunto` varchar(255) NOT NULL,
  `mensagem` text NOT NULL,
  `lida` tinyint(1) DEFAULT 0,
  `data_leitura` timestamp NULL DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);

-- Notificações
CREATE TABLE IF NOT EXISTS `notificacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `usuario_tipo` enum('admin','professor','aluno','responsavel') NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `mensagem` text NOT NULL,
  `tipo` enum('info','warning','success','danger') DEFAULT 'info',
  `lida` tinyint(1) DEFAULT 0,
  `data_leitura` timestamp NULL DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);

-- Calendário de Eventos
CREATE TABLE IF NOT EXISTS `eventos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `data_inicio` datetime NOT NULL,
  `data_fim` datetime DEFAULT NULL,
  `tipo` enum('prova','reuniao','evento','feriado','aula') NOT NULL,
  `turma_id` int(11) DEFAULT NULL,
  `professor_id` int(11) DEFAULT NULL,
  `publico` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`turma_id`) REFERENCES `turmas`(`id`),
  FOREIGN KEY (`professor_id`) REFERENCES `professores`(`id`)
);

-- Financeiro - Planos de Pagamento
CREATE TABLE IF NOT EXISTS `planos_pagamento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `valor_mensalidade` decimal(10,2) NOT NULL,
  `valor_matricula` decimal(10,2) DEFAULT 0.00,
  `ativo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
);

-- Financeiro - Contas do Aluno
CREATE TABLE IF NOT EXISTS `contas_aluno` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aluno_id` int(11) NOT NULL,
  `plano_id` int(11) NOT NULL,
  `data_inicio` date NOT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`aluno_id`) REFERENCES `alunos`(`id`),
  FOREIGN KEY (`plano_id`) REFERENCES `planos_pagamento`(`id`)
);

-- Financeiro - Parcelas/Mensalidades
CREATE TABLE IF NOT EXISTS `mensalidades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conta_id` int(11) NOT NULL,
  `mes_referencia` int(2) NOT NULL,
  `ano_referencia` int(4) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_vencimento` date NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `valor_pago` decimal(10,2) DEFAULT NULL,
  `status` enum('pendente','pago','vencido') DEFAULT 'pendente',
  `observacoes` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`conta_id`) REFERENCES `contas_aluno`(`id`)
);

-- Documentos Digitais
CREATE TABLE IF NOT EXISTS `documentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aluno_id` int(11) NOT NULL,
  `tipo` varchar(100) NOT NULL,
  `nome_arquivo` varchar(255) NOT NULL,
  `arquivo_original` varchar(255) NOT NULL,
  `tamanho` int(11) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `versao` int(11) DEFAULT 1,
  `ativo` tinyint(1) DEFAULT 1,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_by_tipo` enum('admin','professor','aluno','responsavel') NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`aluno_id`) REFERENCES `alunos`(`id`)
);

-- Inserir dados básicos
INSERT INTO `tipos_avaliacao` (`nome`, `peso`) VALUES 
('Prova', 1.00),
('Trabalho', 0.70),
('Participação', 0.50),
('Projeto', 1.00) ON DUPLICATE KEY UPDATE nome=nome;

INSERT INTO `planos_pagamento` (`nome`, `valor_mensalidade`, `valor_matricula`) VALUES 
('Plano Básico', 350.00, 100.00),
('Plano Integral', 550.00, 150.00) ON DUPLICATE KEY UPDATE nome=nome;