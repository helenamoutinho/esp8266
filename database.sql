-- Tabela de Módulos
CREATE TABLE Modulos (
    id_modulo VARCHAR(20) PRIMARY KEY,
    tipo ENUM('Recetor', 'Temperaturas', 'ESP8266') NOT NULL,
    localizacao VARCHAR(100),
    data_instalacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    observacoes TEXT
);

-- Tabela de Sensores (catálogo de todos os sensores)
CREATE TABLE Sensores (
    id_sensor VARCHAR(20) PRIMARY KEY,
    id_modulo VARCHAR(20) NOT NULL,
    tipo_sensor VARCHAR(50) NOT NULL,
    endereco VARCHAR(50),
    variavel_medida VARCHAR(100) NOT NULL,
    unidade VARCHAR(20),
    pino VARCHAR(50),
    protocolo VARCHAR(50),
    observacoes TEXT,
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

-- Tabela de Leituras Detalhadas (uma linha por sensor)
CREATE TABLE Leituras_Detalhadas (
    id_leitura_det BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_sensor VARCHAR(20) NOT NULL,
    timestamp_epoch BIGINT NOT NULL,
    valor DECIMAL(12,6),
    valor_bruto INT,
    unidade VARCHAR(20),
    caminho_ficheiro_sd VARCHAR(255),
    timestamp_gravacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sensor_timestamp (id_sensor, timestamp_epoch),
    INDEX idx_timestamp_epoch (timestamp_epoch),
    FOREIGN KEY (id_sensor) REFERENCES Sensores(id_sensor)
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


-- =========================================================
-- STORED PROCEDURES
-- =========================================================

DELIMITER //

-- Procedure: Registrar evento automático de bateria baixa
CREATE PROCEDURE sp_verificar_bateria(
    IN p_id_modulo VARCHAR(20),
    IN p_voltagem DECIMAL(5,3)
)
BEGIN
    IF p_voltagem < 3.5 THEN
        INSERT INTO Eventos (timestamp_evento, id_modulo, tipo_evento, descricao, valor_associado)
        VALUES (NOW(), p_id_modulo, 'Bateria_Baixa', 
                CONCAT('Voltagem crítica: ', p_voltagem, 'V'), 
                p_voltagem);
    END IF;
END //

-- Procedure: Inserir leitura completa
CREATE PROCEDURE sp_inserir_leitura(
    IN p_id_modulo VARCHAR(20),
    IN p_timestamp_epoch BIGINT,
    IN p_voltagem DECIMAL(5,3),
    IN p_sensor1_temp DECIMAL(5,2),
    IN p_sensor2_temp DECIMAL(5,2),
    IN p_caminho_ficheiro VARCHAR(255)
)
BEGIN
    -- Inserir na tabela principal
    INSERT INTO Leituras (id_modulo, timestamp_epoch, voltagem, sensor1_temp, sensor2_temp, caminho_ficheiro_sd)
    VALUES (p_id_modulo, p_timestamp_epoch, p_voltagem, p_sensor1_temp, p_sensor2_temp, p_caminho_ficheiro);
    
    -- Inserir nas leituras detalhadas
    INSERT INTO Leituras_Detalhadas (id_sensor, timestamp_epoch, valor, unidade, caminho_ficheiro_sd)
    VALUES 
        ('SENS_VBAT_01', p_timestamp_epoch, p_voltagem, 'V', p_caminho_ficheiro),
        ('SENS_DS18B20_1', p_timestamp_epoch, p_sensor1_temp, '°C', p_caminho_ficheiro),
        ('SENS_DS18B20_2', p_timestamp_epoch, p_sensor2_temp, '°C', p_caminho_ficheiro);
    
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