# Plesk Git Bağlantı Sorunu Çözümü

## Sorun
```
fatal: destination path '/var/www/vhosts/wingcert.com/git/parasut_v3.git' already exists and is not an empty directory.
```

## Çözüm Yöntemleri

### Yöntem 1: Mevcut Dizini Temizle (Önerilen)

Plesk'te SSH/Terminal erişimi ile:

```bash
# Dizine gidin
cd /var/www/vhosts/wingcert.com/git

# İçeriği kontrol edin
ls -la parasut_v3.git

# Eğer önemli bir şey yoksa, dizini silin
rm -rf parasut_v3.git

# Veya sadece içeriği temizleyin (daha güvenli)
cd parasut_v3.git
rm -rf *
rm -rf .git
cd ..
rmdir parasut_v3.git
```

Sonra Plesk'te tekrar Git bağlantısını kurmayı deneyin.

### Yöntem 2: Farklı Dizin Adı Kullan

Plesk'te Git ayarlarında repository adını değiştirin:
- `parasut_v3` yerine `parasut-api` veya `parasut_integration` kullanın

### Yöntem 3: Mevcut Dizini Git Repository Olarak Kullan

Eğer dizinde önemli bir şey varsa:

```bash
cd /var/www/vhosts/wingcert.com/git/parasut_v3.git

# Mevcut içeriği yedekleyin
cd ..
mv parasut_v3.git parasut_v3.git.backup

# Plesk'te tekrar bağlantı kurun
# Sonra gerekirse backup'tan dosyaları geri alın
```

### Yöntem 4: Manuel Git Clone (Plesk Arayüzü Yerine)

Plesk'te Git arayüzü çalışmıyorsa, SSH ile manuel yapın:

```bash
# Git dizinine gidin
cd /var/www/vhosts/wingcert.com/git

# Eski dizini silin
rm -rf parasut_v3.git

# GitHub'dan clone edin
git clone https://github.com/KULLANICI_ADINIZ/REPO_ADI.git parasut_v3.git

# Veya SSH ile:
# git clone git@github.com:KULLANICI_ADINIZ/REPO_ADI.git parasut_v3.git

# İzinleri ayarlayın
chown -R kullanici:kullanici parasut_v3.git
```

Sonra Plesk'te "Refresh" veya "Pull" yapın.

## Adım Adım Çözüm (En Güvenli)

### 1. SSH ile Sunucuya Bağlanın

```bash
ssh kullanici@wingcert.com
```

### 2. Dizini Kontrol Edin

```bash
cd /var/www/vhosts/wingcert.com/git
ls -la
```

### 3. İçeriği Yedekleyin (Güvenlik İçin)

```bash
# Eğer önemli bir şey varsa yedekleyin
tar -czf parasut_v3_backup_$(date +%Y%m%d).tar.gz parasut_v3.git
```

### 4. Dizini Temizleyin

```bash
# İçeriği kontrol edin
cd parasut_v3.git
ls -la

# Eğer boş veya önemsizse, çıkın ve silin
cd ..
rm -rf parasut_v3.git
```

### 5. Plesk'te Tekrar Deneyin

1. Plesk paneline gidin
2. Git bölümüne gidin
3. Repository URL'ini girin
4. "Pull" veya "Enable Git" butonuna tıklayın

### 6. Alternatif: Manuel Kurulum

Eğer Plesk arayüzü çalışmıyorsa:

```bash
# Git dizinine gidin
cd /var/www/vhosts/wingcert.com/git

# Eski dizini silin
rm -rf parasut_v3.git

# GitHub'dan clone edin
git clone https://github.com/KULLANICI_ADINIZ/parasut-api-integration.git parasut_v3.git

# Branch'i kontrol edin
cd parasut_v3.git
git branch

# Eğer main branch yoksa:
git checkout -b main
# veya
git branch -M main

# İzinleri ayarlayın (Plesk kullanıcısına göre)
chown -R kullanici:psacln parasut_v3.git
```

## Sorun Devam Ederse

### Git Cache'i Temizle

```bash
cd /var/www/vhosts/wingcert.com/git
rm -rf .git-cache
```

### Plesk Git Ayarlarını Sıfırla

1. Plesk'te Git bölümüne gidin
2. "Disable Git" butonuna tıklayın
3. Birkaç saniye bekleyin
4. Tekrar "Enable Git" yapın
5. Repository URL'ini girin

### Log Dosyalarını Kontrol Edin

```bash
# Plesk log dosyalarını kontrol edin
tail -f /var/log/plesk/git.log
# veya
tail -f /usr/local/psa/var/log/git.log
```

## Hızlı Çözüm (Kopyala-Yapıştır)

SSH'de şu komutları çalıştırın:

```bash
cd /var/www/vhosts/wingcert.com/git && \
ls -la parasut_v3.git && \
echo "--- İçerik yukarıda görünüyor ---" && \
echo "Eğer önemli bir şey yoksa şu komutu çalıştırın:" && \
echo "rm -rf parasut_v3.git"
```

Sonra Plesk'te tekrar Git bağlantısını kurmayı deneyin.
