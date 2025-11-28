<?php
// display_data_new.php - Visualizar dados da nova estrutura
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sensor_project";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Parâmetros de filtro
$id_sensor = $_GET['id_sensor'] ?? null;
$startDate = $_GET['startDate'] ?? null;
$endDate = $_GET['endDate'] ?? null;
$limit = $_GET['limit'] ?? 100;

// Query base
$sql = "SELECT 
    l.id_leitura,
    l.id_sensor,
    l.timestamp_epoch,
    FROM_UNIXTIME(l.timestamp_epoch) as timestamp_readable,
    l.voltagem,
    l.sensor1_temp,
    l.sensor2_temp,
    l.timestamp_gravacao,
    s.tipo_sensor,
    m.localizacao
FROM Leituras l
JOIN Sensores s ON l.id_sensor = s.id_sensor
JOIN Modulos m ON s.id_modulo = m.id_modulo
WHERE 1=1";

// Aplicar filtros
if ($id_sensor) {
    $sql .= " AND l.id_sensor = '$id_sensor'";
}

if ($startDate && $endDate) {
    $startEpoch = strtotime($startDate . " 00:00:00");
    $endEpoch = strtotime($endDate . " 23:59:59");
    $sql .= " AND l.timestamp_epoch BETWEEN $startEpoch AND $endEpoch";
}

$sql .= " ORDER BY l.timestamp_epoch DESC LIMIT $limit";

$result = $conn->query($sql);

if (!$result) {
    die("Query failed: " . $conn->error);
}

$leituras = [];
while ($row = $result->fetch_assoc()) {
    $leituras[] = $row;
}

// Preparar dados para gráficos (inverter ordem para timeline correta)
$leituras_reversed = array_reverse($leituras);
$timestamps = json_encode(array_column($leituras_reversed, 'timestamp_readable'));
$voltagens = json_encode(array_column($leituras_reversed, 'voltagem'), JSON_NUMERIC_CHECK);
$temp1 = json_encode(array_column($leituras_reversed, 'sensor1_temp'), JSON_NUMERIC_CHECK);
$temp2 = json_encode(array_column($leituras_reversed, 'sensor2_temp'), JSON_NUMERIC_CHECK);

// Obter lista de sensores para dropdown
$sensors_query = "SELECT DISTINCT s.id_sensor, m.localizacao FROM Sensores s JOIN Modulos m ON s.id_modulo = m.id_modulo";
$sensors_result = $conn->query($sensors_query);

$result->free();
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sensor Data Visualization</title>
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .filters {
            background: #f8f9fa;
            padding: 25px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .filter-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-item {
            display: flex;
            flex-direction: column;
        }
        
        .filter-item label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #495057;
            font-size: 0.9rem;
        }
        
        .filter-item input,
        .filter-item select {
            padding: 10px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .filter-item input:focus,
        .filter-item select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-apply {
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-apply:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 25px;
            background: #f8f9fa;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .charts {
            padding: 25px;
        }
        
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .data-table {
            padding: 25px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
        }
        
        tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        tbody tr:hover {
            background: #e9ecef;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🌡️ Sensor Data Dashboard</h1>
            <p>Sistema de Monitorização de Temperatura</p>
        </div>
        
        <div class="filters">
            <div class="filter-group">
                <div class="filter-item">
                    <label>Sensor:</label>
                    <select id="id_sensor">
                        <option value="">Todos os sensores</option>
                        <?php while ($sensor = $sensors_result->fetch_assoc()): ?>
                            <option value="<?= $sensor['id_sensor'] ?>" <?= ($id_sensor === $sensor['id_sensor']) ? 'selected' : '' ?>>
                                <?= $sensor['id_sensor'] ?> (<?= $sensor['localizacao'] ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-item">
                    <label>Data Início:</label>
                    <input type="date" id="startDate" value="<?= $startDate ?>">
                </div>
                
                <div class="filter-item">
                    <label>Data Fim:</label>
                    <input type="date" id="endDate" value="<?= $endDate ?>">
                </div>
                
                <div class="filter-item">
                    <label>Limite de registos:</label>
                    <select id="limit">
                        <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                        <option value="500" <?= $limit == 500 ? 'selected' : '' ?>>500</option>
                        <option value="1000" <?= $limit == 1000 ? 'selected' : '' ?>>1000</option>
                    </select>
                </div>
            </div>
            <button class="btn-apply" onclick="applyFilters()">Aplicar Filtros</button>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <h3>Total de Leituras</h3>
                <div class="value"><?= count($leituras) ?></div>
            </div>
            <div class="stat-card">
                <h3>Temp Média Sensor 1</h3>
                <div class="value"><?= count($leituras) > 0 ? number_format(array_sum(array_column($leituras, 'sensor1_temp')) / count($leituras), 1) : '0' ?>°C</div>
            </div>
            <div class="stat-card">
                <h3>Temp Média Sensor 2</h3>
                <div class="value"><?= count($leituras) > 0 ? number_format(array_sum(array_column($leituras, 'sensor2_temp')) / count($leituras), 1) : '0' ?>°C</div>
            </div>
            <div class="stat-card">
                <h3>Voltagem Média</h3>
                <div class="value"><?= count($leituras) > 0 ? number_format(array_sum(array_column($leituras, 'voltagem')) / count($leituras), 2) : '0' ?>V</div>
            </div>
        </div>
        
        <div class="charts">
            <div class="chart-container">
                <div id="chart-temperature"></div>
            </div>
            
            <div class="chart-container">
                <div id="chart-voltage"></div>
            </div>
        </div>
        
        <div class="data-table">
            <h2 style="margin-bottom: 20px;">Últimas Leituras</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Sensor</th>
                        <th>Data/Hora</th>
                        <th>Temp 1 (°C)</th>
                        <th>Temp 2 (°C)</th>
                        <th>Voltagem (V)</th>
                        <th>Localização</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leituras as $row): ?>
                    <tr>
                        <td><?= $row['id_leitura'] ?></td>
                        <td><?= $row['id_sensor'] ?></td>
                        <td><?= $row['timestamp_readable'] ?></td>
                        <td><?= number_format($row['sensor1_temp'], 2) ?></td>
                        <td><?= number_format($row['sensor2_temp'], 2) ?></td>
                        <td><?= number_format($row['voltagem'], 3) ?></td>
                        <td><?= $row['localizacao'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        const timestamps = <?= $timestamps ?>;
        const temp1 = <?= $temp1 ?>;
        const temp2 = <?= $temp2 ?>;
        const voltagens = <?= $voltagens ?>;
        
        function applyFilters() {
            const sensor = document.getElementById('id_sensor').value;
            const start = document.getElementById('startDate').value;
            const end = document.getElementById('endDate').value;
            const limit = document.getElementById('limit').value;
            
            window.location.href = `?id_sensor=${sensor}&startDate=${start}&endDate=${end}&limit=${limit}`;
        }
        
        // Gráfico de Temperatura
        Highcharts.chart('chart-temperature', {
            chart: { type: 'line' },
            title: { text: 'Temperaturas ao Longo do Tempo' },
            xAxis: {
                categories: timestamps,
                title: { text: 'Data/Hora' }
            },
            yAxis: {
                title: { text: 'Temperatura (°C)' }
            },
            series: [{
                name: 'Sensor 1',
                data: temp1,
                color: '#ff6b6b'
            }, {
                name: 'Sensor 2',
                data: temp2,
                color: '#4ecdc4'
            }],
            credits: { enabled: false }
        });
        
        // Gráfico de Voltagem
        Highcharts.chart('chart-voltage', {
            chart: { type: 'area' },
            title: { text: 'Voltagem da Bateria' },
            xAxis: {
                categories: timestamps,
                title: { text: 'Data/Hora' }
            },
            yAxis: {
                title: { text: 'Voltagem (V)' },
                plotLines: [{
                    value: 3.5,
                    color: 'red',
                    dashStyle: 'dash',
                    width: 2,
                    label: {
                        text: 'Limite Crítico (3.5V)'
                    }
                }]
            },
            series: [{
                name: 'Voltagem',
                data: voltagens,
                color: '#667eea'
            }],
            credits: { enabled: false }
        });
    </script>
</body>
</html>