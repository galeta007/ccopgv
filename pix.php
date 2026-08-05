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

// ==== CREDENCIAIS IMPULSE PAY (via Config Vars do Heroku) ====
$publicKey = getenv('IMPULSE_PUBLIC_KEY');
$privateKey = getenv('IMPULSE_PRIVATE_KEY');
$postbackUrl = getenv('IMPULSE_POSTBACK_URL');
$auth = base64_encode($publicKey . ":" . $privateKey);

$url = "https://api.impulse-pay.com/v1/transactions";

// ==== MONTA O PAYLOAD A PARTIR DO QUE O SEU CHECKOUT JÁ ENVIA ====
$items = [];
foreach (($data['items'] ?? []) as $item) {
    $items[] = [
        "title" => $item['title'] ?? 'Produto',
        "unit_price" => (int) ($item['unitPrice'] ?? $item['unit_price'] ?? 0),
        "quantity" => (int) ($item['quantity'] ?? 1),
        "tangible" => $item['tangible'] ?? false
    ];
}

$payload = [
    "amount" => (int) ($data['amount'] ?? 0),
    "payment_method" => "PIX",
    "items" => $items,
    "customer" => [
        "name" => $data['customer']['name'] ?? '',
        "email" => $data['customer']['email'] ?? '',
        "phone" => $data['customer']['phone'] ?? '',
        "document" => [
            "number" => $data['customer']['document']['number'] ?? '',
            "type" => strtoupper($data['customer']['document']['type'] ?? 'CPF')
        ]
    ]
];

if (!empty($postbackUrl)) {
    $payload['postback_url'] = $postbackUrl;
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Basic " . $auth
]);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$responseData = json_decode($response, true);

$output = [
    "success" => $httpcode >= 200 && $httpcode < 300,
    "transaction_id" => $responseData['id'] ?? null,
    "status" => $responseData['status'] ?? null,
    "pix_copy_paste" => $responseData['pix']['copy_paste'] ?? null,
    "expires_at" => $responseData['pix']['expires_at'] ?? null,
    "raw" => $responseData
];

http_response_code($httpcode);
echo json_encode($output);
