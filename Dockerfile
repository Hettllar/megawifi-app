FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libzip-dev libxml2-dev libxslt-dev libicu-dev \
    libonig-dev libcurl4-openssl-dev libsodium-dev \
    zip unzip git curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    bcmath calendar exif gd gettext intl mbstring \
    mysqli pdo pdo_mysql opcache pcntl shmop \
    sockets sodium xml xsl zip \
    && pecl install redis igbinary \
    && docker-php-ext-enable redis igbinary opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN echo 'upload_max_filesize = 100M' > /usr/local/etc/php/conf.d/custom.ini && \
    echo 'post_max_size = 100M' >> /usr/local/etc/php/conf.d/custom.ini && \
    echo 'memory_limit = 512M' >> /usr/local/etc/php/conf.d/custom.ini && \
    echo 'max_execution_time = 300' >> /usr/local/etc/php/conf.d/custom.ini && \
    echo 'opcache.enable=1' >> /usr/local/etc/php/conf.d/custom.ini && \
    echo 'opcache.memory_consumption=256' >> /usr/local/etc/php/conf.d/custom.ini && \
    echo 'opcache.max_accelerated_files=20000' >> /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/megawifi
COPY . .
RUN rm -f Dockerfile docker-compose.yml nginx.conf megawifi.sql megawifi-setup.sh

RUN chown -R www-data:www-data /var/www/megawifi && \
    chmod -R 775 storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
