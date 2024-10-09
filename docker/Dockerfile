################################################################
# S.O 
################################################################
FROM ubuntu:20.04
RUN apt-get update && \
    apt-get install -y tzdata && \
    apt-get install -y gnupg2 && \
    apt-get install -y unixodbc && \
    apt-get install -y unixodbc-dev && \ 
    apt-get install -y git-all && \
    apt-get install -y unzip && \ 
    apt-get install -y curl && \ 
    apt-get install -y iputils-ping && \
    apt-get install -y software-properties-common && \
    rm -rf /var/lib/apt/lists/*RUN apt-get update && \
    apt-get install -y software-properties-common && \
    rm -rf /var/lib/apt/lists/*
################################################################
# PHP 
################################################################
RUN add-apt-repository ppa:ondrej/php -y
RUN apt-get update && \
    apt-get install -y --allow-unauthenticated php && \
    apt-get install -y --allow-unauthenticated php-cli && \
    apt-get install -y --allow-unauthenticated php-dev && \
    apt-get install -y --allow-unauthenticated php-json && \
    apt-get install -y --allow-unauthenticated php-mysql && \
    apt-get install -y --allow-unauthenticated php-zip && \
    apt-get install -y --allow-unauthenticated php-gd && \
    apt-get install -y --allow-unauthenticated php-mbstring && \
    apt-get install -y --allow-unauthenticated php-curl && \
    apt-get install -y --allow-unauthenticated php-xml && \
    apt-get install -y --allow-unauthenticated php-pear && \
    apt-get install -y --allow-unauthenticated libapache2-mod-php && \
    apt-get install -y --allow-unauthenticated php-bcmath
################################################################
# COMPOSER
################################################################
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer
################################################################
# CRONTAB
################################################################
RUN apt-get install cron
ADD docker/crontab /etc/cron.d/docker-crontab
RUN chmod 0644 /etc/cron.d/docker-crontab
RUN service cron start
################################################################
# APACHE
################################################################
RUN apt-get update
RUN apt-get install -y --allow-unauthenticated libapache2-mod-php 
RUN apt-get install -y --allow-unauthenticated apache2
RUN a2dismod mpm_event
RUN a2enmod mpm_prefork
RUN a2enmod rewrite
ENV APACHE_RUN_USER www-data
ENV APACHE_RUN_GROUP www-data
ENV APACHE_LOG_DIR /var/log/apache2
ENV APACHE_LOCK_DIR /var/lock/apache2
ENV APACHE_PID_FILE /var/run/apache2.pid
ADD docker/apache/apache2.conf /etc/apache2/apache2.conf
ADD docker/apache/apache-virtual-hosts.conf /etc/apache2/sites-enabled/000-default.conf
##RUN a2enmod php8.0
################################################################
################################################################
# ADD THE DIRECTORY
################################################################
RUN mkdir -p /var/www/app
WORKDIR /var/www/app
################################################################
# BY DEFAULT START UP APACHE IN THE FOREGROUND
# OVERRIDE WITH /BIN/BASH FOR INTERATIVE.
################################################################
CMD /usr/sbin/apache2ctl -D FOREGROUND

# Modificar el archivo /etc/ssl/openssl.cnf
RUN echo "openssl_conf = default_conf" | cat - /etc/ssl/openssl.cnf > temp && mv temp /etc/ssl/openssl.cnf && \
    echo "\n[default_conf]\nssl_conf = ssl_sect\n\n[ssl_sect]\nsystem_default = system_default_sect\n\n[system_default_sect]\nMinProtocol = TLSv1\nCipherString = DEFAULT@SECLEVEL=1" >> /etc/ssl/openssl.cnf

# Reiniciar apache2
RUN service apache2 restart