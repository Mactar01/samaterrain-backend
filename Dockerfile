FROM php:8.2-apache

# Installer les dépendances système
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    git \
    curl

# Nettoyer le cache apt
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Installer les extensions PHP requises par Laravel
RUN docker-php-ext-install pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd

# Activer le mod_rewrite d'Apache
RUN a2enmod rewrite

# Configuration Apache pour Laravel
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copier Composer depuis l'image officielle
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Définir le répertoire de travail
WORKDIR /var/www/html

# Copier le code existant dans le conteneur
COPY . .

# Installer les dépendances Laravel
RUN composer install --no-dev --optimize-autoloader

# Gérer les permissions (très important pour le cache et le storage)
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Créer le lien symbolique du storage
RUN php artisan storage:link

# Le script de démarrage (optionnel mais recommandé pour lancer les migrations automatiquement)
RUN echo "#!/bin/bash\n\
php artisan migrate --force\n\
apache2-foreground" > /start.sh && chmod +x /start.sh

CMD ["/start.sh"]
