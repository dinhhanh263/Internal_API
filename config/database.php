<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => env('DB_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        'kireimo_mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST'),
            'port' => '3306',
            'database' => 'kireimo',
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
//            'unix_socket' => env('DB_SOCKET', '/tmp/mysql.sock'),
            'charset' => 'utf8',
            'collation' => 'utf8_general_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
//            'sslmode' => env('DB_SSLMODE', 'prefer'),
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_KEY => env('MYSQL_SSL_CA'),
                PDO::ATTR_PERSISTENT => true,
            ]) : [],
        ],
        'kireimo_stg_mysql' => [
            'driver' => 'mysql',
            'host' => env('AWS_DB_HOST'),
            'port' => '3306',
            'database' => 'kireimo',
            'username' => env('AWS_DB_USERNAME'),
            'password' => env('AWS_DB_PASSWORD'),
            //            'unix_socket' => env('DB_SOCKET', '/tmp/mysql.sock'),
            'charset' => 'utf8',
            'collation' => 'utf8_general_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
            //            'sslmode' => env('DB_SSLMODE', 'prefer'),
            'options' => [
                PDO::MYSQL_ATTR_SSL_CA => env('DB_SSL_AWS'),
                PDO::ATTR_PERSISTENT => true,
            ],
        ],
        'kireimo_prod_mysql' => [
            'driver' => 'mysql',
            'host' => env('AWS_DB_HOST'),
            'port' => '3306',
            'database' => 'kireimo',
            'username' => env('AWS_DB_USERNAME'),
            'password' => env('AWS_DB_PASSWORD'),
            //            'unix_socket' => env('DB_SOCKET', '/tmp/mysql.sock'),
            'charset' => 'utf8',
            'collation' => 'utf8_general_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
            //            'sslmode' => env('DB_SSLMODE', 'prefer'),
            'options' => [
                PDO::MYSQL_ATTR_SSL_CA => env('DB_SSL_AWS'),
                PDO::ATTR_PERSISTENT => true,
            ],
        ],
        'message_mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST'),
            'port' => '3306',
            'database' => 'message',
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            //            'unix_socket' => env('DB_SOCKET', '/tmp/mysql.sock'),
            'charset' => 'utf8',
            'collation' => 'utf8_general_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
            //            'sslmode' => env('DB_SSLMODE', 'prefer'),
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_KEY => env('MYSQL_SSL_CA'),
                PDO::ATTR_PERSISTENT => true,
            ]) : [],
        ],
        'common_mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST'),
            'port' => '3306',
            'database' => 'common',
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            //            'unix_socket' => env('DB_SOCKET', '/tmp/mysql.sock'),
            'charset' => 'utf8',
            'collation' => 'utf8_general_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
            //            'sslmode' => env('DB_SSLMODE', 'prefer'),
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_KEY => env('MYSQL_SSL_CA'),
                PDO::ATTR_PERSISTENT => true,
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'predis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'predis'),
            'prefix' => Str::slug(env('APP_NAME', 'laravel'), '_').'_database_',
        ],

        'default' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0),
        ],

        'cache' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_CACHE_DB', 1),
        ],

    ],

];
