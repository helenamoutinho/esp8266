<?php
// process_mock_data.php - No servidor REMOTO
header('Content-Type: text/plain; charset=utf-8');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sensor_project";

// Criar conexão
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

echo "✅ Conectado à base de dados remota\n";
echo "==========================================\n";

if ($_POST['action'] == 'insert_complete_mock_data') {
    $data = json_decode($_POST['data'], true);
    $num_readings = intval($_POST['num_readings']);
    $days_back = intval($_POST['days_back']);
    
    $conn->begin_transaction();
    
    try {
        // 1. INSERIR MÓDULOS
        echo "\n📦 INSERINDO MÓDULOS:\n";
        echo "-------------------\n";
        foreach ($data['modulos'] as $modulo) {
            $sql = "INSERT INTO Modulos (id_modulo, tipo, localizacao, data_instalacao) 
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE localizacao=?, data_instalacao=?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssss", $modulo[0], $modulo[1], $modulo[2], $modulo[3], $modulo[2], $modulo[3]);
            
            if ($stmt->execute()) {
                echo "✓ Módulo {$modulo[0]} - {$modulo[1]} - {$modulo[2]}\n";
            }
            $stmt->close();
        }
        
        // 2. INSERIR SENSORES
        echo "\n🔌 INSERINDO SENSORES:\n";
        echo "--------------------\n";
        foreach ($data['sensores'] as $sensor) {
            $sql = "INSERT INTO Sensores (id_sensor, id_modulo, tipo_sensor, endereco, pino, protocolo) 
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE tipo_sensor=?, endereco=?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssss", $sensor[0], $sensor[1], $sensor[2], $sensor[3], $sensor[4], $sensor[5], $sensor[2], $sensor[3]);
            
            if ($stmt->execute()) {
                echo "✓ Sensor {$sensor[0]} - {$sensor[2]} - Módulo {$sensor[1]}\n";
            }
            $stmt->close();
        }
        
        // 3. GERAR LEITURAS REALISTAS
        echo "\n📊 GERANDO LEITURAS:\n";
        echo "------------------\n";
        $start_timestamp = time() - ($days_back * 24 * 60 * 60);
        $temperature_sensors = ['SENS001', 'SENS002', 'SENS004', 'SENS005', 'SENS009', 'SENS011', 'SENS012'];
        $voltage_sensors = ['SENS003', 'SENS006', 'SENS008', 'SENS010'];
        
        $readings_count = 0;
        $events_count = 0;
        
        for ($i = 0; $i < $num_readings; $i++) {
            // Timestamp progressivo (não totalmente aleatório)
            $timestamp = $start_timestamp + (($i * ($days_back * 24 * 60 * 60)) / $num_readings) + rand(-3600, 3600);
            
            // Escolher sensor baseado no tipo
            if (rand(0, 1) == 0 && !empty($temperature_sensors)) {
                // Leitura de temperatura
                $sensor_id = $temperature_sensors[array_rand($temperature_sensors)];
                $voltagem = rand(360, 410) / 100; // 3.60V a 4.10V
                $temp1 = rand(1850, 2650) / 100;  // 18.5°C a 26.5°C
                $temp2 = rand(1900, 2700) / 100;  // 19.0°C a 27.0°C
                
                $sql = "INSERT INTO Leituras (id_sensor, timestamp_epoch, voltagem, sensor1_temp, sensor2_temp) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("siddd", $sensor_id, $timestamp, $voltagem, $temp1, $temp2);
            } else {
                // Leitura de voltagem apenas
                $sensor_id = $voltage_sensors[array_rand($voltage_sensors)];
                $voltagem = rand(355, 415) / 100; // 3.55V a 4.15V
                
                $sql = "INSERT INTO Leituras (id_sensor, timestamp_epoch, voltagem) 
                        VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sid", $sensor_id, $timestamp, $voltagem);
            }
            
            if ($stmt->execute()) {
                $last_id = $conn->insert_id;
                $readings_count++;
                
                // 4. GERAR EVENTOS ASSOCIADOS (20% das leituras)
                if (rand(1, 5) == 1) {
                    $event_types = [
                        'WiFi_Conectado', 'WiFi_Falhou', 'XBee_Transmissao', 'XBee_Recepcao', 
                        'Sleep', 'Wake', 'Erro_SD', 'Erro_Sensor', 'Bateria_Baixa'
                    ];
                    $event_type = $event_types[array_rand($event_types)];
                    
                    // Descrições específicas por tipo de evento
                    $descriptions = [
                        'WiFi_Conectado' => 'Conexão WiFi estabelecida com sucesso',
                        'WiFi_Falhou' => 'Falha na conexão WiFi',
                        'XBee_Transmissao' => 'Dados transmitidos via XBee',
                        'XBee_Recepcao' => 'Dados recebidos via XBee',
                        'Sleep' => 'Módulo entrou em modo de baixo consumo',
                        'Wake' => 'Módulo acordou do modo sleep',
                        'Erro_SD' => 'Erro na escrita no cartão SD',
                        'Erro_Sensor' => 'Falha na leitura do sensor',
                        'Bateria_Baixa' => 'Voltagem da bateria abaixo do limite recomendado'
                    ];
                    
                    $valor_associado = $voltagem;
                    if ($event_type == 'Bateria_Baixa') {
                        $valor_associado = rand(320, 350) / 100; // Valores baixos para bateria fraca
                    }
                    
                    $event_sql = "INSERT INTO Eventos (timestamp_evento, id_leitura, tipo_evento, descricao, valor_associado)
                                 VALUES (FROM_UNIXTIME(?), ?, ?, ?, ?)";
                    
                    $event_stmt = $conn->prepare($event_sql);
                    $event_stmt->bind_param("iissd", $timestamp, $last_id, $event_type, $descriptions[$event_type], $valor_associado);
                    
                    if ($event_stmt->execute()) {
                        $events_count++;
                    }
                    $event_stmt->close();
                }
                
                if ($readings_count % 50 == 0) {
                    echo "✓ {$readings_count} leituras processadas...\n";
                }
            }
            $stmt->close();
        }
        
        // 5. EVENTOS ADICIONAIS (sem leitura associada)
        echo "\n⚠️  EVENTOS ADICIONAIS:\n";
        echo "--------------------\n";
        
        $additional_events = [
            ['Bateria_Baixa', 'Bateria principal com voltagem crítica', 3.25],
            ['WiFi_Falhou', 'Falha temporária na rede WiFi', NULL],
            ['Erro_SD', 'Cartão SD não detectado', NULL],
            ['Wake', 'Sistema reiniciado após atualização', NULL]
        ];
        
        foreach ($additional_events as $event) {
            $event_timestamp = time() - rand(1, 24 * 60 * 60);
            
            $sql = "INSERT INTO Eventos (timestamp_evento, tipo_evento, descricao, valor_associado)
                    VALUES (FROM_UNIXTIME(?), ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issd", $event_timestamp, $event[0], $event[1], $event[2]);
            
            if ($stmt->execute()) {
                echo "✓ Evento {$event[0]} - {$event[1]}\n";
            }
            $stmt->close();
        }
        
        $conn->commit();
        
        // RELATÓRIO FINAL
        echo "\n==========================================\n";
        echo "🎉 DADOS MOCK INSERIDOS COM SUCESSO!\n";
        echo "==========================================\n";
        echo "📦 Módulos: " . count($data['modulos']) . "\n";
        echo "🔌 Sensores: " . count($data['sensores']) . "\n";
        echo "📊 Leituras: " . $readings_count . "\n";
        echo "⚠️  Eventos: " . $events_count . " + " . count($additional_events) . " adicionais\n";
        echo "📅 Período: Últimos " . $days_back . " dias\n";
        echo "==========================================\n";
        
    } catch (Exception $e) {
        $conn->rollback();
        echo "❌ Erro na transação: " . $e->getMessage() . "\n";
    }
}

$conn->close();
?>