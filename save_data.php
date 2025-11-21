<?php
$servername = "localhost";
$username = "root"; // default XAMPP username
$password = "";     // default XAMPP password
$dbname = "sensor_data"; //  database name

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sensor1 = $_POST['sensor1']; // temperature
$sensor2 = $_POST['sensor2']; // sensor value

$sql = "INSERT INTO sensor_data (temperature, sensor_value) VALUES ('$sensor1', '$sensor2')";

if ($conn->query($sql) === TRUE) {
    echo "Data inserted successfully"; 
} else {
    echo "Error: " . $sql . "<br>" . $conn->error;
}

$conn->close();
?>