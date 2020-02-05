#!/bin/bash

NGINX_PHP_NETWORK="ProjectX"
DOMAIN="https://d64.nl"

comment()
{
	echo ""
	echo "================================================================================"
	echo $1
	echo "================================================================================"
	echo ""
}

#####
# Install curl
#####

sudo apt install curl minify uglifyjs -y

#####
# Install/Update docker
#####

comment "Docker install / update"
#curl -fsSL get.docker.com -o get-docker.sh && sh ./get-docker.sh
rm get-docker.sh

# Create docker network

sudo docker network create $NGINX_PHP_NETWORK --subnet=172.18.0.0/16 --ipv6 --subnet=fcde::/48

#####
# Genereer nieuws cache voor de laatste 10 items
####

comment "Create nieuws cache"
rm -R webroot/cache
mkdir webroot/cache
find webroot/blog -type f ! -name "*.*" -not -name "content" | sed 's/webroot//' | sort -r | tail -n 10 | xargs -L 1 echo >> webroot/cache/last_10.txt

#####
# Push the title and first alinea of the last 10 news files into a cache file
#####

find webroot/blog -type f ! -name "*.*" -not -name "content" | sort | tail -n 10 | tee -a webroot/cache/news.txt | xargs cut -d$'\n' -f7 >> webroot/cache/news.txt

#####
# Make RAM disk
#####

sudo umount ./ramdisk/webroot
sudo umount ./ramdisk/nginx
rm -R ramdisk
mkdir ramdisk
mkdir ramdisk/webroot
mkdir ramdisk/nginx
sudo mount -t tmpfs -o rw,size=10M tmpfs ./ramdisk/webroot
sudo mount -t tmpfs -o rw,size=30M tmpfs ./ramdisk/nginx

cp -R webroot/includes ramdisk/webroot/
#cp -R webroot/css ramdisk/webroot/
mkdir ramdisk/webroot/css
cat webroot/css/w3.css | minify --type=css > ramdisk/webroot/css/w3.css
#cp -R webroot/js ramdisk/webroot/
mkdir ramdisk/webroot/js
cat webroot/js/d64.js | minify --type=js > ramdisk/webroot/js/d64.js
uglifyjs ramdisk/webroot/js/d64.js -o ramdisk/webroot/js/d64.js
cp -R webroot/cache ramdisk/webroot/
cp -R webroot/blog ramdisk/webroot/
cp -R webroot/img ramdisk/webroot/

comment "Copy latest btc.json"
cp btc/btc.json ramdisk/webroot/cache/
cp webroot/google2d6267b3d2d2287f.html ramdisk/webroot/
cp webroot/favicon.ico ramdisk/webroot/
cp webroot/robots.txt ramdisk/webroot/
cp webroot/manifest.json ramdisk/webroot/
cp webroot/index.php ramdisk/webroot/
#cp webroot/service-worker.js ramdisk/webroot/
cat webroot/sw.js | minify --type=js > ramdisk/webroot/sw.js
cp config/d64.conf.json ramdisk/webroot/includes/

#####
# PHP container
#####

comment "Gracefully stopping PHP container"
sudo docker stop PHP
comment "Remove PHP container"
sudo docker rm PHP

comment "Init PHP container just to get the php.ini-production"
sudo docker run --name PHP -d php:fpm-alpine

comment "Copy out the php.ini file"
sudo docker cp PHP:/usr/local/etc/php/php.ini-production config/php.ini

comment "Setting expose_php & file_uploads to off"
sed -i 's/expose_php = On/expose_php = Off/g' config/php.ini
sed -i 's/file_uploads = On/file_uploads = Off/g' config/php.ini

comment "Gracefully stopping PHP container"
sudo docker stop PHP
comment "Remove PHP container"
sudo docker rm PHP

sudo docker run \
	--name PHP \
	--restart unless-stopped \
	--network $NGINX_PHP_NETWORK \
	-p 9000:9000/tcp \
	-v $(pwd)/ramdisk/webroot:/var/www/html:ro \
	-v $(pwd)/config/php.ini:/usr/local/etc/php/php.ini:ro \
	-d \
	php:fpm-alpine

#####
# PHP Socket container
#####

sudo docker build -t socket .

sudo docker stop Socket
sudo docker rm Socket

sudo docker run \
        -d \
        --rm \
        --name Socket \
        --network ProjectX \
        --ip 172.18.0.52 \
        -v $(pwd):/usr/src/myapp \
        -w /usr/src/myapp \
        socket \

sudo docker cp PHP:/usr/local/etc/php/php.ini-production config/php-cli.ini

sudo docker stop Socket

comment "Experimental"
sed -i 's/;opcache.enable=1/opcache.enable=1/g' config/php-cli.ini
sed -i 's/;opcache.enable_cli=0/opcache.enable_cli=1/g' config/php-cli.ini

sudo docker run \
        --restart unless-stopped \
        --name Socket \
        --network ProjectX \
        --ip 172.18.0.52 \
        -p 8080:8080/tcp \
        -v $(pwd)/config/php-cli.ini:/usr/local/etc/php/php.ini:ro \
        -v $(pwd):/usr/src/myapp \
        -w /usr/src/myapp \
	-d \
        socket \
        php ramdisk/webroot/index.php

#####
# Nginx container
#####

comment "Gracefully stopping Nginx container"
sudo docker stop Nginx
comment "Remove Nginx container"
sudo docker rm Nginx

comment "Generate new nginx.conf"
cp config/nginx.conf-default config/nginx.conf

mkdir log

comment "Init Nginx container"
sudo docker run \
	--name Nginx \
	--restart unless-stopped \
	-e TZ=Europe/Amsterdam \
	--network $NGINX_PHP_NETWORK \
	--ip 172.18.0.50 \
	--ip6 fcde::4 \
	-p 80:80/tcp \
	-p 443:443/tcp \
	-v $(pwd)/ramdisk/webroot:/var/www/html:ro \
	-v $(pwd)/config/nginx.conf:/etc/nginx/nginx.conf:ro \
	-v $(pwd)/ramdisk/nginx:/var/cache/nginx \
	-v $(pwd)/log:/var/log/nginx \
	-v $(pwd)/letsencrypt/etc/live/d64.nl/fullchain.pem:/etc/letsencrypt/live/d64.nl/fullchain.pem \
	-v $(pwd)/letsencrypt/etc/live/d64.nl/privkey.pem:/etc/letsencrypt/live/d64.nl/privkey.pem \
	-v $(pwd)/dhparam-2048.pem:/etc/ssl/certs/dhparam-2048.pem \
	-d nginx:alpine

#####
# Clean-up
#####

comment "Prune images"
yes | sudo docker image prune

comment "Done, are they running?"
sudo docker ps

echo ""
echo "Dumb cli test"
echo ""

sudo docker run \
        -it \
        --rm \
        --name PHP-cli \
        -v $(pwd):/usr/src/myapp \
        -w /usr/src/myapp \
        php:cli-alpine \
        php create_nav.php

echo ""
echo ""

echo "Request tests"
echo ""

passed(){
  echo -e "\e[92mPASSED\e[39m" $1
}

failed(){
  echo -e "\e[91mFAILED\e[39m" $1
}

if (( $( curl -Is $DOMAIN | grep -c "HTTP/2 200" ) > 0)) ; then passed "200 /"; else failed "/"; fi
if (( $( curl -Is $DOMAIN/blog | grep -c "HTTP/2 301" ) > 0)) ; then passed "301 /blog"; else failed "/blog"; fi
if (( $( curl -Is $DOMAIN/blog/ | grep -c "HTTP/2 200" ) > 0)) ; then passed "200 /blog/"; else failed "/blog/"; fi
if (( $( curl -Is $DOMAIN/sport | grep -c "HTTP/2 301" ) > 0)) ; then passed "301 /sport"; else failed "/sport"; fi
if (( $( curl -Is $DOMAIN/sport/ | grep -c "HTTP/2 200" ) > 0)) ; then passed "200 /sport/"; else failed "/sport/"; fi
if (( $( curl -Is $DOMAIN/sport/tennis | grep -c "HTTP/2 301" ) > 0)) ; then passed "301 /sport/tennis"; else failed "/sport/tennis"; fi
if (( $( curl -Is $DOMAIN/sport/tennis\?bla | grep -c "HTTP/2 301" ) > 0)) ; then passed "301 /sport/tennis?bla"; else failed "/sport/tennis?bla"; fi
if (( $( curl -Is $DOMAIN/sport/tennis/\?bla | grep -c "HTTP/2 200" ) > 0)) ; then passed "200 /sport/tennis/?bla"; else failed "/sport/tennis/?bla"; fi
if (( $( curl -Is $DOMAIN/?404 | grep -c "HTTP/2 404" ) > 0)) ; then passed "404 /?404"; else failed "/?404"; fi
if (( $( curl -Is $DOMAIN/index.php?404 | grep -c "HTTP/2 404" ) > 0)) ; then passed "404 /index.php?404"; else failed "/index.php?404"; fi
if (( $( curl -Is $DOMAIN/index.php | grep -c "HTTP/2 404" ) > 0)) ; then passed "404 /index.php"; else failed "/index.php"; fi
if (( $( curl -Is $DOMAIN/includes | grep -c "HTTP/2 404" ) > 0)) ; then passed "404 /includes"; else failed "/includes"; fi
if (( $( curl -Is $DOMAIN/includes/ | grep -c "HTTP/2 404" ) > 0)) ; then passed "404 /includes/"; else failed "/includes/"; fi

echo ""

sudo docker logs -f Socket
