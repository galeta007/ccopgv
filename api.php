<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

if (!isset($_GET['cpf'])) {
    http_response_code(400);
    echo json_encode(["error" => "CPF is required"]);
    exit;
}

$cpf = preg_replace('/[^0-9]/', '', $_GET['cpf']);

// Validação básica
if (strlen($cpf) !== 11) {
    http_response_code(400);
    echo json_encode(["error" => "CPF inválido"]);
    exit;
}

$token = "707db4e1-0923-4dfd-ac5e-simpl3s";

$url = "https://api.amnesiatecnologia.lat/?token=" . urlencode($token) . "&cpf=" . urlencode($cpf);

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false) {
    $error = curl_error($ch);
    curl_close($ch);

    http_response_code(502);
    echo json_encode([
        "error" => "Erro ao consultar API externa",
        "details" => $error
    ]);
    exit;
}

curl_close($ch);

http_response_code($httpcode ?: 200);
echo $response;
?>
