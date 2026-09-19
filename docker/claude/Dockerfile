FROM node:22-bookworm-slim
RUN apt-get update \
 && apt-get install -y --no-install-recommends git ca-certificates curl \
 && rm -rf /var/lib/apt/lists/*
# PHP 8.3 CLI to run bin/<site> console commands, phpunit, phpstan, php-cs-fixer directly.
# Bookworm ships PHP 8.2, so use deb.sury.org (same maintainer as the ppa:ondrej/php the
# php container uses — setup per https://packages.sury.org/php/README.txt).
# Extensions mirror the php container (Dockerfile at repo root), minus fpm.
RUN curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb \
 && dpkg -i /tmp/debsuryorg-archive-keyring.deb \
 && rm /tmp/debsuryorg-archive-keyring.deb \
 && echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ bookworm main" > /etc/apt/sources.list.d/php.list \
 && apt-get update \
 && apt-get install -y --no-install-recommends \
        php8.3-cli \
        php8.3-bcmath \
        php8.3-curl \
        php8.3-gd \
        php8.3-imagick \
        php8.3-intl \
        php8.3-mbstring \
        php8.3-pgsql \
        php8.3-xml \
        php8.3-zip \
 && rm -rf /var/lib/apt/lists/*
RUN echo 'date.timezone=Europe/Brussels' >> /etc/php/8.3/cli/conf.d/99-tcu.ini \
 && echo 'memory_limit=1024M' >> /etc/php/8.3/cli/conf.d/99-tcu.ini
# psql for direct DB inspection + tools for the egress firewall below
RUN apt-get update \
 && apt-get install -y --no-install-recommends postgresql-client iptables ipset dnsutils jq iproute2 sudo \
 && rm -rf /var/lib/apt/lists/*
# Egress allowlist firewall (see init-firewall.sh): applied at container start
# by entrypoint.sh. Needs root + NET_ADMIN (granted in docker-compose.yaml);
# node gets passwordless sudo for this single root-owned script, nothing else.
COPY init-firewall.sh entrypoint.sh /usr/local/bin/
RUN chmod 755 /usr/local/bin/init-firewall.sh /usr/local/bin/entrypoint.sh \
 && echo "node ALL=(root) NOPASSWD: /usr/local/bin/init-firewall.sh" > /etc/sudoers.d/node-firewall \
 && chmod 440 /etc/sudoers.d/node-firewall
# intelephense = the PHP language server behind the official php-lsp
# Claude Code plugin (plugin itself is installed user-scope, lives in
# the claude-config volume; only the binary needs to be in the image)
RUN npm install -g @anthropic-ai/claude-code intelephense
# create /home/node directory **inside** the container
RUN mkdir -p /home/node/.claude && chown -R node:node /home/node/.claude
USER node
WORKDIR /data/www
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["claude"]