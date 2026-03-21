FROM php:8.2-apache

# NOWOŚĆ: Zmiana DocumentRoot na folder public (standard dla nowoczesnych aplikacji)
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 1. Pakiety systemowe dla Composera i bazy MongoDB
RUN apt-get update && apt-get install -y git unzip libzip-dev libssl-dev \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && docker-php-ext-install zip

# 2. Włączenie mod_rewrite dla Apache
RUN a2enmod rewrite

# 3. Instalacja Composera
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Kopiowanie Twojego kodu na serwer
COPY . /var/www/html/

# 5. Pobieranie bibliotek (Slim, Twig)
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader

# 6. Tworzenie folderów na dane i zdjęcia z odpowiednimi uprawnieniami
RUN mkdir -p /var/www/html/data \
    && mkdir -p /var/www/html/public/uploads \
    && chown -R www-data:www-data /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/public/uploads \
    && chmod -R 775 /var/www/html/data \
    && chmod -R 775 /var/www/html/public/uploads

EXPOSE 80