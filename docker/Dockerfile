FROM php:8.1-fpm

RUN apt-get update && apt-get install -y \
		libfreetype-dev \
		libzip-dev \
		libjpeg62-turbo-dev \
		libpng-dev \
	&& docker-php-ext-configure gd --with-freetype --with-jpeg \
	&& docker-php-ext-install -j$(nproc) gd
RUN docker-php-ext-install zip
RUN pecl install redis && docker-php-ext-enable redis
RUN apt-get install -y wget && wget https://mirrors.aliyun.com/composer/composer.phar && mv composer.phar /usr/bin/composer && chmod u+x /usr/bin/composer
RUN composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/
RUN docker-php-ext-install mysqli pdo pdo_mysql bcmath

RUN echo "post_max_size = 500M" >> /usr/local/etc/php/conf.d/docker-php-ext-bcmath.ini \
    && echo "upload_max_filesize = 500M" >> /usr/local/etc/php/conf.d/docker-php-ext-bcmath.ini


WORKDIR /var/www/html
