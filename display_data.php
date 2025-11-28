<?php
$servername = "localhost";
$dbname = "sensor_projects";
$username = "root";
$password = "";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* ============================
   PARÂMETROS DE FILTRO
   ============================ */
$startDate     = $_GET['startDate']     ?? null;
$endDate       = $_GET['endDate']       ?? null;
$intervalStart = $_GET['intervalStart'] ?? null;
$intervalEnd   = $_GET['intervalEnd']   ?? null;
$dataFrequency = $_GET['dataFrequency'] ?? null;
$sensor        = $_GET['sensor']        ?? null;

/* ============================
   QUERY BASE → TABELA LEITURAS
   ============================ */
$sql = "
    SELECT 
        id_leitura AS id,
        id_sensor,
        sensor1_temp AS temperature,
        voltagem AS sensor_value,
        FROM_UNIXTIME(timestamp_epoch) AS timestamp
    FROM Leituras
    WHERE 1=1
";

/* ============================
   FILTRO POR SENSOR
   ============================ */
if (!empty($sensor)) {
    $s = $conn->real_escape_string($sensor);
    $sql .= " AND id_sensor = '$s' ";
}

/* ============================
   FILTRO POR DATA
   ============================ */
if ($startDate && $endDate) {
    $sql .= " AND DATE(FROM_UNIXTIME(timestamp_epoch)) BETWEEN '$startDate' AND '$endDate' ";
} elseif ($startDate) {
    $sql .= " AND DATE(FROM_UNIXTIME(timestamp_epoch)) >= '$startDate' ";
} elseif ($endDate) {
    $sql .= " AND DATE(FROM_UNIXTIME(timestamp_epoch)) <= '$endDate' ";
}

/* ============================
   FILTRO POR HORAS
   ============================ */
if ($intervalStart && $intervalEnd) {
    $sql .= "
        AND TIME(FROM_UNIXTIME(timestamp_epoch))
        BETWEEN '$intervalStart:00' AND '$intervalEnd:59'
    ";
}

/* ============================
   ORDENAR POR TEMPO
   ============================ */
$sql .= " ORDER BY timestamp_epoch ASC";

/* ============================
   EXECUTAR QUERY
   ============================ */
$result = $conn->query($sql);
if (!$result) {
    die("Query failed: " . $conn->error);
}

$sensor_data = [];
while ($row = $result->fetch_assoc()) {
    $sensor_data[] = $row;
}

/* ============================
   FILTRAGEM POR FREQUÊNCIA
   ============================ */
if ($dataFrequency) {
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

    $interval = $intervalMap[$dataFrequency] ?? 0;

    if ($interval > 0) {
        $filtered_data = [];
        $last_ts = null;

        foreach ($sensor_data as $d) {
            $ts = strtotime($d["timestamp"]);
            if ($last_ts === null || ($ts - $last_ts) >= $interval) {
                $filtered_data[] = $d;
                $last_ts = $ts;
            }
        }

        $sensor_data = $filtered_data;
    }
}

/* ============================
   PREPARAR ARRAYS PARA GRÁFICOS
   ============================ */
$timestamps   = array_reverse(array_column($sensor_data, "timestamp"));
$temperature  = json_encode(array_reverse(array_column($sensor_data, "temperature")), JSON_NUMERIC_CHECK);
$sensor_value = json_encode(array_reverse(array_column($sensor_data, "sensor_value")), JSON_NUMERIC_CHECK);
$timestamp    = json_encode($timestamps, JSON_NUMERIC_CHECK);

?>
<!DOCTYPE html>
<html>
<head>
<script src="https://code.highcharts.com/highcharts.js"></script>
</head>

<body>
<h2 style="text-align:center;">LEITURAS DOS SENSORES</h2>

<div style="text-align:center;margin-bottom:20px;">

    <label>Sensor:</label>
    <input type="text" id="sensor" value="<?php echo $sensor; ?>">

    <label>Start:</label>
    <input type="date" id="startDate" value="<?php echo $startDate; ?>">

    <label>End:</label>
    <input type="date" id="endDate" value="<?php echo $endDate; ?>">

    <label>Hour Start:</label>
    <input type="time" id="intervalStart" value="<?php echo $intervalStart; ?>">

    <label>Hour End:</label>
    <input type="time" id="intervalEnd" value="<?php echo $intervalEnd; ?>">

    <label>Freq:</label>
    <select id="dataFrequency">
        <option value="">None</option>
        <?php 
            foreach (["1s","30s","1m","2m","5m","10m","30m","1h"] as $o) {
                $sel = ($dataFrequency === $o) ? "selected" : "";
                echo "<option value='$o' $sel>$o</option>";
            }
        ?>
    </select>

    <button onclick="applyFilters()">APPLY</button>
</div>

<script>
function applyFilters() {
    const p = new URLSearchParams({
        sensor:        document.getElementById("sensor").value,
        startDate:     document.getElementById("startDate").value,
        endDate:       document.getElementById("endDate").value,
        intervalStart: document.getElementById("intervalStart").value,
        intervalEnd:   document.getElementById("intervalEnd").value,
        dataFrequency: document.getElementById("dataFrequency").value
    });
    window.location = "?" + p.toString();
}
</script>

<div id="chart-temperature"></div>
<div id="chart-sensor"></div>

<script>
Highcharts.chart('chart-temperature', {
    title: { text: 'Temperature (sensor1_temp)' },
    xAxis: { categories: <?php echo $timestamp; ?> },
    series: [{ data: <?php echo $temperature; ?> }]
});

Highcharts.chart('chart-sensor', {
    title: { text: 'Voltagem' },
    xAxis: { categories: <?php echo $timestamp; ?> },
    series: [{ data: <?php echo $sensor_value; ?> }]
});
</script>

</body>
</html>
