<?php

// Mengambil parameter URL Google Drive dari permintaan POST
$googleDriveUrl = isset($_POST['google_drive_url']) ? $_POST['google_drive_url'] : '';

// Mengatur URL Aria2 RPC
$aria2RpcUrl = 'http://localhost:6800/jsonrpc';

// Mengatur username dan password Aria2 RPC
$aria2Username = 'your_username';
$aria2Password = 'your_password';

// Fungsi untuk mengirim permintaan ke Aria2 RPC
function sendAria2RpcRequest($method, $params = array()) {
    global $aria2RpcUrl, $aria2Username, $aria2Password;

    $request = array(
        'jsonrpc' => '2.0',
        'id' => '1',
        'method' => $method,
        'params' => $params
    );

    $options = array(
        'http' => array(
            'header' => "Content-Type: application/json\r\nAuthorization: Basic " . base64_encode($aria2Username . ':' . $aria2Password) . "\r\n",
            'method' => 'POST',
            'content' => json_encode($request)
        )
    );

    $context = stream_context_create($options);
    $response = file_get_contents($aria2RpcUrl, false, $context);

    return json_decode($response, true);
}

// Fungsi untuk menambahkan tautan unduhan ke Aria2 RPC
function addDownloadToAria2($url, $fileName) {
    $params = array(
        array($url),
        array('out' => $fileName)
    );

    return sendAria2RpcRequest('aria2.addUri', $params);
}

// Fungsi untuk mendapatkan status unduhan dari Aria2 RPC
function getDownloadStatus($gid) {
    $params = array($gid);

    return sendAria2RpcRequest('aria2.tellStatus', $params);
}

// Fungsi untuk mendapatkan file ID dari URL Google Drive
function getFileIdFromUrl($url) {
    $parts = parse_url($url);

    if (isset($parts['query'])) {
        parse_str($parts['query'], $query);

        if (isset($query['id'])) {
            return $query['id'];
        }
    }

    $path = explode('/', $parts['path']);
    return $path[3];
}

// Fungsi untuk mendapatkan nama file dari URL dengan fields=name
function getNameFromUrl($fileNameUrl) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fileNameUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Mendapatkan status HTTP
    curl_close($ch);

    if ($httpStatus === 200) {
        $json = json_decode($response, true);
        if (isset($json['name'])) {
            return $json['name'];
        }
    }

    // Jika status header bukan 200, atau nama file tidak ditemukan
    return null;
}

// Fungsi untuk mengubah nama file
function changeFileName($downloadUrl, $newFileName) {
    $modifiedUrl = preg_replace('/\/[^\/]+\?alt=media/', '/' . urlencode($newFileName) . '?alt=media', $downloadUrl);
    return $modifiedUrl;
}

// Function to check if the URL is a valid Google Drive URL
function isValidGoogleDriveUrl($url) {
    // Check for the standard format
    if (preg_match('/^(https?:\/\/)?(www\.)?(drive\.google\.com\/(file\/d\/|uc\?id=)|docs\.google\.com\/uc\?id=)([a-zA-Z0-9_-]+)/', $url)) {
        return true;
    }
    
    // Check for the format with /u/0/
    if (preg_match('/^(https?:\/\/)drive\.google\.com\/u\/[0-9]+\/uc\?id=([a-zA-Z0-9_-]+)/', $url)) {
        return true;
    }
    
    // Check for the format with export=download
    if (preg_match('/^(https?:\/\/)drive\.google\.com\/uc\?export=download&id=([a-zA-Z0-9_-]+)/', $url)) {
        return true;
    }

    // Check for the format with /file/d/
    if (preg_match('/^(https?:\/\/)drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $url)) {
        return true;
    }

    // Check for the format with /u/0/uc
    if (preg_match('/^(https?:\/\/)drive\.google\.com\/u\/[0-9]+\/uc\?id=([a-zA-Z0-9_-]+)&export=download/', $url)) {
        return true;
    }

    // Check for the format with /uc
    if (preg_match('/^(https?:\/\/)drive\.google\.com\/uc\?id=([a-zA-Z0-9_-]+)&export=download/', $url)) {
        return true;
    }
	
	// Check for the Google Docs format
    if (preg_match('/^(https?:\/\/)?docs\.google\.com\/document\/d\/([a-zA-Z0-9_-]+)\/edit/', $url)) {
        return true;
    }
	
	// Check for Google Sheets format
    if (preg_match('/^(https?:\/\/)?docs\.google\.com\/spreadsheets\/d\/([a-zA-Z0-9_-]+)\/edit/', $url)) {
        return true;
    }

    return false;
}


// Memulai unduhan jika URL Google Drive tersedia
if (!empty($googleDriveUrl)) {
    // Check if the entered URL is a valid Google Drive URL
    if (!isValidGoogleDriveUrl($googleDriveUrl)) {
        echo 'Error: Invalid Google Drive URL.';
        exit; // Stop further execution
    }

    // Mendapatkan file ID dari URL Google Drive
    $fileId = getFileIdFromUrl($googleDriveUrl);

    // Mengganti YOUR_API_KEY dengan kunci API Google Drive Anda
    $apiKey = "AIzaSyDd4FUuqOlOjQBxQVUfz4Gh4ia5FDXLsbI";

    // Menghasilkan URL download menggunakan Google Drive API dengan fields=name
    $fileNameUrl = "https://www.googleapis.com/drive/v3/files/$fileId?fields=name&key=$apiKey";

    // Mendapatkan nama file dari URL dengan fields=name
    $fileName = getNameFromUrl($fileNameUrl);

    if ($fileName === null) {
        echo 'Error: File not found or invalid Google Drive URL.';
        exit; // Stop further execution
    }

    // Menghasilkan URL download menggunakan Google Drive API dengan alt=media
    $downloadUrl = "https://www.googleapis.com/drive/v3/files/$fileId?alt=media&key=$apiKey";

    // Mengubah nama file jika diperlukan
    $modifiedDownloadUrl = changeFileName($downloadUrl, $fileName);

    // Pengecekan respon status header setelah mengirim permintaan ke Google Drive API
    if (http_response_code() !== 200) {
        echo 'Error: Failed to fetch file information from Google Drive.';
        exit; // Stop further execution
    }

    // Menambahkan tautan unduhan ke Aria2 RPC
    $response = addDownloadToAria2($downloadUrl, $fileName);

    if (isset($response['result'])) {
        $gid = $response['result'];
        $downloadStatus = getDownloadStatus($gid);

        // Tampilkan status unduhan beserta nama file
        echo 'Download started. Status: ' . $downloadStatus['result']['status'] . '<br>';
        echo 'File Name: ' . $fileName;
    } else {
        // Tampilkan pesan kesalahan jika gagal menambahkan unduhan
        echo 'Failed to add download.';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>Google Drive Downloader</title>
    <style>
        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            font-family: Arial, sans-serif;
        }

        h1 {
            margin-bottom: 20px;
        }

        input[type="text"] {
            width: 300px;
            padding: 10px;
            margin-bottom: 10px;
        }

        button {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }

        button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <h1>Google Drive Downloader</h1>
    <form method="post" action="">
        <input type="text" id="google_drive_url" name="google_drive_url" placeholder="Enter Google Drive URL" required><br><br>
        <button type="submit" value="Download">Download</button>
    </form>
</body>
</html>
