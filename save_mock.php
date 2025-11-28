<?php
// save_data_new.php - Para receber dados do ESP8266 e gravar na nova estrutura

header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sensor_project";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["success" => false, "error" => "Connection failed: " . $conn->connect_error]));
}

// Receber dados via POST
$id_modulo = $_POST['id_modulo'] ?? null;
$timestamp_epoch = $_POST['timestamp_epoch'] ?? null;
$voltagem = $_POST['voltagem'] ?? null;
$sensor1_temp = $_POST['sensor1_temp'] ?? null;
$sensor2_temp = $_POST['sensor2_temp'] ?? null;

// Validar dados obrigatórios
if (!$id_modulo || !$timestamp_epoch) {
    die(json_encode(["success" => false, "error" => "Missing required fields"]));
}

// 1. Verificar/Inserir módulo se não existir
$stmt = $conn->prepare("INSERT IGNORE INTO Modulos (id_modulo, tipo, localizacao) VALUES (?, 'Temperaturas', 'Laboratorio')");
$stmt->bind_param("s", $id_modulo);
$stmt->execute();
$stmt->close();

// 2. Obter id_sensor principal do módulo (ou criar se não existir)
$id_sensor = $id_modulo . "_main";
$stmt = $conn->prepare("INSERT IGNORE INTO Sensores (id_sensor, id_modulo, tipo_sensor, protocolo) VALUES (?, ?, 'DS18B20', 'OneWire')");
$stmt->bind_param("ss", $id_sensor, $id_modulo);
$stmt->execute();
$stmt->close();

// 3. Inserir leitura na tabela Leituras
$stmt = $conn->prepare("INSERT INTO Leituras (id_sensor, timestamp_epoch, voltagem, sensor1_temp, sensor2_temp) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("siddd", $id_sensor, $timestamp_epoch, $voltagem, $sensor1_temp, $sensor2_temp);

if ($stmt->execute()) {
    $id_leitura = $stmt->insert_id;
    
    // 4. Registrar eventos se necessário
    // Verificar bateria baixa
    if ($voltagem && $voltagem < 3.5) {
        $evento_stmt = $conn->prepare("INSERT INTO Eventos (timestamp_evento, id_leitura, tipo_evento, descricao, valor_associado) VALUES (FROM_UNIXTIME(?), ?, 'Bateria_Baixa', 'Voltagem abaixo do limite crítico', ?)");
        $evento_stmt->bind_param("iid", $timestamp_epoch, $id_leitura, $voltagem);
        $evento_stmt->execute();
        $evento_stmt->close();
    }
    
    // Registrar transmissão XBee bem-sucedida
    $evento_stmt = $conn->prepare("INSERT INTO Eventos (timestamp_evento, id_leitura, tipo_evento, descricao) VALUES (FROM_UNIXTIME(?), ?, 'XBee_Transmissao', 'Dados recebidos com sucesso')");
    $evento_stmt->bind_param("ii", $timestamp_epoch, $id_leitura);
    $evento_stmt->execute();
    $evento_stmt->close();
    
    echo json_encode([
        "success" => true, 
        "message" => "Data inserted successfully",
        "id_leitura" => $id_leitura
    ]);
} else {
    echo json_encode([
        "success" => false, 
        "error" => "Error: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>