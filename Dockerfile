FROM php:5.6-apache

# Install PHP extensions required for CakePHP
RUN docker-php-ext-install pdo_mysql

# Enable Apache modules
RUN a2enmod rewrite

# Copy application
COPY . /var/www/html

# Copy entrypoint
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
