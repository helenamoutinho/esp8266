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
        sensor1_temp,
        sensor2_temp,
        voltagem,
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
    $startDate_esc = $conn->real_escape_string($startDate);
    $endDate_esc = $conn->real_escape_string($endDate);
    $sql .= " AND DATE(FROM_UNIXTIME(timestamp_epoch)) BETWEEN '$startDate_esc' AND '$endDate_esc' ";
} elseif ($startDate) {
    $startDate_esc = $conn->real_escape_string($startDate);
    $sql .= " AND DATE(FROM_UNIXTIME(timestamp_epoch)) >= '$startDate_esc' ";
} elseif ($endDate) {
    $endDate_esc = $conn->real_escape_string($endDate);
    $sql .= " AND DATE(FROM_UNIXTIME(timestamp_epoch)) <= '$endDate_esc' ";
}

/* ============================
   FILTRO POR HORAS
   ============================ */
if ($intervalStart && $intervalEnd) {
    $intervalStart_esc = $conn->real_escape_string($intervalStart);
    $intervalEnd_esc = $conn->real_escape_string($intervalEnd);
    $sql .= "
        AND TIME(FROM_UNIXTIME(timestamp_epoch))
        BETWEEN '$intervalStart_esc:00' AND '$intervalEnd_esc:59'
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
$sensor1_temp = json_encode(array_reverse(array_column($sensor_data, "sensor1_temp")), JSON_NUMERIC_CHECK);
$sensor2_temp = json_encode(array_reverse(array_column($sensor_data, "sensor2_temp")), JSON_NUMERIC_CHECK);
$voltagem     = json_encode(array_reverse(array_column($sensor_data, "voltagem")), JSON_NUMERIC_CHECK);
$timestamp    = json_encode($timestamps, JSON_NUMERIC_CHECK);

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<script src="https://code.highcharts.com/highcharts.js"></script>
<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .filter-container { text-align: center; margin-bottom: 20px; padding: 15px; background: #f5f5f5; border-radius: 5px; }
    .filter-container label { margin-right: 5px; font-weight: bold; }
    .filter-container input, .filter-container select { margin-right: 15px; padding: 5px; }
    .filter-container button { padding: 6px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
    .filter-container button:hover { background: #0056b3; }
    .chart-container { margin: 20px 0; }
</style>
</head>

<body>
<h2 style="text-align:center;">LEITURAS DOS SENSORES</h2>

<div class="filter-container">

    <label>Sensor:</label>
    <input type="text" id="sensor" value="<?php echo htmlspecialchars($sensor ?? ''); ?>">

    <label>Start:</label>
    <input type="date" id="startDate" value="<?php echo htmlspecialchars($startDate ?? ''); ?>">

    <label>End:</label>
    <input type="date" id="endDate" value="<?php echo htmlspecialchars($endDate ?? ''); ?>">

    <label>Hour Start:</label>
    <input type="time" id="intervalStart" value="<?php echo htmlspecialchars($intervalStart ?? ''); ?>">

    <label>Hour End:</label>
    <input type="time" id="intervalEnd" value="<?php echo htmlspecialchars($intervalEnd ?? ''); ?>">

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

<div id="chart-temp1" class="chart-container"></div>
<div id="chart-temp2" class="chart-container"></div>
<div id="chart-voltagem" class="chart-container"></div>

<script>
Highcharts.chart('chart-temp1', {
    title: { text: 'Temperatura Sensor 1' },
    xAxis: { 
        categories: <?php echo $timestamp; ?>,
        title: { text: 'Timestamp' }
    },
    yAxis: {
        title: { text: 'Temperatura (°C)' }
    },
    series: [{ 
        name: 'Sensor 1',
        data: <?php echo $sensor1_temp; ?> 
    }]
});

Highcharts.chart('chart-temp2', {
    title: { text: 'Temperatura Sensor 2' },
    xAxis: { 
        categories: <?php echo $timestamp; ?>,
        title: { text: 'Timestamp' }
    },
    yAxis: {
        title: { text: 'Temperatura (°C)' }
    },
    series: [{ 
        name: 'Sensor 2',
        data: <?php echo $sensor2_temp; ?> 
    }]
});

Highcharts.chart('chart-voltagem', {
    title: { text: 'Voltagem' },
    xAxis: { 
        categories: <?php echo $timestamp; ?>,
        title: { text: 'Timestamp' }
    },
    yAxis: {
        title: { text: 'Voltagem (V)' }
    },
    series: [{ 
        name: 'Voltagem',
        data: <?php echo $voltagem; ?> 
    }]
});
</script>

</body>
</html>
<?php $conn->close(); ?>
