# API Rest SinapsisVet


Provides services for SinapsisVet

## Authors

- [@ezemanzano]([ezemanzano (Ezequiel Manzano) · GitHub](https://github.com/ezemanzano/))

## Deployment

To deploy this project run

```bash
  cp app/.env-example app/.env
```

To deploy this project run

```bash
  cp docker-compose.example.yml docker-compose.yml
```

To deploy this project run

```bash
  sudo docker-compose up -d
```

To deploy this project run

```bash


docker exec -it api-rest-Compras
-apirest-laravel /bin/bash
```

```bash
$ chown -R www-data /var/www/app
$ composer install
```

```bash
composer install
```

## Documentation

[Documentation](documentation.md)

## Environment Variables

To run this project, you will need to add the following environment variables to your .env file

| Name       | Default Value | Description |
|------------|---------------|-------------|
| APP_ENV    | local         |             |
| APP_DEBUG  | true          |             |
| DB_HOST    |               |             |
| DB_NAME    |               |             |
| DB_USER    |               |             |
| DB_PASS    |               |             |
| DB_CHARSET | utf8          |             |

## Helps

Si al ejecutar **docker-compose up -d**  retorna el siguiente error referido a **moby/buildkit:buildx**.

```bash
DOCKER_BUILDKIT=0 docker-compose up -d
```

En caso de ver este mensaje: Your Composer dependencies require a PHP version ">= 8.1.0".
Ejecutamos dentro del contenedor.

```bash
add-apt-repository ppa:ondrej/php
apt update
apt install php8.1
```

```bash
$ a2dismod php8.0
```

```bash
$ a2enmod php8.1 
```

```bash
$ apt-get install php8.1-curl
```

```bash
$ apt-get install php8.1-xml
```

En caso de tener problemas con los drivers de SQL Server.
Ejecutar dentro del contenedor.

```bash
$ apt-get install freetds-common freetds-bin unixodbc php8.1-sybase
```

## Package External


## Support

For support, email team@libreopcion.com or join our Slack channel.

#
