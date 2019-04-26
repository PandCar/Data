# Data ![compatible](https://img.shields.io/badge/php-%3E=5.5-green.svg)

## Начало работы

Подключение кэша не обязательно

### Инициализация через конекты

```php
Data::init([
	'db' => [
		// Драйвер (доступны: pdo, mysqli, mysql)
		'driver' => 'mysql',
		'connect' => [
			'host'     => 'localhost',
			'user'     => 'Login',
			'password' => '12345',
			'namedb'   => 'site-db',
			'charset'  => 'utf8'
		]
	],
	'cache' => [
		// Драйвер (доступны: memcached, memcache, redis в будущих версиях)
		'driver' => 'memcached',
		'connect' => [
			'servers' => [
				['127.0.0.1', 11211]
			]
		]
	]
]);
```

### Через присоединение к существующим конектам

```php
Data::init([
	'db' => [
		'driver' => 'pdo',
		'bind' => $pdo
	],
	'cache' => [
		'driver' => 'memcache',
		'bind' => $memcache
	]
]);
```

### Настройки

```php
// Массивом
Data::set([
	// Включение вывода всех отчётов
	'debug' => true,
	// Выбрасывать ли исключения при ошибках
	'throw_exception' => false,
	// Вторичный кэш внутри приложения
	'app_cache' => true,
]);

// Так тоже можно использовать
Data::set('debug', true);

// Как один из параметров при инициализации
Data::init([
	...,
	'set' => [
		...
	]
]);
```
