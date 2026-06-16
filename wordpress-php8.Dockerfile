FROM php:8.2-apache

USER root
RUN ln -sf /bin/bash /bin/sh
RUN touch /root/.vimrc && echo 'set nocompatible' >> /root/.vimrc

# basic environment install.
RUN apt-get update --allow-releaseinfo-change -qq \
  && apt-get install -y default-mysql-client --no-install-recommends \
  && apt-get install -y vim-tiny unzip \
  && apt-get install -y sudo ca-certificates curl gnupg \
  && apt-get install -y libfreetype6-dev libjpeg62-turbo-dev libpng-dev libxml2-dev libzip-dev

# Install most PHP dependencies
RUN docker-php-ext-install mysqli pdo pdo_mysql soap zip \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) gd

# Install PHP redis support
RUN apt-get install -y redis-server redis-tools libzstd-dev
RUN pecl install igbinary \
  && docker-php-ext-enable igbinary \
  && yes '' | pecl install redis \
  && docker-php-ext-enable redis

# Set up a development php.ini
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
RUN printf "<VirtualHost *:80> \
\n\tDocumentRoot \"/var/www/html\" \
\n\tServerName spudworks.localhost \
\n</VirtualHost>" > /etc/apache2/sites-available/000-default.conf

RUN printf "memory_limit = 64M \
  \nupload_max_filesize = 64M \
  \npost_max_size = 64M \
  \nmax_execution_time = 60" >> /usr/local/etc/php/conf.d/memory_config.ini

# Enable Apache mod_rewrite
WORKDIR /etc/apache2/mods-enabled
RUN ln -s ../mods-available/rewrite.load rewrite.load
RUN ln -s ../mods-available/headers.load headers.load

# Install XDebug Support
RUN pecl install xdebug \
  && docker-php-ext-enable xdebug

RUN printf "[xdebug] \
\nxdebug.mode=develop,debug \
\nxdebug.client_host=host.docker.internal \
\nxdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

RUN printf "error_reporting=E_ALL" > /usr/local/etc/php/conf.d/error_reporting.ini

# Set up Gulp to process SCSS
RUN curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
  && apt-get install -y nodejs \
  && node --version \
  && npm --version

RUN mkdir -p /usr/src/spudworks
COPY ./build/package.json /usr/src/spudworks
COPY ./build/local.json /usr/src/spudworks
COPY ./build/gulpfile.js /usr/src/spudworks
COPY ./build/wp-config.php /var/www/html/
COPY ./build/htaccess /var/www/html/.htaccess
WORKDIR /usr/src/spudworks

RUN npm install
RUN npm install -g gulp-cli

RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
  && chmod +x wp-cli.phar \
  && mv wp-cli.phar /usr/local/bin/wp

ENV WORDPRESS_VERSION="7.0"
RUN curl -fsSLO --compressed "https://wordpress.org/wordpress-${WORDPRESS_VERSION}.tar.gz" \
  && tar -xzf "wordpress-${WORDPRESS_VERSION}.tar.gz" -C /var/www/html --strip-components=1 --no-same-owner \
  && curl -fsSLO --compressed "https://downloads.wordpress.org/plugin/classic-editor.1.7.0.zip" \
  && unzip classic-editor.1.7.0.zip -d /var/www/html/wp-content/plugins \
  && curl -fsSLO --compressed "https://downloads.wordpress.org/plugin/classic-widgets.0.3.zip" \
  && unzip classic-widgets.0.3.zip -d /var/www/html/wp-content/plugins \
  # && curl -fsSLO --compressed "https://downloads.wordpress.org/plugin/multiple-post-thumbnails.1.7.zip" \
  # && unzip multiple-post-thumbnails.1.7.zip -d /var/www/html/wp-content/plugins \
  && curl -fsSLO --compressed "https://downloads.wordpress.org/plugin/regenerate-thumbnails.3.1.6.zip" \
  && unzip regenerate-thumbnails.3.1.6.zip -d /var/www/html/wp-content/plugins \
  && curl -fsSLO --compressed "https://downloads.wordpress.org/plugin/redis-cache.2.5.4.zip" \
  && unzip redis-cache.2.5.4.zip -d /var/www/html/wp-content/plugins \
  && rm *.zip && rm -Rf /var/www/html/wp-content/plugins/akismet && rm /var/www/html/wp-content/plugins/hello.php \
  && rm "/usr/src/spudworks/wordpress-${WORDPRESS_VERSION}.tar.gz"

RUN echo "%www-data  ALL=(ALL) NOPASSWD: ALL" >> /etc/sudoers
RUN useradd -ms /bin/sh -g www-data -G sudo www-user
RUN touch /root/.vimrc && echo 'set nocompatible' >> /root/.vimrc
RUN touch /home/www-user/.vimrc && echo 'set nocompatible' >> /home/www-user/.vimrc

RUN /usr/local/bin/php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
  && /usr/local/bin/php composer-setup.php \
  && /usr/local/bin/php -r "unlink('composer-setup.php');" \
  && mv composer.phar /usr/local/bin/composer

ENTRYPOINT ["/bin/bash", "-c", "bash \
  && service redis-server start \
  && sudo chown -R www-user:www-data /var/www/html \
  && sudo chown -R www-user:www-data /usr/src/spudworks \
  && cd /usr/src/spudworks \
  && gulp build >> /dev/stdout && \
  eval 'gulp watch >> /dev/stdout &'; sleep 5; apachectl stop; apachectl -D FOREGROUND"]