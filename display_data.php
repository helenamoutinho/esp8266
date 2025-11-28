<?php
$servername = "localhost";
$dbname = "sensor_projects";
$username = "root";
$password = "";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$startDate = $_GET['startDate'] ?? null; 
$endDate = $_GET['endDate'] ?? null; 
$intervalStart = $_GET['intervalStart'] ?? null; 
$intervalEnd = $_GET['intervalEnd'] ?? null; 
$dataFrequency = $_GET['dataFrequency'] ?? null; 

$sql = "SELECT id, temperature, sensor_value, timestamp FROM sensor_data WHERE 1=1";

if ($startDate && $endDate) {
    $sql .= " AND DATE(timestamp) BETWEEN '$startDate' AND '$endDate'";
} elseif ($startDate) {
    $sql .= " AND DATE(timestamp) >= '$startDate'";
} elseif ($endDate) {
    $sql .= " AND DATE(timestamp) <= '$endDate'";
}


if ($intervalStart && $intervalEnd) {
    $intervalStart = "$startDate $intervalStart:00";
    $intervalEnd = "$endDate $intervalEnd:59"; 
    $sql .= " AND TIME(timestamp) BETWEEN '$intervalStart' AND '$intervalEnd'";
}

$sql .= " ORDER BY timestamp ASC"; 

$result = $conn->query($sql);

if (!$result) {
    die("Query failed: " . $conn->error);
}

$sensor_data = [];
while ($data = $result->fetch_assoc()) {
    $sensor_data[] = $data;
}

if ($dataFrequency) {
    $filtered_data = [];
    $interval = 0;

    switch ($dataFrequency) {
        case '1s':
            $interval = 1;
            break;
        case '30s':
            $interval = 30;
            break;
        case '1m':
            $interval = 60;
            break;
        case '2m':
            $interval = 120;
            break;
        case '5m':
            $interval = 300;
            break;
        case '10m':
            $interval = 600;
            break;
        case '30m':
            $interval = 1800;
            break;
        case '1h':
            $interval = 3600;
            break;
    }

    $last_timestamp = null;
    foreach ($sensor_data as $data) {
        $current_timestamp = strtotime($data['timestamp']);
        if ($last_timestamp === null || ($current_timestamp - $last_timestamp) >= $interval) {
            $filtered_data[] = $data;
            $last_timestamp = $current_timestamp;
        }
    }

    $sensor_data = $filtered_data;
}

$readings_time = array_column($sensor_data, 'timestamp');

$temperature = json_encode(array_reverse(array_column($sensor_data, 'temperature')), JSON_NUMERIC_CHECK);
$sensor_value = json_encode(array_reverse(array_column($sensor_data, 'sensor_value')), JSON_NUMERIC_CHECK);
$timestamp = json_encode(array_reverse($readings_time), JSON_NUMERIC_CHECK);

$result->free();
$conn->close();
?>

<!DOCTYPE html>
<html>
<meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://code.highcharts.com/highcharts.js"></script>
  <style>
    body {
      min-width: 310px;
      max-width: 1280px;
      height: 500px;
      margin: 0 auto;
      font-family: 'Arial', sans-serif;
      background-color: #f4f4f4;
      color: #333;
    }
    h2 {
      font-size: 2.5rem;
      text-align: center;
      color: #555;
    }
    .filters {
      margin-bottom: 20px;
      text-align: center;
    }
    .filters label {
      margin-right: 10px;
      color: #555;
    }
    .filters input, .filters select {
      padding: 5px;
      border: 1px solid #ccc;
      border-radius: 4px;
    }
    .filters button {
      padding: 5px 10px;
      background-color: #28a745;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }
    .filters button:hover {
      background-color: #218838;
    }
    .container {
      background-color: white;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
  </style>
  <body>
    <h2>ESP8266 TESTE</h2>
    <div class="filters">
      <label for="startDate">Start Date:</label>
      <input type="date" id="startDate" name="startDate" value="<?php echo $startDate ?? ''; ?>">
      <label for="endDate">End Date:</label>
      <input type="date" id="endDate" name="endDate" value="<?php echo $endDate ?? ''; ?>">
      <label for="intervalStart">Interval Start:</label>
      <input type="time" id="intervalStart" name="intervalStart" value="<?php echo $intervalStart ?? ''; ?>">
      <label for="intervalEnd">Interval End:</label>
      <input type="time" id="intervalEnd" name="intervalEnd" value="<?php echo $intervalEnd ?? ''; ?>">
      <label for="dataFrequency">Data Frequency:</label>
      <select id="dataFrequency" name="dataFrequency">
        <option value="1s" <?php echo ($dataFrequency ?? '') === '1s' ? 'selected' : ''; ?>>1 Second</option>
        <option value="30s" <?php echo ($dataFrequency ?? '') === '30s' ? 'selected' : ''; ?>>30 Seconds</option>
        <option value="1m" <?php echo ($dataFrequency ?? '') === '1m' ? 'selected' : ''; ?>>1 Minute</option>
        <option value="2m" <?php echo ($dataFrequency ?? '') === '2m' ? 'selected' : ''; ?>>2 Minutes</option>
        <option value="5m" <?php echo ($dataFrequency ?? '') === '5m' ? 'selected' : ''; ?>>5 Minutes</option>
        <option value="10m" <?php echo ($dataFrequency ?? '') === '10m' ? 'selected' : ''; ?>>10 Minutes</option>
        <option value="30m" <?php echo ($dataFrequency ?? '') === '30m' ? 'selected' : ''; ?>>30 Minutes</option>
        <option value="1h" <?php echo ($dataFrequency ?? '') === '1h' ? 'selected' : ''; ?>>1 Hour</option>
      </select>
      <button onclick="applyFilters()">Apply Filters</button>
    </div>
    <div id="chart-temperature" class="container"></div>
    <div id="chart-sensor" class="container"></div>
    <script>
      var temperature = <?php echo $temperature; ?>;
      var sensor_value = <?php echo $sensor_value; ?>;
      var timestamp = <?php echo $timestamp; ?>;

      function applyFilters() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        const intervalStart = document.getElementById('intervalStart').value;
        const intervalEnd = document.getElementById('intervalEnd').value;
        const dataFrequency = document.getElementById('dataFrequency').value;

        window.location.href = `?startDate=${startDate}&endDate=${endDate}&intervalStart=${intervalStart}&intervalEnd=${intervalEnd}&dataFrequency=${dataFrequency}`;
      }

      var chartT = new Highcharts.Chart({
        chart: { renderTo: 'chart-temperature' },
        title: { text: 'Temperature', style: { color: '#555', fontSize: '1.5rem' } },
        series: [{
          showInLegend: false,
          data: temperature,
          color: '#28a745'
        }],
        plotOptions: {
          line: { animation: false, dataLabels: { enabled: true } }
        },
        xAxis: { 
          type: 'datetime',
          categories: timestamp,
          labels: { style: { color: '#555' } }
        },
        yAxis: {
          title: { text: 'Temperature (Celsius)', style: { color: '#555' } },
          labels: { style: { color: '#555' } }
        },
        credits: { enabled: false }
      });

      var chartS = new Highcharts.Chart({
        chart: { renderTo: 'chart-sensor' },
        title: { text: 'Sensor Value', style: { color: '#555', fontSize: '1.5rem' } },
        series: [{
          showInLegend: false,
          data: sensor_value,
          color: '#007bff'
        }],
        plotOptions: {
          line: { animation: false, dataLabels: { enabled: true } }
        },
        xAxis: {
          type: 'datetime',
          categories: timestamp,
          labels: { style: { color: '#555' } }
        },
        yAxis: {
          title: { text: 'Sensor Value', style: { color: '#555' } },
          labels: { style: { color: '#555' } }
        },
        credits: { enabled: false }
      });
    </script>
  </body>
</html>
