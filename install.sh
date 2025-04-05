#!/bin/sh

    echo "Membersihkan cache Luci..."
    rm -f /tmp/luci-indexcache
    rm -f /tmp/luci-modulecache/*
    echo "Cache Luci telah dibersihkan."



    echo "Menginstall aplikasi..."
    
    # Download file-file aplikasi dari GitHub
    wget -q -O /www/gdrive/config.php https://raw.githubusercontent.com/ajisetiawan716/gdrive-openwrt/refs/heads/main/gdrive/config.php
    wget -q -O /www/gdrive/index.php https://raw.githubusercontent.com/ajisetiawan716/gdrive-openwrt/refs/heads/main/gdrive/index.php
 
    # Set permissions untuk file yang ada di folder "gdrive"
    chmod -R 755 /www/gdrive/*

    echo "Aplikasi berhasil diinstall."


    echo "Membersihkan cache Luci..."
    rm -f /tmp/luci-indexcache
    rm -f /tmp/luci-modulecache/*
    echo "Cache Luci telah dibersihkan."