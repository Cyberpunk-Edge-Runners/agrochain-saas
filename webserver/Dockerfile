FROM  php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

# explicitly turn on mod_rewrite so the RewriteEngine in the .htaccess files work
RUN a2enmod rewrite

# Replaces the image's default vhost — sets AllowOverride All, which is what lets public/.htaccess (where the actual clean-URL rewrite rules
# live) be read and take effect at all.
COPY webserver/000-default.conf /etc/apache2/sites-available/000-default.conf

# Raises upload limits above our app-level 5MB check, and disables printing  raw PHP errors/warnings straight onto the page.
COPY webserver/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# --- Composer & the AWS SDK ---
    
# copying the composer files from the official docker image without  pulling the whole
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer


# 1. Install system tools needed by Composer
RUN apt-get update && apt-get install -y git unzip zip


# Copying composer.json BEFORE the rest of the app (nothing else has been copied into /var/www yet at this point — public/ and includes are 
# bind-mounted at runtime via docker-compose.yml, not baked into the image) means Docker can cache this layer. 
# The SDK only needs re-downloading when composer.json itself changes, not on every rebuild — meaningfully faster iteration once this is set up.
COPY composer.json composer.lock* /var/www/
COPY . /var/www/
RUN cd /var/www && composer install --no-dev --optimize-autoloader

# Pre-create the uploads directory and own it as www-data (the user Apache runs as in this image) so the volume
# set in our compose file for the user document uploads doesnt start out owned by root, and PHP running as www-data gets "Permission denied" the moment it tries to 
# mkdir/write into files int it.
RUN mkdir -p /var/www/uploads/documents \
    && chown -R www-data:www-data /var/www/uploads

WORKDIR /var/www/html