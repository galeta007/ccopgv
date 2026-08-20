<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

if (!isset($_GET['cpf'])) {
    http_response_code(400);
    echo json_encode(["error" => "CPF is required"]);
    exit;
}

$cpf = $_GET['cpf'];
$user = "3524c1a3cbda96fafcba7e88ff85de01";
$url = "https://api-apela.online/?user={$user}&cpf={$cpf}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpcode);
echo $response;
?>
