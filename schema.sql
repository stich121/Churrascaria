-- Schema da Área do Funcionário - Churrascaria Pampulha
-- Importe este arquivo na aba "SQL" (ou "Importar") do phpMyAdmin, dentro do banco u654041352_Reserva.

CREATE TABLE IF NOT EXISTS funcionarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    nivel TINYINT UNSIGNED NOT NULL,
    ativo TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_funcionarios_nivel CHECK (nivel IN (1, 2, 3))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reservas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome_cliente VARCHAR(100) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    data_reserva DATE NOT NULL,
    hora_reserva TIME NOT NULL,
    pessoas SMALLINT UNSIGNED NOT NULL,
    funcionario_id INT UNSIGNED NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reservas_funcionario FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_reservas_data ON reservas (data_reserva, hora_reserva);

-- Primeiro administrador (nível 3). Hash gerado localmente com password_hash() do PHP
-- para a senha informada — rode este INSERT uma vez, na aba SQL do phpMyAdmin.
INSERT INTO funcionarios (nome, usuario, senha_hash, nivel) VALUES
('Matheus Dias', 'Matheus.dias', '$2y$10$MAK7weG1g1u4OEp8Ir46C.9Bzho2nX1p2YRGHN6fpl/6H7aLGHaFi', 3);
