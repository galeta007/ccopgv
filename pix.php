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

// ==== CREDENCIAIS PAGFLEX (chave de PAGAMENTO, fixa no código) ====
$apiKey = "9cDu5KQCJHzI5ZJPIo7Sc79djPEm8ut6-7mI-p_rbE0";

$url = "https://api.pagflexbr.com/v1/payment";

// ==== MONTA O PAYLOAD A PARTIR DO QUE O SEU CHECKOUT JÁ ENVIA ====
$items = [];
foreach (($data['items'] ?? []) as $item) {
    $items[] = [
        "quantity" => (int) ($item['quantity'] ?? 1),
        "name" => $item['title'] ?? 'Produto',
        "price" => (int) ($item['unitPrice'] ?? $item['unit_price'] ?? 0),
        "type" => "DIGITAL"
    ];
}

$payload = [
    "amount" => (int) ($data['amount'] ?? 0),
    "currency" => "BRL",
    "method" => "PIX",
    "description" => $data['items'][0]['title'] ?? 'Pagamento',
    "externalRef" => $data['externalRef'] ?? uniqid('order_'),
    "payer" => [
        "name" => $data['customer']['name'] ?? '',
        "taxId" => preg_replace('/\D/', '', $data['customer']['document']['number'] ?? ''),
        "email" => $data['customer']['email'] ?? '',
        "phone" => preg_replace('/\D/', '', $data['customer']['phone'] ?? '')
    ],
    "items" => $items
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $apiKey
]);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$responseData = json_decode($response, true);

// O código Pix vem dentro do campo "data" da resposta da PagFlex
$pixData = $responseData['data'] ?? $responseData['pix'] ?? $responseData;

$output = [
    "success" => $httpcode >= 200 && $httpcode < 300,
    "transaction_id" => $responseData['id'] ?? null,
    "status" => $responseData['status'] ?? null,
    "pix_copy_paste" => $pixData['copypaste'] ?? $responseData['copypaste'] ?? null,
    "e2e" => $pixData['e2e'] ?? $responseData['e2e'] ?? null,
    "raw" => $responseData
];

http_response_code($httpcode);
echo json_encode($output);
