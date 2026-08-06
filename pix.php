<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON payload"]);
    exit;
}

// ==== CREDENCIAIS ZUCKPAY (via Config Vars do Heroku) ====
$clientId = getenv('ZUCKPAY_CLIENT_ID');
$clientSecret = getenv('ZUCKPAY_CLIENT_SECRET');
$urlnoty = getenv('ZUCKPAY_WEBHOOK_URL'); // opcional

// IMPORTANTE: sempre com www. -- sem isso dá erro 405
$url = "https://www.zuckpay.com.br/conta/v3/pix/qrcode";

// ==== MONTA O PAYLOAD A PARTIR DO QUE O SEU CHECKOUT JÁ ENVIA ====
// (mesma origem de dados que já usava na Impulse Pay: $data['customer'], $data['amount'], etc.)
$valorEmReais = round(((float) ($data['amount'] ?? 0)) / 100, 2); // se amount vier em centavos
// Se o front já manda o valor em reais (não centavos), troque a linha acima por:
// $valorEmReais = (float) ($data['amount'] ?? 0);

$payload = [
    "valor" => $valorEmReais,
    "nome" => $data['customer']['name'] ?? '',
    "cpf" => preg_replace('/\D/', '', $data['customer']['document']['number'] ?? ''),
    "email" => $data['customer']['email'] ?? '',
    "telefone" => preg_replace('/\D/', '', $data['customer']['phone'] ?? ''),
];

if (!empty($urlnoty)) {
    $payload['urlnoty'] = $urlnoty;
}
if (!empty($data['items'][0]['title'])) {
    $payload['descricao'] = $data['items'][0]['title'];
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, $clientId . ":" . $clientSecret); // Basic Auth
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$responseData = json_decode($response, true);

// Tenta achar o QR code / copia-e-cola em vários nomes de campo possíveis
$copiaCola = $responseData['pix_qr_code']
    ?? $responseData['qrcode']
    ?? $responseData['qr_code']
    ?? $responseData['copia_e_cola']
    ?? $responseData['pix_copia_cola']
    ?? $responseData['code']
    ?? $responseData['brcode']
    ?? $responseData['emv']
    ?? null;

$transactionId = $responseData['id']
    ?? $responseData['transaction_id']
    ?? $responseData['transactionId']
    ?? null;

$output = [
    "success" => $httpcode >= 200 && $httpcode < 300,
    "transaction_id" => $transactionId,
    "status" => $responseData['status'] ?? null,
    "pix_copy_paste" => $copiaCola,
    "raw" => $responseData
];

http_response_code($httpcode);
echo json_encode($output);
