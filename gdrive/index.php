<?php
require_once 'config.php';

// Mengambil parameter URL Google Drive dari permintaan POST
$googleDriveUrl = isset($_POST['google_drive_url']) ? $_POST['google_drive_url'] : '';
$downloadOption = isset($_POST['download_option']) ? $_POST['download_option'] : 'google_drive'; // Default to Google Drive

// Variabel untuk menyimpan pesan error
$errorMessage = '';

// Fungsi untuk mengirim permintaan ke Aria2 RPC
function sendAria2RpcRequest($method, $params = array()) {
    global $aria2RpcUrl, $aria2RpcSecretToken;

    // Membuat request JSON-RPC
    $request = array(
        'jsonrpc' => '2.0',
        'id' => '1',
        'method' => $method,
        'params' => array_merge(["token:$aria2RpcSecretToken"], $params) // Tambahkan token ke params
    );

    // Debug: Tampilkan URL dan data request
    // echo "Request URL: $aria2RpcUrl\n";
    // echo "Request Data: " . json_encode($request) . "\n";

    // Inisialisasi cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $aria2RpcUrl); // Set URL Aria2 RPC
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return response sebagai string
    curl_setopt($ch, CURLOPT_POST, true); // Set metode request ke POST

    // Set header
    $headers = array(
        'Content-Type: application/json'
    );

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request)); // Set data POST

    // Eksekusi request dan simpan responsenya
    $response = curl_exec($ch);

    // Cek jika ada error
    if (curl_errno($ch)) {
        $errorMessage = 'cURL Error: ' . curl_error($ch);
        curl_close($ch);
        return array('error' => $errorMessage);
    }

    // Tutup koneksi cURL
    curl_close($ch);

    // Debug: Tampilkan respons dari Aria2 RPC
    // echo "Response from Aria2: " . $response . "\n";
  	// echo "Headers: " . json_encode($headers) . "\n";

    // Decode response JSON dan kembalikan sebagai array
    return json_decode($response, true);
}

// Fungsi untuk menambahkan tautan unduhan ke Aria2 RPC
function addDownloadToAria2($url, $fileName) {
    $params = array(
        array($url),
        array('out' => $fileName)
    );

    $response = sendAria2RpcRequest('aria2.addUri', $params);

    // Cek jika ada error dalam respons
    if (isset($response['error'])) {
        return 'Error: ' . json_encode($response['error']); // Konversi array ke string
    }

    // Cek jika respons mengandung result (berhasil)
    if (isset($response['result'])) {
        return $response;
    }

    // Jika tidak ada result atau error, kembalikan pesan error default
    return 'Failed to add download. No result or error message from Aria2.';
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

        // Menghasilkan URL download berdasarkan pilihan pengguna
        if ($downloadOption === 'sachin_mirror') {
            $downloadUrl = "https://bypass.sachinmirror.eu.org/direct.aspx?id=$fileId";
        } else {
            $downloadUrl = "https://www.googleapis.com/drive/v3/files/$fileId?alt=media&key=$apiKey";
        }

        if ($downloadUrl === "" || $fileName === null) {
            $errorMessage = 'Error: File not found or invalid Google Drive URL.';
        } else {
            // Mengubah nama file jika diperlukan
            $modifiedDownloadUrl = changeFileName($downloadUrl, $fileName);

            // Menambahkan tautan unduhan ke Aria2 RPC
            $response = addDownloadToAria2($downloadUrl, $fileName);

            // Cek jika ada error
            if (is_string($response) && strpos($response, 'Error:') === 0) {
                $errorMessage = $response;
            } elseif (is_array($response) && isset($response['result'])) {
                $gid = $response['result'];
                $downloadStatus = getDownloadStatus($gid);

                // Tampilkan status unduhan beserta nama file
                if (empty($errorMessage)) {
                    echo 'Download started. Status: ' . $downloadStatus['result']['status'] . '<br>';
                    echo 'File Name: ' . $fileName;
                }
            } else {
                // Tampilkan pesan kesalahan jika gagal menambahkan unduhan
                $errorMessage = 'Failed to add download. No response from Aria2.';
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

        select {
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
    if (is_array($errorMessage)) {
        echo '<p class="error-message">' . json_encode($errorMessage) . '</p>';
    } else {
        echo '<p class="error-message">' . $errorMessage . '</p>';
    }
}
    ?>
    <form method="post" action="">
        <h1>Google Drive Downloader</h1>
        <textarea name="google_drive_url" placeholder="Enter Google Drive URL" rows="10" cols="50" required></textarea><br><br>
        <select name="download_option">
            <option value="google_drive">Use Google Drive API</option>
            <option value="sachin_mirror">Use Sachin Mirror API</option>
        </select><br><br>
        <button type="submit" value="Download">Download</button>
    </form>
</body>

</html>