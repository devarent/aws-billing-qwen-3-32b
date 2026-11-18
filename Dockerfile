FROM php:8.5-apache

# Set environment untuk multi-platform
ENV DEBIAN_FRONTEND=noninteractive

# Install dependencies sistem (termasuk Python untuk CLI scripts)
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    python3 \
    python3-pip \
    python3-venv \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install ekstensi PHP yang dibutuhkan
# - curl: sudah tersedia di image resmi, tetapi dipastikan aktif
# - redis: untuk budget enforcement (phpredis)
RUN docker-php-ext-install curl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && a2enmod rewrite

# Ekstensi MySQL untuk modul AWS Billing Manager (/billing/).
# Dibuat sebagai layer terpisah agar cache build ekstensi lain tetap dipakai.
RUN docker-php-ext-install pdo_mysql

# Install Python dependencies untuk script CLI (menggunakan venv untuk menghindari externally-managed error)
COPY requirements.txt /tmp/requirements.txt
RUN python3 -m venv /opt/venv && \
    /opt/venv/bin/pip install --no-cache-dir --upgrade pip && \
    /opt/venv/bin/pip install --no-cache-dir -r /tmp/requirements.txt && \
    ln -sf /opt/venv/bin/python3 /usr/local/bin/python3 && \
    ln -sf /opt/venv/bin/pip3 /usr/local/bin/pip3

# Konfigurasi Apache DocumentRoot
WORKDIR /var/www/html

# Salin kode aplikasi (tanpa menyertakan .env berkat .dockerignore)
COPY . /var/www/html/

# Atur permissions untuk web server dan Python scripts
RUN chown -R www-data:www-data /var/www/html && \
    chmod +x /var/www/html/*.py 2>/dev/null || true

# Expose port HTTP
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

CMD ["apache2-foreground"]
