
CREATE TABLE Modulos (
    id_modulo VARCHAR(50) PRIMARY KEY,
    tipo ENUM('Recetor', 'Temperaturas', 'ESP8266') NOT NULL,
    localizacao VARCHAR(200),
    data_instalacao DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Sensores (
    id_sensor VARCHAR(50) PRIMARY KEY,
    id_modulo VARCHAR(50) NOT NULL,
    tipo_sensor VARCHAR(100) NOT NULL,
    endereco VARCHAR(100),
    pino VARCHAR(50),
    protocolo VARCHAR(50),

    CONSTRAINT fk_sensores_modulo
        FOREIGN KEY (id_modulo)
        REFERENCES Modulos(id_modulo)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Leituras (
    id_leitura BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_sensor VARCHAR(50) NOT NULL,
    timestamp_epoch BIGINT UNSIGNED NOT NULL,

    voltagem DECIMAL(5,3),
    sensor1_temp DECIMAL(5,2),
    sensor2_temp DECIMAL(5,2),

    timestamp_gravacao DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_sensor_timestamp (id_sensor, timestamp_epoch),
    INDEX idx_timestamp_epoch (timestamp_epoch),

    CONSTRAINT fk_leituras_sensor
        FOREIGN KEY (id_sensor)
        REFERENCES Sensores(id_sensor)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Eventos (
    id_evento INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    timestamp_evento DATETIME NOT NULL,
    id_leitura BIGINT UNSIGNED,

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

    INDEX idx_leitura_timestamp (id_leitura, timestamp_evento),
    INDEX idx_tipo_evento (tipo_evento),

    CONSTRAINT fk_evento_leitura
        FOREIGN KEY (id_leitura)
        REFERENCES Leituras(id_leitura)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
