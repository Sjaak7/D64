sudo docker run -it --rm \
	-v $(pwd)/letsencrypt/etc:/etc/letsencrypt \
	-v $(pwd)/letsencrypt/lib:/var/lib/letsencrypt \
	-v $(pwd)/ramdisk/webroot:/data/letsencrypt \
	-v $(pwd)/letsencrypt/log:/var/log/letsencrypt \
	certbot/certbot \
	certonly --webroot \
	--register-unsafely-without-email --agree-tos \
	--webroot-path=/data/letsencrypt \
	-d d64.nl -d www.d64.nl
