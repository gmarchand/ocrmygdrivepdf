# OCR My Google Drive

It is a cronjob that scan your Google Drive to find PDF to OCR.

This project uses https://github.com/fritz-hh/OCRmyPDF to make the OCR of PDF.


## Install

1. Init
You have to launch manually ocrtodrive.cli.php to initiate the autorization with Google API

2. Cronjob

To not launch twice the same job, we use flock

Put this script in your crontab
`/usr/bin/flock -n /tmp/ocr.lockfile php ocrtodrive.cli.php --gid=<XXXX> --gsecret=<YYYY>
