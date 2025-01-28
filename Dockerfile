FROM ubuntu:22.04

# Install the necessary dependencies for wkhtmltopdf and PHP
RUN apt-get update && apt-get install -y \
    libc-client-dev \
    libkrb5-dev \
    wget \
    fontconfig \
    libxrender1 \
    libxext6 \
    libssl-dev \
    libjpeg-turbo8-dev \
    libpng-dev \
    libfreetype6 \
    curl \
    ca-certificates \
    lsb-release \
    gnupg2 \
    software-properties-common \
    && rm -rf /var/lib/apt/lists/*

# Add the PHP 8.2 repository
RUN add-apt-repository ppa:ondrej/php -y \
    && apt-get update

# Install PHP 8.2 and the necessary extensions
RUN apt-get install -y \
    php8.2-cli \
    php8.2-curl \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-imap \
    php8.2-zip \
    php8.2-fpm \
    php8.2-mysql \
    php8.2-common \
    && rm -rf /var/lib/apt/lists/*

# Install wkhtmltopdf from the official Ubuntu 22.04 repositories
RUN apt-get update && apt-get install -y wkhtmltopdf
RUN ln -s /usr/bin/wkhtmltopdf /usr/local/bin/wkhtmltopdf

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set the working directory
WORKDIR /usr/local/app

# Copy the source code
COPY . .

# Install the Composer dependencies
RUN composer install
