<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

if (!isset($_GET['cpf'])) {
    http_response_code(400);
    echo json_encode(["error" => "CPF is required"]);
    exit;
}

$cpf = preg_replace('/[^0-9]/', '', $_GET['cpf']);

$url = "https://api.amnesiatecnologia.lat/?token=SEU_TOKEN&cpf=" . urlencode($cpf);

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode([
        "error" => "Erro ao consultar API",
        "details" => curl_error($ch)
    ]);
    curl_close($ch);
    exit;
}

$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpcode ?: 200);
echo $response;
?>
