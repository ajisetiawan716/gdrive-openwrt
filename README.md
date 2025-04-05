# Google Drive Downloader for OpenWRT

Repositori ini menyediakan file Php di dalam folder `gdrive` yang berfungsi untuk mengunduh file dari Google Drive melalui `aria2` yang berbasis antarmuka web.

---

## ✅ Fitur

- Download file langsung dari Google Drive
- Menggunakan tool aria2.
- Berjalan di antarmuka yang berbasis web.

---

## 🔧 Cara Install

1. Pastikan `aria2` sudah terinstall di sistem OpenWRT.
   
   ```bash
   opkg update
   opkg install aria2 
   ```

2. Copy paste script bash berikut ke terminal OpenWRT.
   
   ```bash
   wget --no-check-certificate -q "https://raw.githubusercontent.com/ajisetiawan716/gdrive-openwrt/refs/heads/main/install.sh" -O /tmp/install && cd /tmp && sh install 
   ```

3. Coba cek di folder `/www/gdrive` pastikan sudah terunduh.
   
   ```bash
   ls -s /www/gdrive
   ```

4.  Atur konfigurasi di /www/gdrive/config.php.
   
   ```php
   <?php
   	// Ganti 'YOUR_API_KEY' dengan kunci API Google Drive Anda
   	$apiKey = "YOUR_API_KEY";
   
       // Ganti 'YOUR_API_KEY' dengan kunci API Google Sheets Anda
       $Gsheet_apiKey = "YOUR_API_KEY";
   	
   	// Mengatur URL Aria2 RPC
   	$aria2RpcUrl = 'http://localhost:6800/jsonrpc';
   
   	// Mengatur username dan password Aria2 RPC
   	$aria2Username = 'your_username';
   	$aria2Password = 'your_password';
     $aria2RpcSecretToken = "token"; // Ganti dengan token yang Anda gunakan
   
   
   ?>
   
   ```

---

## 🔧 Penggunaan

            Aplikasi dapat diakses melalui antarmuka web.

```
http://IP-ADDRESS/gdrive
```



---

## 📁 Contoh Penggunaan

1. Siapkan URL Google Drive yang akan Anda download filenya.

2. Copy dan buka `http://IP-ADDRESS/gdrive` 

3. Paste URL dan masukkan ke kolom `Enter Google Drive URL.`

4. Pilih API download yang akan digunakan, misalnya `Use Google Drive API.`

5. Tekan tombol `Download,` periksa status download melalui antarmuka web aria2. Misalnya menggunakan `ariaNg.`

---

## 🛠️ Sumber

Project ini ditulis sendiri oleh saya dengan bantuan ChatGPT AI.
