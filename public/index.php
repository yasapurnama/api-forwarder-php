<?php
/**
 * Created by Pu12
 * Name: Forward Request to Real App
 * Date: 2024/10/01
 */

require __DIR__.'/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
$dotenv->load();

// Function to build the real app endpoint
function buildRealAppEndpoint($clientDomain) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $port = $_SERVER['SERVER_PORT'] !== '80' && $_SERVER['SERVER_PORT'] !== '443' ? ':' . $_SERVER['SERVER_PORT'] : '';

    // Use REQUEST_URI for the path, but remove any query string
    $uri = strtok($_SERVER['REQUEST_URI'], '?');

    return "{$protocol}://{$clientDomain}{$port}{$uri}";
}

// Specify the domain for the real app
$clientDomain = 'api.yourdomain.com';

// Build the real app endpoint
$realAppEndpoint = buildRealAppEndpoint($clientDomain);

// Headers to add or modify
$customHeaders = [
    'Host' => $clientDomain,
    'X-Custom-Header' => 'CustomValue',
    'Authorization' => 'Bearer ' . $_ENV['API_TOKEN']
];

// Get all headers from the incoming request
$incomingHeaders = getallheaders();

// Merge custom headers with incoming headers (custom headers will overwrite if there's a conflict)
$headers = array_merge($incomingHeaders, $customHeaders);

// Initialize cURL session
$ch = curl_init($realAppEndpoint);

// Prepare headers for cURL
$curlHeaders = [];
foreach ($headers as $key => $value) {
    $curlHeaders[] = "$key: $value";
}

// Set cURL options
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_HTTPHEADER => $curlHeaders,
    CURLOPT_CUSTOMREQUEST => $_SERVER['REQUEST_METHOD'],
    CURLOPT_POSTFIELDS => file_get_contents('php://input'),
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
]);

// If there's a query string, add it to the cURL request
if (!empty($_SERVER['QUERY_STRING'])) {
    curl_setopt($ch, CURLOPT_URL, $realAppEndpoint . '?' . $_SERVER['QUERY_STRING']);
}

// Execute the request
$response = curl_exec($ch);

// Check for cURL errors
if (curl_errno($ch)) {
    http_response_code(500);
    echo 'Curl error: ' . curl_error($ch);
    exit;
}

// Get response headers
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headerStr = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

// Close cURL session
curl_close($ch);

// Forward the response headers
$responseHeaders = explode("\r\n", $headerStr);
foreach ($responseHeaders as $header) {
    if (!empty($header)) {
        header($header);
    }
}

// Output the response body
echo $body;