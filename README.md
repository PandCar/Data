# Data ![compatible](https://img.shields.io/badge/php-%3E=5.5-green.svg)

## Начало работы

Подключение кэша не обязательно

### Инициализация через конекты

```php
$result = Data::init([
    'db' => [
        /**
         * Драйвер:
         *   - pdo
         *   - mysqli
         *   - mysql
         */
        'driver' => 'mysqli',
        'connect' => [
            'host'     => 'localhost',
            'user'     => 'Login',
            'password' => '12345',
            'dbname'   => 'site-db',
            'charset'  => 'utf8'
        ]
    ],
    'cache' => [
        /**
         * Драйвер:
         *   - memcached
         *   - memcache
         *   - files (для корректной работы требуется прописать 
         *            в cron уборщик files_scavenger.php, в котором 
         *            в свою очередь указать директорию кэша)
         *   - redis (в будущих версиях)
         */
        'driver' => 'memcached',
        'connect' => [
            'servers' => [
                ['127.0.0.1', 11211]
            ]
        ]
    ]
]);

if (! $result) {
    die( Data::lastError() );
}
```

### Через присоединение к существующим конектам

```php
$result = Data::init([
    'db' => [
        'driver' => 'pdo',
        'bind'   => $pdo
    ],
    'cache' => [
        'driver' => 'memcache',
        'bind'   => $memcache
    ]
]);

if (! $result) {
    die( Data::lastError() );
}
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
    // Время кэша в секундах по умолчанию
    'cache_sec' => 3600
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

### Последняя ошибка

```php
$string = Data::lastError();
```

## Работа с базой данных

Подготовленными запросами можно работать как именованными переменными так и нет (через знак "?")

### Транзакции

```php
Data::$db->beginTransaction();

Data::$db->rollBack();

Data::$db->commit();
```

### Выбор одной строки

```php
// Самый короткий способ, по её id
$row = Data::$db->select('table', 1);

// Массивом, равенство через and
$row = Data::$db->select('table', [
   'id' => 1,
   'login' => $login
]);

// Более сложная логика
$row = Data::$db->select('table', 'id = 1 or login = :login', [
   'login' => $login
]);

// Запросом с дополнительными параметрами
$row = Data::$db->getRow(
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
$count = Data::$db->getValue(
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
$list = Data::$db->getList(
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
$data = Data::$db->getListWithCount(
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

### Обновление 

```php
// Короткий способ, там где id = 5
$bool = Data::$db->update('table', 5, [
    'email' => $new_email,
    'pass'  => $new_pass
]);

// Другие условия
$bool = Data::$db->update('table', [
    'login' => $login
], [
    'email' => $new_email,
    'pass' => $new_pass
]);

// Запросом
$bool = Data::$db->exec(
    'UPDATE 
      `table` 
    SET 
      `email` = :email, 
      `pass` = :pass 
    WHERE 
      `login` = :login', 
    [
        'email' => $new_email,
        'pass'  => $new_pass,
        'login' => $login
    ]
);
```

### Добавление

```php
// Короткий способ
$insert_id = Data::$db->insert('table', [
    'login' => $login,
    'pass'  => $pass,
    'email' => $email
]);

// Запросом
$bool = Data::$db->exec(
    'INSERT INTO 
      `table` (
        `login`, `pass`, `email`
      ) 
    VALUES 
      (:login, :pass, :email)', 
    [
        'login' => $login,
        'pass' => $pass,
        'email' => $email
    ]
);

if ($bool) {
    $insert_id = Data::$db->getInsertID();
}

``` 

### Удаление

```php
// Тот же способ выбора как и у select...
// Короткий способ, там где id = 5
$bool = Data::$db->remove('table', 'level > :level', [
   'level' => $level
]);

// Запросом
$bool = Data::$db->exec(
    'DELETE FROM 
      `table` 
    WHERE 
      `level` > :level', 
    [
        'level' => $level
    ]
);
```

## Работа с кэшем

### Базовый функционал

```php
// Запись
$bool = Data::$cache->set($key, $value, $exp, $en_json);

// Чтение
$value = Data::$cache->get($key, $un_json, $extended_info);

// Удаление
$bool = Data::$cache->del($key);
```

### Версии

Используются для сброса большого количества ключей с параметрами

```php
$key = [$version_key, $key, [$param1, $param2]];

// Запись
$bool = Data::$cache->set($key, $value, $exp, $en_json);
```

### Кэш запросов к базе

```php
// По обычному ключу
$list = Data::$db->getList(
    'SELECT 
      * 
    FROM 
      `table` 
    WHERE 
      `level` > :level', 
    [
       'level/int' => $level,
    ],
    [
        'debug' => true,
        'cache' => true,
        'cache_key' => 'get_level_more_'.$level,
        // Необязательный параметр, по умолчанию 3 часа
        'cache_sec' => 3600
    ]
);

// По ключу с версией
$list = Data::$db->getList(
    'SELECT 
      * 
    FROM 
      `table` 
    WHERE 
      `level` > :level', 
    [
       'level/int' => $level,
    ],
    [
        'debug' => true,
        'cache' => true,
        'cache_key' => [$version_key, 'get_level_more', [$level]],
    ]
);
```


