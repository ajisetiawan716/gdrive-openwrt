<?php
require_once 'config.php';

// Mengambil parameter URL Google Drive dari permintaan POST
$googleDriveUrl = isset($_POST['google_drive_url']) ? $_POST['google_drive_url'] : '';

// Variabel untuk menyimpan pesan error
$errorMessage = '';

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
    if (isset($path[3])) {
        return $path[3];
    } else {
        return null; // Atau sesuaikan dengan tindakan yang sesuai
    }
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

    // Check for the format https://drive.google.com/file/d/FILE_ID/view?usp=drivesdk
    if (preg_match('/^https:\/\/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $url)) {
        return true;
    }

    // Check for the format https://drive.google.com/file/d/FILE_ID/view
    if (preg_match('/^https:\/\/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)\/view/', $url)) {
        return true;
    }

    return false;
}

// Function to download a Google Sheets file from a Google Sheets URL
function downloadGoogleSheets($url) {
    // Mendapatkan file ID dari URL Google Sheets
    $fileId = getFileIdFromUrl($url);

    // Menentukan URL untuk mengunduh file Google Sheets
    $downloadUrl = "https://sheets.googleapis.com/v4/spreadsheets/$fileId/values/A1:Z100?&key=$Gsheet_apiKey";

    // Mengambil nama file dari URL
    $fileName = getNameFromUrl($url);

    if ($downloadUrl === "" || $fileName === null) {
        $errorMessage = 'Error: File not found or invalid Google Sheets URL.';
    } else {
        // Menambahkan tautan unduhan ke Aria2 RPC
        $response = addDownloadToAria2($downloadUrl, $fileName);

        if (isset($response['result'])) {
            $gid = $response['result'];
            $downloadStatus = getDownloadStatus($gid);

            // Tampilkan status unduhan beserta nama file
            if (empty($errorMessage)) {
                echo 'Download started. Status: ' . $downloadStatus['result']['status'] . '<br>';
                echo 'File Name: ' . $fileName;
            }
        } else {
            // Tampilkan pesan kesalahan jika gagal menambahkan unduhan
            $errorMessage = 'Failed to add download.';
        }
    }

    return $errorMessage;
}

// Memulai unduhan sesuai dengan jenis URL
if (!empty($googleDriveUrl)) {
    if (preg_match('/^(https?:\/\/)?docs\.google\.com\/spreadsheets\/d\/([a-zA-Z0-9_-]+)\/edit/', $googleDriveUrl)) {
        // Jika URL adalah Google Sheets URL
        $errorMessage = downloadGoogleSheets($googleDriveUrl);
    } else {
        // Jika URL bukan Google Sheets URL
        // Menggunakan URL Google Drive
        // Mendapatkan file ID dari URL Google Drive
        $fileId = getFileIdFromUrl($googleDriveUrl);

        // Menghasilkan URL download menggunakan Google Drive API dengan fields=name
        $fileNameUrl = "https://www.googleapis.com/drive/v3/files/$fileId?fields=name&key=$apiKey";

        // Mendapatkan nama file dari URL dengan fields=name
        $fileName = getNameFromUrl($fileNameUrl);

        // Menghasilkan URL download menggunakan Google Drive API dengan alt=media
       // $downloadUrl = "https://bypass.sachinmirror.eu.org/direct.aspx?id=$fileId"; //
           $downloadUrl = "https://www.googleapis.com/drive/v3/files/$fileId?alt=media&key=$apiKey";

        if ($downloadUrl === "" || $fileName === null) {
            $errorMessage = 'Error: File not found or invalid Google Drive URL.';
        } else {
            // Mengubah nama file jika diperlukan
            $modifiedDownloadUrl = changeFileName($downloadUrl, $fileName);

            // Pengecekan respon status header setelah mengirim permintaan ke Google Drive API
            $httpStatusCode = http_response_code();
            if (http_response_code() !== 200) {
                $errorMessage = 'Error: Failed to fetch file information from Google Drive.';
            } else {
                // Menambahkan tautan unduhan ke Aria2 RPC
                $response = addDownloadToAria2($downloadUrl, $fileName);

                if (isset($response['result'])) {
                    $gid = $response['result'];
                    $downloadStatus = getDownloadStatus($gid);

                    // Tampilkan status unduhan beserta nama file
                    if (empty($errorMessage)) {
                        echo 'Download started. Status: ' . $downloadStatus['result']['status'] . '<br>';
                        echo 'File Name: ' . $fileName;
                    }
                } else {
                    // Tampilkan pesan kesalahan jika gagal menambahkan unduhan
                    $errorMessage = 'Failed to add download.';
                }
            }
        }
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
            background-color: #2a89ff;
            color: white;
            border: none;
            cursor: pointer;
        }

        button:hover {
            background-color: #0058c5;
        }

        p.error-message {
            color: red;
        }
    </style>
</head>

<body>
    <?php
    if (!empty($errorMessage)) {
        echo '<p class="error-message">' . $errorMessage . '</p>';
    }
    ?>
    <form method="post" action="">
        <h1>Google Drive Downloader</h1>
        <textarea name="google_drive_url" placeholder="Enter Google Drive URL" rows="10" cols="50" required></textarea><br><br>
        <button type="submit" value="Download">Download</button>
    </form>
</body>

</html>
