FROM php:8.3-apache

# 必要なパッケージのインストールとPDO拡張の有効化
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libcurl4-openssl-dev \
    && docker-php-ext-install pdo_mysql mysqli curl

# 自作のApache設定をコンテナにコピーして有効化
COPY default.conf /etc/apache2/sites-available/000-default.conf

# Apacheのmod_rewriteを有効化（URLルーティングで必要になります）
RUN a2enmod rewrite

