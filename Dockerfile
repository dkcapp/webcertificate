FROM php:8.4-apache

# ติดตั้ง library ที่จำเป็นสำหรับต่อ PostgreSQL (Neon)
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ตั้งค่าความปลอดภัยของ PHP (ดู docker/php-security.ini)
COPY docker/php-security.ini /usr/local/etc/php/conf.d/zz-security.ini

# เปิดใช้ mod_rewrite (เผื่อใช้ .htaccess ในอนาคต)
RUN a2enmod rewrite

# คัดลอกโค้ดทั้งโปรเจกต์เข้า Apache document root
COPY . /var/www/html/
RUN rm -rf /var/www/html/docker

# ให้ Apache ฟังพอร์ตที่ Render กำหนดผ่าน ENV PORT (Render จะ inject ตัวแปรนี้มาให้ตอน runtime)
RUN echo 'Listen ${PORT}' > /etc/apache2/ports.conf && \
    sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf

ENV PORT=10000
EXPOSE 10000

CMD ["apache2-foreground"]