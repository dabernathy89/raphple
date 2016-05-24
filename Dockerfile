FROM php:7.0-cli

# install ev
RUN docker-php-ext-install -j$(nproc) pdo pdo_mysql && pecl install ev

# Install Composer
RUN curl https://getcomposer.org/composer.phar > /usr/sbin/composer

# Copy configs
COPY container/php.ini /usr/local/etc/php

ENTRYPOINT ["php", "/var/app/public/index.php"]
EXPOSE 80

# For local dev, mount volume
VOLUME /var/app

# set up app; order of operations optimized for maximum layer reuse
# RUN mkdir /var/app
# COPY composer.lock /var/app/composer.lock
# COPY composer.json /var/app/composer.json
# RUN cd /var/app && php /usr/sbin/composer install --prefer-dist -o
# COPY templates /var/app/templates
# COPY public /var/app/public

