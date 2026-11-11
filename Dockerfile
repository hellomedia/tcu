FROM ubuntu:24.04

RUN apt-get update && \
    apt-get install -y software-properties-common && \
    LC_ALL=C.UTF-8 add-apt-repository -y -u ppa:ondrej/php

RUN apt-get update

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git

RUN apt-get install -y --no-install-recommends \
        php8.5-curl \
        php8.5-fpm \
        php8.5-gd \
        php8.5-intl \
        php8.5-bcmath \
        php8.5-mbstring \
        php8.5-pgsql \
        php8.5-xml \
        php8.5-zip

# apparently, have to specify current version of libicu (66 for 20.04, 70 for 22.04, 74 for 24.04...)
RUN apt-get install -y --no-install-recommends \
        postgresql \
        libicu74 \
        postgresql-client

RUN apt-get install iputils-ping

# unloaded extensions:
# php8.1-xdebug

RUN apt-get update && apt-get install zip unzip

# uncomment and build image (docker compose build php) to enable xdebug extension 
# RUN echo xdebug.remote_enable=1 >> /etc/php/8.2/fpm/conf.d/20-xdebug.ini && \
#     echo xdebug.remote_connect_back=1 >> /etc/php/8.2/fpm/conf.d/20-xdebug.ini && \
#     echo xdebug.remote_port=9000 >> /etc/php/8.2/fpm/conf.d/20-xdebug.ini && \
#     echo xdebug.remote_handler=dbgp >> /etc/php/8.2/fpm/conf.d/20-xdebug.ini && \
#     echo xdebug.max_nesting_level=250 >> /etc/php/8.2/fpm/conf.d/20-xdebug.ini

RUN echo 'date.timezone=Europe/Brussels' >> /etc/php/8.5/fpm/php.ini && \
    echo 'date.timezone=Europe/Brussels' >> /etc/php/8.5/cli/php.ini && \
    echo 'short_open_tag=off' >> /etc/php/8.5/fpm/php.ini && \
    echo 'upload_max_filesize=50M' >> /etc/php/8.5/fpm/php.ini && \
    echo 'post_max_size=100M' >> /etc/php/8.5/fpm/php.ini && \
    echo 'memory_limit=1024M' >> /etc/php/8.5/fpm/php.ini

ADD docker/php/php-fpm.conf /etc/php/8.5/fpm/pool.d/www.conf

RUN echo "include=/etc/php/8.5/fpm/pool.d/*.conf" > /etc/php/8.5/fpm/php-fpm.conf

RUN sed -e s/33/1000/g -i /etc/passwd
RUN sed -e s/33/1000/g -i /etc/group

COPY . /data/www

# home dir for www-data (php-fpm user)
RUN mkdir /var/www
RUN chown -R www-data:www-data /var/www/

RUN chown -R www-data:www-data /data/www

WORKDIR /data/www

EXPOSE 9000

CMD ["php-fpm8.5"]