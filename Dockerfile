FROM ubuntu:24.04

RUN apt-get update && \
    apt-get install -y software-properties-common && \
    LC_ALL=C.UTF-8 add-apt-repository -y -u ppa:ondrej/php

RUN apt-get update

RUN apt-get install -y --no-install-recommends \
        build-essential \
        ca-certificates

RUN apt-get install -y --no-install-recommends \
        curl \
        git \
        make \
        npm

# IMAGEMAGICK

ENV DEBIAN_FRONTEND=noninteractive

ARG IM_VERSION=7.1.1-39
ARG LIB_HEIF_VERSION=1.19.2
ARG LIB_AOM_VERSION=3.10.0
ARG LIB_WEBP_VERSION=1.4.0
ARG LIBJXL_VERSION=0.11.0

RUN apt-get -y update && \
    apt-get -y upgrade && \
    apt-get install -y git make gcc pkg-config autoconf curl g++ cmake clang \
    # libaom
    yasm \
    # libheif
    libde265-0 libde265-dev libjpeg-turbo8-dev x265 libx265-dev libtool \
    # libwebp
    libsdl1.2-dev libgif-dev \
    # libjxl
    libbrotli-dev \
    # IM
    libpng16-16 libpng-dev libgomp1 ghostscript libxml2-dev libxml2-utils libtiff-dev libfontconfig1-dev libfreetype6-dev fonts-dejavu liblcms2-2 liblcms2-dev libtcmalloc-minimal4 \
    # Install manually to prevent deleting with -dev packages
    libxext6 libbrotli1

# Building libjxl
RUN export CC=clang-18 CXX=clang++-18 && \
    git clone -b v${LIBJXL_VERSION} https://github.com/libjxl/libjxl.git --depth 1 --recursive --shallow-submodules && \
    cd libjxl && \
    mkdir build && \
    cd build && \
    cmake -DCMAKE_BUILD_TYPE=Release -DBUILD_TESTING=OFF .. && \
    cmake --build . -- -j$(nproc) && \
    cmake --install . && \
    cd ../../ && \
    rm -rf libjxl && \
    ldconfig /usr/local/lib

# Building libwebp
RUN git clone -b v${LIB_WEBP_VERSION} --depth 1 https://chromium.googlesource.com/webm/libwebp && \
    cd libwebp && \
    mkdir build && cd build && cmake -DBUILD_SHARED_LIBS=ON ../ && make && make install && \
    ldconfig /usr/local/lib && \
    cd ../../ && rm -rf libwebp

# Building libaom
RUN git clone -b v${LIB_AOM_VERSION} --depth 1 https://aomedia.googlesource.com/aom && \
    mkdir build_aom && \
    cd build_aom && \
    cmake ../aom/ -DENABLE_TESTS=0 -DBUILD_SHARED_LIBS=1 && make && make install && \
    ldconfig /usr/local/lib && \
    cd .. && \
    rm -rf aom && \
    rm -rf build_aom

# Building libheif
RUN git clone -b v${LIB_HEIF_VERSION} --depth 1 https://github.com/strukturag/libheif.git && \
    cd libheif/ && \
    mkdir build && cd build && cmake --preset=release .. && make && make install && cd ../../ && \
    ldconfig /usr/local/lib && \
    rm -rf libheif

# Building ImageMagick
RUN git clone -b ${IM_VERSION} --depth 1 https://github.com/ImageMagick/ImageMagick.git && \
    cd ImageMagick && \
    ./configure --without-magick-plus-plus --disable-docs --disable-static --with-tiff --with-jxl --with-tcmalloc && \
    make && make install && \
    ldconfig /usr/local/lib && \
    rm -rf /ImageMagick

RUN apt-get install -y --no-install-recommends \
        php8.3-curl \
        php8.3-fpm \
        php8.3-gd \
        php8.3-imagick \
        php8.3-intl \
        php8.3-bcmath \
        php8.3-mbstring \
        php8.3-pgsql \
        php8.3-xml \
        php8.3-zip

# apparently, have to specify current version of libicu (66 for 20.04, 70 for 22.04, 74 for 24.04...)
RUN apt-get install -y --no-install-recommends \
        postgresql \
        libfontconfig1 \
        libxrender1 \
        libicu74 \
        postgresql-client

RUN apt-get install iputils-ping

# unloaded extensions:
# php8.1-xdebug

# ask for node version 22
RUN curl -sL https://deb.nodesource.com/setup_22.x | bash -
    
RUN apt-get install -y --no-install-recommends nodejs && \
    rm -Rf /var/lib/apt/lists/*

RUN apt-get update && apt-get install zip unzip

# uncomment and build image (docker compose build php) to enable xdebug extension 
# RUN echo xdebug.remote_enable=1 >> /etc/php/8.2/fpm/conf.d/20-xdebug.ini && \
#     echo xdebug.remote_connect_back=1 >> /etc/php/8.2/fpm/conf.d/20-xdebug.ini && \
#     echo xdebug.remote_port=9000 >> /etc/php/8.2/fpm/conf.d/20-xdebug.ini && \
#     echo xdebug.remote_handler=dbgp >> /etc/php/8.2/fpm/conf.d/20-xdebug.ini && \
#     echo xdebug.max_nesting_level=250 >> /etc/php/8.2/fpm/conf.d/20-xdebug.ini

RUN echo 'date.timezone=Europe/Brussels' >> /etc/php/8.3/fpm/php.ini && \
    echo 'date.timezone=Europe/Brussels' >> /etc/php/8.3/cli/php.ini && \
    echo 'short_open_tag=off' >> /etc/php/8.3/fpm/php.ini && \
    echo 'upload_max_filesize=50M' >> /etc/php/8.3/fpm/php.ini && \
    echo 'post_max_size=100M' >> /etc/php/8.3/fpm/php.ini && \
    echo 'memory_limit=1024M' >> /etc/php/8.3/fpm/php.ini

ADD docker/php/php-fpm.conf /etc/php/8.3/fpm/pool.d/www.conf

RUN echo "include=/etc/php/8.3/fpm/pool.d/*.conf" > /etc/php/8.3/fpm/php-fpm.conf

ENV PATH "$PATH:./node_modules/.bin"

RUN sed -e s/33/1000/g -i /etc/passwd
RUN sed -e s/33/1000/g -i /etc/group

COPY . /data/www

# create home dir for www-data, necessary for yarn install
RUN mkdir /var/www
RUN chown -R www-data:www-data /var/www/

# currently installing yarn v1.x
# yarn is now on v3 but those seems to be big changes
# ( no more node_modules directory )
RUN npm install -g yarn

RUN chown -R www-data:www-data /data/www

WORKDIR /data/www

EXPOSE 9000

CMD ["php-fpm8.3"]