FROM php:7.1-cli

WORKDIR /var/www

#
# Dependencies
#
RUN apt-get update \
    && apt-get install -y \
        libzip-dev \
        zip \
        wget \
        gnupg \
    && docker-php-ext-configure zip --with-libzip \
    && docker-php-ext-install zip bcmath

#
# Redis
#
RUN curl -L -o /tmp/redis.tar.gz https://github.com/phpredis/phpredis/archive/4.0.2.tar.gz \
    && tar xfz /tmp/redis.tar.gz \
    && rm -r /tmp/redis.tar.gz \
    && mkdir -p /usr/src/php/ext \
    && mv phpredis-4.0.2 /usr/src/php/ext/redis \
    && docker-php-ext-install redis

#
# Composer
#
RUN apt-get install -y curl \
    && curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/bin/composer

#
# New Relic
#
RUN echo 'deb http://apt.newrelic.com/debian/ newrelic non-free' | tee /etc/apt/sources.list.d/newrelic.list \
    && wget -O- https://download.newrelic.com/548C16BF.gpg | apt-key add - \
    && apt-get update \
    && apt-get install -y newrelic-php5 \
    && newrelic-install install

#
# Apisearch installation
#
RUN mkdir /var/www/apisearch
COPY . /var/www/apisearch
RUN cd /var/www/apisearch && \
    composer install -n --prefer-dist && \
    composer dump-autoload && \
    rm -Rf /var/www/apisearch/var/cache && \
    php /var/www/apisearch/bin/console cache:warmup --env=prod

COPY docker/* /

EXPOSE 8200
HEALTHCHECK --interval=5s --timeout=3s CMD php /var/www/apisearch/bin/console apisearch-server:check-health --env=prod > /dev/null 2>&1
ENTRYPOINT ["/server-entrypoint.sh"]
