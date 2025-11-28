<?php
$servername = "localhost";
$dbname = "sensor_projects";
$username = "root";
$password = "";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* ------------------------
   RECEBER PARÂMETROS GET
   ------------------------ */
$startDate     = $_GET['startDate']     ?? null;
$endDate       = $_GET['endDate']       ?? null;
$intervalStart = $_GET['intervalStart'] ?? null;
$intervalEnd   = $_GET['intervalEnd']   ?? null;
$dataFrequency = $_GET['dataFrequency'] ?? null;
$sensor        = $_GET['sensor']        ?? null;

/* ----------------------------------------
   BASE QUERY (agora usando a tabela correta)
   ---------------------------------------- */
$sql = "
    SELECT 
        id_leitura AS id,
        sensor1_temp AS temperature,
        voltagem AS sensor_value,
        FROM_UNIXTIME(timestamp_epoch) AS timestamp,
        id_sensor
    FROM Leituras
    WHERE 1=1
";

/* ----------------------------------------
   FILTRO POR SENSOR (opcional)
   ---------------------------------------- */
if ($sensor) {
    $sql .= " AND id_sensor = '" . $conn->real_escape_string($sensor) . "' ";
}

/* ----------------------------------------
   FILTRO POR INTERVALO DE DATAS
   ---------------------------------------- */
if ($startDate && $endDate) {
    $sql .= " AND DATE(FROM_UNIXTIME(timestamp_epoch)) BETWEEN '$startDate' AND '$endDate' ";
} elseif ($startDate) {
    $sql .= " AND DATE(FROM_UNIXTIME(timestamp_epoch)) >= '$startDate' ";
} elseif ($endDate) {
    $sql .= " AND DATE(FROM_UNIXTIME(timestamp_epoch)) <= '$endDate' ";
}

/* ----------------------------------------
   FILTRO POR INTERVALO DE HORAS
   ---------------------------------------- */
if ($intervalStart && $intervalEnd && $startDate && $endDate) {
    $intervalStartFull = "$startDate $intervalStart:00";
    $intervalEndFull   = "$endDate $intervalEnd:59";

    $sql .= "
        AND TIME(FROM_UNIXTIME(timestamp_epoch)) BETWEEN 
        TIME('$intervalStartFull') AND TIME('$intervalEndFull')
    ";
}

/* ----------------------------------------
   ORDENAR POR TEMPO
   ---------------------------------------- */
$sql .= " ORDER BY timestamp_epoch ASC";

/* ----------------------------------------
   EXECUTAR QUERY
   ---------------------------------------- */
$result = $conn->query($sql);
if (!$result) {
    die("Query failed: " . $conn->error);
}

$sensor_data = [];
while ($row = $result->fetch_assoc()) {
    $sensor_data[] = $row;
}

/* ----------------------------------------
   FILTRAGEM PELA FREQUÊNCIA TEMPORAL
   ---------------------------------------- */
if ($dataFrequency) {
    $filtered_data = [];
    $interval = 0;

    $intervalMap = [
        "1s"  => 1,
        "30s" => 30,
        "1m"  => 60,
        "2m"  => 120,
        "5m"  => 300,
        "10m" => 600,
        "30m" => 1800,
        "1h"  => 3600
    ];

    if (isset($intervalMap[$dataFrequency])) {
        $interval = $intervalMap[$dataFrequency];
    }

    $last_timestamp = null;

    foreach ($sensor_data as $row) {
        $current_ts = strtotime($row["timestamp"]);

        if ($last_timestamp === null || ($current_ts - $last_timestamp) >= $interval) {
            $filtered_data[] = $row;
            $last_timestamp = $current_ts;
        }
    }

    $sensor_data = $filtered_data;
}

/* ----------------------------------------
   PREPARAR ARRAYS PARA GRÁFICOS
   ---------------------------------------- */
$timestamps   = array_column($sensor_data, "timestamp");
$temperature  = json_encode(array_reverse(array_column($sensor_data, "temperature")), JSON_NUMERIC_CHECK);
$sensor_value = json_encode(array_reverse(array_column($sensor_data, "sensor_value")), JSON_NUMERIC_CHECK);
$timestamp    = json_encode(array_reverse($timestamps), JSON_NUMERIC_CHECK);

$result->free();
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://code.highcharts.com/highcharts.js"></script>
<style>
body {
    min-width: 310px;
    max-width: 1280px;
    margin: 0 auto;
    background-color: #f4f4f4;
    font-family: Arial, sans-serif;
    color: #333;
}
.container {
    background: white;
    margin-bottom: 20px;
    padding: 20px;
    border-radius: 8px;
}
.filters {
    text-align: center;
    margin-bottom: 20px;
}
.filters input, .filters select {
    padding: 5px;
    border: 1px solid #ccc;
    border-radius: 4px;
}
</style>
</head>

<body>
<h2 style="text-align:center;">LEITURAS DOS SENSORES</h2>

<div class="filters">
    <label>Sensor:</label>
    <input type="text" id="sensor" value="<?php echo $sensor ?? ''; ?>">

    <label>Start Date:</label>
    <input type="date" id="startDate" value="<?php echo $startDate ?? ''; ?>">

    <label>End Date:</label>
    <input type="date" id="endDate" value="<?php echo $endDate ?? ''; ?>">

    <label>Interval Start:</label>
    <input type="time" id="intervalStart" value="<?php echo $intervalStart ?? ''; ?>">

    <label>Interval End:</label>
    <input type="time" id="intervalEnd" value="<?php echo $intervalEnd ?? ''; ?>">

    <label>Data Frequency:</label>
    <select id="dataFrequency">
        <option value="">None</option>
        <?php 
        $opts = ['1s','30s','1m','2m','5m','10m','30m','1h'];
        foreach ($opts as $o) {
            $sel = ($dataFrequency === $o) ? "selected" : "";
            echo "<option value='$o' $sel>$o</option>";
        }
        ?>
    </select>

    <button onclick="applyFilters()">Apply</button>
</div>

<script>
function applyFilters() {
    const s = new URLSearchParams({
        sensor: document.getElementById('sensor').value,
        startDate: document.getElementById('startDate').value,
        endDate: document.getElementById('endDate').value,
        intervalStart: document.getElementById('intervalStart').value,
        intervalEnd: document.getElementById('intervalEnd').value,
        dataFrequency: document.getElementById('dataFrequency').value
    }).toString();

    window.location.href = "?" + s;
}
</script>

<div id="chart-temperature" class="container"></div>
<div id="chart-sensor" class="container"></div>

<script>
var temperature = <?php echo $temperature; ?>;
var sensor_value = <?php echo $sensor_value; ?>;
var timestamp = <?php echo $timestamp; ?>;

Highcharts.chart('chart-temperature', {
    title: { text: 'Sensor Temperature' },
    xAxis: { categories: timestamp },
    yAxis: { title: { text: '°C' } },
    series: [{ data: temperature }]
});

Highcharts.chart('chart-sensor', {
    title: { text: 'Sensor Value (Voltagem)' },
    xAxis: { categories: timestamp },
    yAxis: { title: { text: 'Voltagem (V)' } },
    series: [{ data: sensor_value }]
});
</script>

</body>
</html>
