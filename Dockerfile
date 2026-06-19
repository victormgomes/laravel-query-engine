FROM php:8.5-cli

ARG UID=1000
ARG GID=1000
ARG USERNAME=app

RUN apt-get update && apt-get install -y \
        unzip \
        libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

RUN groupadd -g ${GID} ${USERNAME} \
    && useradd -u ${UID} -g ${GID} -m -s /bin/bash ${USERNAME}

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN curl -fsSL https://github.com/php/pie/releases/latest/download/pie.phar \
    -o /usr/local/bin/pie \
    && chmod +x /usr/local/bin/pie

USER root

RUN pie install pecl/pcov

USER ${USERNAME}

WORKDIR /app
