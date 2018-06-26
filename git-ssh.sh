#!/bin/sh
exec ssh -i /var/www/.ssh/id_rsa "$@"