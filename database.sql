-- Tabela de Módulos
CREATE TABLE Modulos (
    id_modulo VARCHAR(20) PRIMARY KEY,
    tipo ENUM('Recetor', 'Temperaturas', 'ESP8266') NOT NULL,
    localizacao VARCHAR(100),
    data_instalacao DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Sensores (catálogo de todos os sensores)
CREATE TABLE Sensores (
    id_sensor VARCHAR(20) PRIMARY KEY,
    id_modulo VARCHAR(20) NOT NULL,
    tipo_sensor VARCHAR(50) NOT NULL,
    endereco VARCHAR(50),
    pino VARCHAR(50),
    protocolo VARCHAR(50),
    FOREIGN KEY (id_modulo) REFERENCES Modulos(id_modulo)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- Tabela Principal de Leituras (dados agregados por ciclo)
CREATE TABLE Leituras (
    id_leitura BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_modulo VARCHAR(20) NOT NULL,
    timestamp_epoch BIGINT NOT NULL,
    voltagem DECIMAL(5,3),
    sensor1_temp DECIMAL(5,2),
    sensor2_temp DECIMAL(5,2),
    caminho_ficheiro_sd VARCHAR(255),
    timestamp_gravacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_modulo_timestamp (id_modulo, timestamp_epoch),
    INDEX idx_timestamp_epoch (timestamp_epoch),
    FOREIGN KEY (id_modulo) REFERENCES Modulos(id_modulo)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- Tabela de Ficheiros SD
CREATE TABLE Ficheiros_SD (
    id_ficheiro INT AUTO_INCREMENT PRIMARY KEY,
    id_modulo VARCHAR(20) NOT NULL,
    caminho_ficheiro VARCHAR(255) NOT NULL,
    timestamp_criacao DATETIME,
    tamanho_bytes INT,
    processado BOOLEAN DEFAULT FALSE,
    num_leituras INT DEFAULT 0 COMMENT,
    conteudo_raw TEXT,
    INDEX idx_modulo_timestamp (id_modulo, timestamp_criacao),
    FOREIGN KEY (id_modulo) REFERENCES Modulos(id_modulo)
);

-- Tabela de Eventos do Sistema
CREATE TABLE Eventos (
    id_evento INT AUTO_INCREMENT PRIMARY KEY,
    timestamp_evento DATETIME NOT NULL,
    id_modulo VARCHAR(20),
    tipo_evento ENUM(
        'Bateria_Baixa', 
        'WiFi_Conectado', 
        'WiFi_Falhou',
        'XBee_Transmissao', 
        'XBee_Recepcao', 
        'Sleep', 
        'Wake', 
        'Erro_SD',
        'Erro_Sensor'
    ) NOT NULL,
    descricao TEXT,
    valor_associado DECIMAL(10,4),
    INDEX idx_modulo_timestamp (id_modulo, timestamp_evento),
    INDEX idx_tipo_evento (tipo_evento),
    FOREIGN KEY (id_modulo) REFERENCES Modulos(id_modulo)
);

-- Tabela de Configuração WiFi
CREATE TABLE Configuracao_WiFi (
    id_config INT AUTO_INCREMENT PRIMARY KEY,
    ssid VARCHAR(50) NOT NULL,
    password VARCHAR(50) NOT NULL,
    ativo BOOLEAN DEFAULT TRUE,
    ultima_conexao DATETIME,
    tentativas_falhas INT DEFAULT 0
);

-- Tabela de Comunicações entre Módulos
CREATE TABLE Comunicacoes (
    id_comunicacao INT AUTO_INCREMENT PRIMARY KEY,
    id_modulo_origem VARCHAR(20) NOT NULL,
    id_modulo_destino VARCHAR(20) NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    dados_enviados TEXT,
    formato VARCHAR(50),
    sucesso BOOLEAN DEFAULT TRUE,
    tempo_transmissao_ms INT,
    INDEX idx_origem_destino (id_modulo_origem, id_modulo_destino),
    INDEX idx_timestamp (timestamp),
    FOREIGN KEY (id_modulo_origem) REFERENCES Modulos(id_modulo),
    FOREIGN KEY (id_modulo_destino) REFERENCES Modulos(id_modulo)
);

/*
DELIMITER //

CREATE PROCEDURE sp_inserir_leitura(
    IN p_id_modulo VARCHAR(20),
    IN p_timestamp_epoch BIGINT,
    IN p_voltagem DECIMAL(5,3),
    IN p_sensor1_temp DECIMAL(5,2),
    IN p_sensor2_temp DECIMAL(5,2),
    IN p_caminho_ficheiro VARCHAR(255)
)
BEGIN
    -- Inserir apenas na tabela principal Leituras
    INSERT INTO Leituras (id_modulo, timestamp_epoch, voltagem, sensor1_temp, sensor2_temp, caminho_ficheiro_sd)
    VALUES (p_id_modulo, p_timestamp_epoch, p_voltagem, p_sensor1_temp, p_sensor2_temp, p_caminho_ficheiro);
    
    -- Verificar bateria
    CALL sp_verificar_bateria(p_id_modulo, p_voltagem);
END //

DELIMITER ;

-- =========================================================
-- TRIGGERS
-- =========================================================

DELIMITER //

-- Trigger: Atualizar contador de leituras em ficheiros SD
CREATE TRIGGER trg_atualizar_contador_ficheiro
AFTER INSERT ON Leituras
FOR EACH ROW
BEGIN
    UPDATE Ficheiros_SD 
    SET num_leituras = num_leituras + 1
    WHERE caminho_ficheiro = NEW.caminho_ficheiro_sd;
END //

DELIMITER ;
*/
