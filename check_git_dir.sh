#!/bin/bash
# Plesk Git dizin kontrol scripti

echo "=== Git Dizin Kontrolü ==="
echo ""

# Git dizinine git
cd /var/www/vhosts/wingcert.com/git 2>/dev/null || {
    echo "HATA: /var/www/vhosts/wingcert.com/git dizini bulunamadı!"
    exit 1
}

echo "Mevcut dizin: $(pwd)"
echo ""

# Tüm dosya ve dizinleri listele (gizli olanlar dahil)
echo "=== Tüm dosyalar ve dizinler (gizli dahil) ==="
ls -la

echo ""
echo "=== parasut_v3 ile ilgili her şey ==="
ls -la | grep -i parasut

echo ""
echo "=== .git ile biten dizinler ==="
ls -la | grep "\.git"

echo ""
echo "=== Git dizinlerini kontrol et ==="
for dir in */; do
    if [ -d "$dir/.git" ]; then
        echo "Git repo bulundu: $dir"
        cd "$dir"
        git remote -v 2>/dev/null || echo "  (Git remote yok)"
        cd ..
    fi
done

echo ""
echo "=== Plesk Git ayarları kontrolü ==="
if [ -f "/var/www/vhosts/wingcert.com/.git/config" ]; then
    echo "Ana domain'de .git var"
    cat /var/www/vhosts/wingcert.com/.git/config
else
    echo "Ana domain'de .git yok"
fi

echo ""
echo "=== Önerilen çözüm ==="
echo "Eğer hiçbir şey bulunamadıysa, Plesk'te:"
echo "1. Git bölümüne gidin"
echo "2. 'Disable Git' butonuna tıklayın (varsa)"
echo "3. Sayfayı yenileyin"
echo "4. Tekrar 'Enable Git' yapın"
echo "5. Repository URL'ini girin"
