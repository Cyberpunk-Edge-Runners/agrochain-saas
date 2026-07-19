FROM  php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

# mod_rewrite is what makes the RewriteEngine/RewriteRule directives in
# public/.htaccess actually work — it's not enabled by default in this
# image, has to be turned on explicitly.
RUN a2enmod rewrite

# Replaces the image's default vhost — sets AllowOverride All, which is
# what lets public/.htaccess (where the actual clean-URL rewrite rules
# live) be read and take effect at all.
COPY webserver/000-default.conf /etc/apache2/sites-available/000-default.conf

# Raises upload limits above our app-level 5MB check, and disables printing
# raw PHP errors/warnings straight onto the page — see uploads.ini for why.
COPY webserver/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Pre-create the uploads directory and own it as www-data (the user Apache
# runs as in this image). This matters specifically because of HOW Docker
# named volumes work: the first time uploads_data is mounted onto
# /var/www/uploads, Docker copies whatever's already there in the image
# (including ownership) into the fresh volume. Without this step, the
# volume starts out owned by root, and PHP running as www-data gets
# "Permission denied" the moment it tries to mkdir/write into it.
RUN mkdir -p /var/www/uploads/documents \
    && chown -R www-data:www-data /var/www/uploads

WORKDIR /var/www/html