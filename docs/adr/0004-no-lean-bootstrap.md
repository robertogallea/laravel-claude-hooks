# No lean bootstrap for Hook Commands

Measured on a Laravel 13.37 app on PHP 8.5.9: a Hook Command takes about 130 ms, the Composer autoloader alone takes 40 ms, `artisan optimize` changes nothing, and OPcache with `opcache.file_cache` brings the command to about 70 ms (`opcache.enable_cli` alone makes it slower, about 290 ms). A lean bootstrap could save at most about 90 ms, and only by dropping the container, config and database, which are the reason to write a hook in Laravel at all. The package documents `opcache.file_cache`, the `if` filter and the `http` transport instead.
