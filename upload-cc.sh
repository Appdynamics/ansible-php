#!/usr/bin/env bash

if [ ! -f bin/ocular.phar ]; then
    wget -O bin/ocular.phar https://scrutinizer-ci.com/ocular.phar
fi

php bin/phpunit --coverage-clover=coverage.clover
php bin/ocular.phar code-coverage:upload --format=php-clover coverage.clover
