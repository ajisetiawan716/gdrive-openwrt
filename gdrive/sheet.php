<?php
// File: /www/download_google_sheets.php

$apiKey = 'AIzaSyBTPsOk2mOrD_5re3n4OmLFFRvflGvqgMY'; // Ganti dengan API Key Anda

// Menerima parameter dari permintaan HTTP
$spreadsheetId = $_GET['Id']; // ID Google Sheets
$worksheetName = $_GET['sheet']; // Nama lembar kerja
$range = $_GET['range']; // Rentang sel

if (!$spreadsheetId || !$worksheetName || !$range) {
    echo "Parameter tidak lengkap.";
    exit;
}

$url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$worksheetName}!{$range}?key={$apiKey}";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

// Proses data atau simpan ke file, sesuai kebutuhan Anda
echo $response;
?>