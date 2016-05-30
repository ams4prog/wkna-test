php -S localhost:8080 -t ../webroot &> /dev/null &
pid="${!}"
phpunit --configuration phpunit.xml
kill "${pid}"