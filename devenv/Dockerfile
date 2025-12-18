FROM wordpress:6.9-php8.4-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip curl less default-mysql-client rsync \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN curl -sSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp \
    && chmod +x /usr/local/bin/wp

COPY devenv/setup.sh /usr/local/bin/healthchecks-setup.sh
COPY devenv/entrypoint.sh /usr/local/bin/healthchecks-entrypoint.sh

RUN chmod +x /usr/local/bin/healthchecks-setup.sh /usr/local/bin/healthchecks-entrypoint.sh \
    && mkdir -p /var/www/html/wp-content/plugins/vigilant-healthchecks

WORKDIR /var/www/html

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/healthchecks-entrypoint.sh"]
CMD ["php-fpm"]
