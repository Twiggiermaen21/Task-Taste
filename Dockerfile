# 1. Wybieramy oficjalny, lekki obraz PHP z wbudowanym serwerem Apache
FROM php:8.2-apache

# 2. Włączamy moduł Rewrite w Apache (niezbędny dla naszego Slim Framework i pliku .htaccess)
RUN a2enmod rewrite

# 3. Zezwalamy Apache na czytanie naszych reguł z pliku .htaccess
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# 4. Kopiujemy wszystkie pliki z Twojego komputera do głównego folderu serwera w kontenerze
COPY . /var/www/html/

# 5. Tworzymy folder 'data' (jeśli go nie ma) i dajemy serwerowi pełne uprawnienia, 
# żeby mógł tam tworzyć i edytować plik bazy SQLite
RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/data \
    && chmod -R 775 /var/www/html/data

# 6. Informujemy, że aplikacja działa na porcie 80
EXPOSE 80