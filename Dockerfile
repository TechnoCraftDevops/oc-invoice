
FROM php:8.2-cli-alpine



# install common php lib
RUN apk update
RUN apk add curl unzip git lsb-release ca-certificates wget

RUN apk add php-json php-mbstring php-xml php-xdebug php-curl

# Installer les dépendances nécessaires pour PCOV
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del $PHPIZE_DEPS

# Installer les dépendances système pour GD
RUN apk add --no-cache \
    libpng libpng-dev \
    libjpeg-turbo libjpeg-turbo-dev \
    libwebp libwebp-dev \
    freetype freetype-dev

# Configurer et installer l'extension GD
RUN docker-php-ext-configure gd \
    --with-jpeg \
    --with-webp \
    --with-freetype \
    && docker-php-ext-install gd

# Nettoyer les dépendances inutiles
RUN apk del --no-cache libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev

# install common php-unit
RUN wget -O phpunit.phar https://phar.phpunit.de/phpunit-10.phar
RUN chmod +x phpunit.phar
RUN mv phpunit.phar /usr/local/bin/phpunit

# install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# installer dd
RUN composer require symfony/var-dumper

RUN echo "error_reporting = E_ALL & ~E_WARNING" > /usr/local/etc/php/conf.d/memory-limit.ini

WORKDIR /app

CMD ["/bin/sh"]
