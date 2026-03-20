#!/usr/bin/env bash
# Wrapper de sail para ejecutar comandos dentro del contenedor Docker
# Uso: ./sail artisan ..., ./sail composer ..., ./sail npm ...

if [ "$1" == "up" ]; then
    WWWGROUP=1000 WWWUSER=1000 docker compose up "${@:2}"
elif [ "$1" == "down" ]; then
    docker compose down "${@:2}"
elif [ "$1" == "artisan" ]; then
    docker compose exec laravel.test php artisan "${@:2}"
elif [ "$1" == "php" ]; then
    docker compose exec laravel.test php "${@:2}"
elif [ "$1" == "composer" ]; then
    docker compose exec laravel.test composer "${@:2}"
elif [ "$1" == "npm" ]; then
    docker compose exec laravel.test npm "${@:2}"
elif [ "$1" == "node" ]; then
    docker compose exec laravel.test node "${@:2}"
elif [ "$1" == "mysql" ]; then
    docker compose exec mysql mysql -u sail -ppassword "${@:2}"
elif [ "$1" == "test" ]; then
    docker compose exec laravel.test php artisan test "${@:2}"
else
    docker compose exec laravel.test "$@"
fi
