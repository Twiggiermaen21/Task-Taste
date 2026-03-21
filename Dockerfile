FROM php:8.2-apache

# 1. Pakiety systemowe dla Composera
RUN apt-get update && apt-get install -y git unzip libzip-dev && docker-php-ext-install zip

# 2. Włączenie mod_rewrite dla Apache (żeby działały ścieżki)
RUN a2enmod rewrite
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# 3. Instalacja Composera
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Kopiowanie Twojego kodu na serwer
COPY . /var/www/html/

# 5. MAGIA: Pobieranie bibliotek (To utworzy brakujący folder vendor!)
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