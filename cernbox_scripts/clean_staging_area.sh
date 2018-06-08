#!/bin/sh

/bin/find /var/tmp/systemd-private-*httpd*/tmp/ -type f -cmin +60 -delete

