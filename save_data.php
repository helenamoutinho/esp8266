<?php
$servername = "localhost";
$username = "root";       // ou o user MySQL do servidor
$password = "";           // password MySQL
$dbname = "sensor_project";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// Dados enviados via URL (GET)
$id_sensor        = $_GET['id_sensor'];
$timestamp_epoch  = $_GET['timestamp'];
$voltagem         = $_GET['voltagem'];
$temp1            = $_GET['temp1'];
$temp2            = $_GET['temp2'];

// Inserção
$sql = "INSERT INTO Leituras (id_sensor, timestamp_epoch, voltagem, sensor1_temp, sensor2_temp)
        VALUES ('$id_sensor', '$timestamp_epoch', '$voltagem', '$temp1', '$temp2')";

if ($conn->query($sql) === TRUE) {
    echo "OK";
} else {
    echo "Erro: " . $conn->error;
}

$conn->close();
?>
