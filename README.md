# OCR My Google Drive

It is a cronjob that scan your Google Drive to find PDF to OCR.

This project uses https://github.com/fritz-hh/OCRmyPDF to make the OCR of PDF.


## Install

### Package
Install java php-cli tesseract and all package from  https://github.com/fritz-hh/OCRmyPDF

### Composer
Install PHP Composer https://getcomposer.org/download/ in src

```
curl -sS https://getcomposer.org/installer | php
php composer.phar install
```

## Init

In Google API Console https://code.google.com/apis/console/ :
* Create a project with an OAuth2 Service Account for Server to Server applications https://developers.google.com/accounts/docs/OAuth2ServiceAccount
* Activate `Drive Api` and `Drive SDK`



You have to launch manually /ocrtodrive.cli.php to initiate the autorization with Google API

## Run
To not launch twice the same job, we use `flock`

Put this command line in your crontab

```
*/3 * * * *	<user>	/usr/bin/flock -n /tmp/ocr.lockfile /usr/bin/php /<path>/ocrmygdrivepdf/src/ocrtodrive.cli.php --gid=XXXX --gsecret=YYYYY --maxsize=10485760 2>&1 | /usr/bin/logger -t ocr
```
Change `gid` by Google API Client ID and `gsecret`by Google API Client Secret, you can find theses keys in Google API Console  https://code.google.com/apis/console/


## Help

You can have help with this command line
> ocrtodrive.cli.php -h

You can have logs into your syslog
