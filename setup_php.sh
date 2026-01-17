#!/bin/bash
# PHP Kurulum Scripti - macOS için

echo "=========================================="
echo "PHP Kurulum Scripti"
echo "=========================================="
echo ""

# Homebrew kontrolü
if ! command -v brew &> /dev/null; then
    echo "⚠️  Homebrew bulunamadı!"
    echo ""
    echo "Homebrew kurulumu için aşağıdaki komutu çalıştırın:"
    echo "/bin/bash -c \"\$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)\""
    echo ""
    echo "Kurulumdan sonra bu scripti tekrar çalıştırın."
    exit 1
fi

echo "✓ Homebrew bulundu"
echo ""

# PHP kurulumu
echo "PHP kuruluyor..."
brew install php

# Kurulum kontrolü
if command -v php &> /dev/null; then
    echo ""
    echo "✅ PHP başarıyla kuruldu!"
    php --version
    echo ""
    echo "PHP yolu: $(which php)"
else
    echo ""
    echo "❌ PHP kurulumu başarısız oldu"
    exit 1
fi
