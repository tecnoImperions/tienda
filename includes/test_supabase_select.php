<?php
require 'config_supabase.php';

$url = SUPABASE_URL . '/rest/v1/categorias?select=*';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, supabaseHeaders());
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);

if ($response === false) {
    die('Error CURL: ' . curl_error($ch));
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h3>HTTP CODE: $httpCode</h3>";
echo "<pre>";
echo $response;
echo "</pre>";
