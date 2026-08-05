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

// ==== CREDENCIAIS IMPULSE PAY ====
$publicKey = "pk_EmJoszXY5ZsIr75iCf9buUXMghm1yVBxeXme9LEeWz0";
$privateKey = "sk_ip_riuRjqiJ9jGznhy9F281aQq_PsNbXZn-CAl80K5Y"; // pegue no painel, clicando no olho
$auth = base64_encode($publicKey . ":" . $privateKey);

$url = "https://api.impulse-pay.com/v1/transactions";

// ==== MAPEAMENTO DO PAYLOAD (Black Cat -> Impulse Pay) ====
// Ajuste os "??" abaixo conforme os nomes reais que seu checkout envia hoje.
$valorEmCentavos = $data['amount'] ?? $data['valor'] ?? $data['value'] ?? 0;

$payload = [
    "amount" => (int) $valorEmCentavos,
    "payment_method" => "PIX",
    "postback_url" => "http://regularizeprocess.org/caminho/webhook.php", // ajuste pro seu endpoint de webhook
    "items" => [
        [
            "title" => $data['produto'] ?? $data['product'] ?? $data['title'] ?? "Produto",
            "unit_price" => (int) $valorEmCentavos,
            "quantity" => 1,
            "tangible" => false,
            "external_ref" => $data['produto_id'] ?? $data['external_ref'] ?? null
        ]
    ],
    "customer" => [
        "name" => $data['nome'] ?? $data['name'] ?? '',
        "email" => $data['email'] ?? '',
        "phone" => $data['telefone'] ?? $data['phone'] ?? '',
        "document" => [
            "number" => $data['cpf'] ?? $data['document'] ?? '',
            "type" => "CPF"
        ]
    ]
];

if (!empty($data['utm'])) {
    $payload['utm'] = $data['utm'];
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

// Padroniza a saída para o seu front-end
$output = [
    "success" => $httpcode >= 200 && $httpcode < 300,
    "transaction_id" => $responseData['id'] ?? null,
    "status" => $responseData['status'] ?? null,
    "pix_copy_paste" => $responseData['pix']['copy_paste'] ?? null,
    "expires_at" => $responseData['pix']['expires_at'] ?? null,
    "raw" => $responseData // remova se não quiser expor a resposta completa
];

http_response_code($httpcode);
echo json_encode($output);
