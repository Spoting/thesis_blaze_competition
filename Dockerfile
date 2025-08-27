#syntax=docker/dockerfile:1

# https://github.com/dunglas/frankenphp/blob/main/docs/docker.md
FROM dunglas/frankenphp:builder AS builder

# Copy xcaddy in the builder image
COPY --from=caddy:builder /usr/bin/xcaddy /usr/bin/xcaddy

RUN curl -L https://go.dev/dl/go1.25.0.linux-amd64.tar.gz -o go1.25.0.linux-amd64.tar.gz \
    && rm -rf /usr/local/go \
    && tar -C /usr/local -xzf go1.25.0.linux-amd64.tar.gz \
    && rm go1.25.0.linux-amd64.tar.gz \
    && export PATH=$PATH:/usr/local/go/bin

# CGO must be enabled to build FrankenPHP
RUN CGO_ENABLED=1 \
    XCADDY_SETCAP=1 \
    XCADDY_GO_BUILD_FLAGS="-ldflags='-w -s' -tags=nobadger,nomysql,nopgx" \
    CGO_CFLAGS=$(php-config --includes) \
    CGO_LDFLAGS="$(php-config --ldflags) $(php-config --libs)" \
    xcaddy build \
        --output /usr/local/bin/frankenphp \
        --with github.com/dunglas/frankenphp=./ \
        --with github.com/dunglas/frankenphp/caddy=./caddy/ \
        --with github.com/dunglas/caddy-cbrotli \
        # Mercure and Vulcain are included in the official build, but feel free to remove them
        --with github.com/dunglas/mercure/caddy \
        --with github.com/dunglas/vulcain/caddy \
        # Add extra Caddy modules here
    	--with github.com/caddyserver/cache-handler
    # Or use the following lines instead of the cache-handler one for the latest improvements
    # @65cb24114d76a7de3f4e8c7b8ef7df3efd028899 can be removed when a new release of `darkweak/souin` will be published
    #--with github.com/darkweak/souin/plugins/caddy@65cb24114d76a7de3f4e8c7b8ef7df3efd028899 \
    #--with github.com/darkweak/souin@65cb24114d76a7de3f4e8c7b8ef7df3efd028899 \
    #--with github.com/darkweak/storages/otter/caddy
	
# Versions
FROM dunglas/frankenphp:1-php8.4 AS frankenphp_upstream

# The different stages of this Dockerfile are meant to be built into separate images
# https://docs.docker.com/develop/develop-images/multistage-build/#stop-at-a-specific-build-stage
# https://docs.docker.com/compose/compose-file/#target
COPY --from=builder /usr/local/bin/frankenphp /usr/local/bin/frankenphp

# Base FrankenPHP image
FROM frankenphp_upstream AS frankenphp_base

ARG USER=appuser

RUN \
	# Use "adduser -D ${USER}" for alpine based distros
	useradd ${USER}; \
	# Add additional capability to bind to port 80 and 443
	setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp; \
	# Give write access to /data/caddy and /config/caddy
	chown -R ${USER}:${USER} /data/caddy && chown -R ${USER}:${USER} /config/caddy


WORKDIR /app

RUN chown ${USER}:${USER} /app

VOLUME /app/var/

# persistent / runtime deps
# hadolint ignore=DL3008
RUN apt-get update && apt-get install -y --no-install-recommends \
	acl \
	file \
	gettext \
	git \
	make \
	&& rm -rf /var/lib/apt/lists/*

RUN set -eux; \
	install-php-extensions \
		@composer \
		apcu \
		intl \
		opcache \
		zip \
		amqp \
		redis \
		sockets \
	;

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

# Transport to use by Mercure (default to Bolt)
ENV MERCURE_TRANSPORT_URL=bolt:///data/mercure.db

ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"

###> recipes ###
###> doctrine/doctrine-bundle ###
RUN install-php-extensions pdo_pgsql
###< doctrine/doctrine-bundle ###
###< recipes ###

COPY --link frankenphp/conf.d/10-app.ini $PHP_INI_DIR/app.conf.d/
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link frankenphp/Caddyfile /etc/frankenphp/Caddyfile

ENTRYPOINT ["docker-entrypoint"]

HEALTHCHECK --start-period=60s CMD curl -f http://localhost:2019/metrics || exit 1
CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile" ]

# Dev FrankenPHP image
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev
ENV XDEBUG_MODE=off
ENV FRANKENPHP_WORKER_CONFIG=watch

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

RUN set -eux; \
	install-php-extensions \
		xdebug \
	;

COPY --link frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/app.conf.d/

CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--watch" ]

# This stage is dedicated to installing Composer dependencies
FROM frankenphp_base AS frankenphp_vendor

ARG USER=appuser

# Copy only composer files
COPY --link --chown=${USER}:${USER} composer.* symfony.* ./

# Install dependencies AS THE NON-ROOT USER
# This ensures the entire /vendor directory is created with the correct ownership
USER ${USER}
RUN set -eux; \
    composer install --no-cache --prefer-dist --no-dev --no-scripts --no-progress


# Prod FrankenPHP image
FROM frankenphp_base AS frankenphp_prod

ARG USER=appuser
ENV APP_ENV=prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link frankenphp/conf.d/20-app.prod.ini $PHP_INI_DIR/app.conf.d/

# Create and set permissions for var/, which is fast
RUN mkdir -p var/cache var/log && \
    chown -R ${USER}:${USER} var

# Copy the pre-built vendor directory from the vendor stage
# This is extremely fast and preserves permissions.
COPY --from=frankenphp_vendor --chown=${USER}:${USER} /app/vendor ./vendor

# Copy the rest of the application source code
COPY --link --chown=${USER}:${USER} . ./
RUN rm -Rf frankenphp/

# Switch to the non-root user
USER ${USER}

# Redis is not reachable during build time. So clear cache will hang.
# So we just put dummy ENV for Redis so it will be bypassed.
# https://github.com/dunglas/symfony-docker/issues/383
ENV REDIS_URL=redis://localhost:1234
ENV REDIS_SYMFONY_DB=999

RUN set -eux; \
    # dump-autoload and other scripts will now work perfectly
    composer dump-autoload --classmap-authoritative --no-dev; \
    composer dump-env prod; \
    composer -vvv run-script --no-dev post-install-cmd; \
    chmod +x bin/console; sync;