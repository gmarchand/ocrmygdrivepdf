# OCR My Google Drive

It is a cronjob that scan your Google Drive to find PDF to OCR.

This project uses https://github.com/fritz-hh/OCRmyPDF to make the OCR of PDF.


## Install

1. Package

Install java php-cli tesseract and https://github.com/fritz-hh/OCRmyPDF prerequis

1. Composer

curl -sS https://getcomposer.org/installer | php
php composer.phar install

1. Init
You have to launch manually ocrtodrive.cli.php to initiate the autorization with Google API

2. Cronjob

To not launch twice the same job, we use flock

Put this script in your crontab
`*/3 * * * *	gmarchand	/usr/bin/flock -n /tmp/ocr.lockfile /usr/bin/php /home/gmarchand/projet/ocrmygdrivepdf/src/ocrtodrive.cli.php --gid=XXXX --gsecret=YYYYY --maxsize=10485760 2>&1 | /usr/bin/logger -t ocr
