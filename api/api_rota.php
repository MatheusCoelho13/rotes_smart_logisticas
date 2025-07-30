<?php
header('Content-Type: application/json');

$apiKey = 'eyJvcmciOiI1YjNjZTM1OTc4NTExMTAwMDFjZjYyNDgiLCJpZCI6ImRmMDQ0NTg1YTI3OTRjMTBhYjUxMGM5ZTQyMzY5NjIxIiwiaCI6Im11cm11cjY0In0=';
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['enderecos']) || !is_array($data['enderecos'])) {
    http_response_code(400);
    echo json_encode(["erro" => "Informe os enderecos como array em 'enderecos'"]);
    exit;
}

function geocodificar($endereco, $apiKey) {
    $url = "https://api.openrouteservice.org/geocode/search?api_key=" . urlencode($apiKey) . "&text=" . urlencode($endereco);
    $resposta = @file_get_contents($url);
    if (!$resposta) {
        return null;
    }
    $json = json_decode($resposta, true);
    return $json['features'][0]['geometry']['coordinates'] ?? null;
}

$jobs = [];
$id = 1;
foreach ($data['enderecos'] as $endereco) {
    $coord = geocodificar($endereco, $apiKey);
    if ($coord) {
        $jobs[] = ["id" => $id++, "location" => $coord];
    }
}

if (count($jobs) < 2) {
    http_response_code(422);
    echo json_encode(["erro" => "Insira ao menos dois enderecos validos."]);
    exit;
}

$body = [
    "jobs" => $jobs,
    "vehicles" => [["id" => 1]]
];

$ch = curl_init('https://api.openrouteservice.org/optimization');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: $apiKey",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
$resposta = curl_exec($ch);
curl_close($ch);

$rota = json_decode($resposta, true);
$steps = $rota['routes'][0]['steps'] ?? [];

$paradas_ordenadas = [];
foreach ($steps as $step) {
    $job_id = $step['job'];
    foreach ($jobs as $job) {
        if ($job['id'] === $job_id) {
            $paradas_ordenadas[] = $job['location'];
            break;
        }
    }
}

$map_url = 'https://www.google.com/maps/dir/';
foreach ($paradas_ordenadas as $p) {
    $map_url .= $p[1] . ',' . $p[0] . '/';
}

$rota['google_maps_url'] = $map_url;
$rota['paradas'] = $paradas_ordenadas;
$rota['distancia_total_km'] = round(($rota['routes'][0]['distance'] ?? 0) / 1000, 2);
$rota['duracao_total_min'] = round(($rota['routes'][0]['duration'] ?? 0) / 60);

echo json_encode($rota);
