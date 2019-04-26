# Data ![compatible](https://img.shields.io/badge/php-%3E=5.5-green.svg)

## Начало работы

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
    // Подключение кэша не обязательно
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
        'bind'   => $pdo
    ],
    'cache' => [
        'driver' => 'memcache',
        'bind'   => $memcache
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

## SELECT

### Выбор одной строки

```php
// Самый короткий способ, по её id
$row = Base::$db->select('table', 1);

// Массивом, равенство через and
$row = Base::$db->select('table', [
   'id' => 1,
   'login' => $login
]);

// Более сложная логика
$row = Base::$db->select('table', 'id = 1 or login = :login', [
   'login' => $login
]);

// Запросом с дополнительными параметрами
$row = Base::$db->getRow(
    'SELECT 
      * 
    FROM 
      `table` 
    WHERE 
      `type` = :type 
      AND `level` > :level 
    LIMIT 
      1', 
    [
       'type' => $type
       'level/int' => 10,
    ]
);
```

### Получение значения

```php
$count = Base::$db->getValue(
    'SELECT 
      COUNT(*) 
    FROM 
      `table` 
    WHERE 
      `level` > :level', 
    [
       'level' => 10,
    ]
);
```

### Получение списка

```php
$list = Base::$db->getList(
    'SELECT 
      * 
    FROM 
      `table` 
    WHERE 
      `level` > :level', 
    [
       'level' => 10,
    ]
);

// Выполняется 2 запроса
// Первый получает общее количество строк (переделывает запрос)
// Второй сами данные (базовый запрос)
// На выходе массив ['count' => 10, 'items' => [...]]
$data = Base::$db->getListWithCount(
    'SELECT 
      * 
    FROM 
      `table` 
    WHERE 
      `level` > :level', 
    [
       'level' => 10,
    ]
);
```
