<?php

/**
 * @author Oleg Isaev (PandCar)
 * @contacts vk.com/id50416641, t.me/pandcar, github.com/pandcar
 * @version 1.0.0
 */

/**
 * Class DataException
 */
class DataException extends Exception { }

/**
 * Class Data
 */
class Data
{
    public static $db,
        $cache;
    
    protected static $debug = false,
        $throw_exception = false,
        $app_cache = false,
        $cache_sec = 10800,
        $set_white = [
            'debug', 
            'throw_exception', 
            'app_cache', 
            'cache_sec'
        ],
        $report_replace = [
            '_DataUsingDB'    => 'Data::$db',
            '_DataUsingCache' => 'Data::$cache',
        ],
        $drivers_db = [
            'pdo'    => '_DataDBDriverPDO',
            'mysqli' => '_DataDBDriverMySQLi',
            'mysql'  => '_DataDBDriverMySQL'
        ],
        $drivers_cache = [
            'memcached' => '_DataCacheDriverMemcached',
            'memcache'  => '_DataCacheDriverMemcache'
        ];

    /**
     * @param array $opt
     * @param null $value
     */
    public static function set($opt = [], $value = null)
    {
        if (! is_array($opt)) {
            $opt = [
                $opt => $value
            ];
        }
        
        $white = self::getSetting('set_white');
        
        foreach ($opt as $key => $value)
        {
            if (isset(self::${$key}) && in_array($key, $white)) {
                self::${$key} = $value;
            }
        }
    }

    /**
     * @param array $opt
     * @return bool
     * @throws DataException
     */
    public static function init($opt = [])
    {
        $debug = ! empty($opt['debug']);
        
        if (! empty($opt['set'])) {
            self::set($opt['set']);
        }
        
        try {
            if (self::$db || self::$cache) {
                throw new \DataException('Data уже был инициирован');
            }
            
            if (! empty($opt['cache']))
            {
                if (! isset($opt['cache']['driver'])) {
                    throw new \DataException('Драйвер кэша не указан');
                }
                
                if (! isset(self::$drivers_cache[ $opt['cache']['driver'] ])) {
                    throw new \DataException('Не указан поддерживаемый драйвер кэша');
                }
                
                $cache = self::getDriver('cache', $opt['cache']['driver']);
                
                if (isset($opt['cache']['connect']))
                {
                    $cache->connect = $cache->connect($opt['cache']['connect']);
                    
                    _DataReport::view('Подключились к кэшу через '.$opt['cache']['driver'], 1, $debug);
                }
                elseif (isset($opt['cache']['bind']))
                {
                    $cache->connect = $opt['cache']['bind'];
                    
                    _DataReport::view('Привязались к кэшу через '.$opt['cache']['driver'], 1, $debug);
                }
                else {
                    throw new \DataException('Ни один из обязательных параметров не передан');
                }
                
                self::$cache = new _DataUsingCache($cache);
            }
            
            if (! isset($opt['db']['driver'])) {
                throw new \DataException('Драйвер базы данных не указан');
            }
            
            if (! isset(self::$drivers_db[ $opt['db']['driver'] ])) {
                throw new \DataException('Не указан поддерживаемый драйвер базы данных');
            }
            
            $db = self::getDriver('db', $opt['db']['driver']);
            
            if (isset($opt['db']['connect']))
            {
                $db->connect = $db->connect($opt['db']['connect']);
                
                _DataReport::view('Подключились к бд через '.$opt['db']['driver'], 1, $debug);
            }
            elseif (isset($opt['db']['bind']))
            {
                $db->connect = $opt['db']['bind'];
                
                _DataReport::view('Привязались к бд через '.$opt['db']['driver'], 1, $debug);
            }
            else {
                throw new \DataException('Ни один из обязательных параметров не передан');
            }
            
            self::$db = new _DataUsingDB($db, (! empty(self::$cache) ? self::$cache : null));
        }
        catch (\DataException $e)
        {
            _DataReport::view('Ошибка инициализации: '.$e->getMessage(), 3, $debug);
            
            return false;
        }
        
        return true;
    }
    
    /**
     * @return string
     */
    public static function lastError()
    {
        return _DataReport::$last_error;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public static function getSetting($key)
    {
        if (! isset(self::${$key})) {
            return false;
        }
        
        return self::${$key};
    }

    /**
     * @param $type
     * @param $driver
     * @return bool
     */
    protected static function getDriver($type, $driver)
    {
        if (! isset(self::${'drivers_'.$type}[ $driver ])) {
            return false;
        }
        
        return new self::${'drivers_'.$type}[ $driver ]();
    }
}

/**
 * Class _DataReport
 */
class _DataReport
{
    public static $last_error = '';
    
    /**
     * return null
     */
    public static function call($error = '')
    {
        self::$last_error = trim($error);
    }
    
    /**
     * @param string $text
     * @param int    $type
     * @param bool   $forcibly
     * @return null
     * @throws DataException
     */
    public static function view($text, $type = 1, $forcibly = false)
    {
        self::$last_error = $type == 3 ? trim($text) : '';
        
        // Если это ошибка и включены исключения
        if (Data::getSetting('throw_exception') && $type == 3) {
            throw new \DataException($text);
        }
        
        if (! Data::getSetting('debug') && ! $forcibly) {
            return;
        }
        
        $trace = (new \Exception)->getTraceAsString();
        
        // Убираем из стека вызовов функции класса self, дабы не путаться а так же укорочиваем выводимый путь до файлов
        $trace = preg_replace_callback(
            "~#\d+ ((.+)\(\d+\):|)(.+)(\n|)~i",
            function($matc){
                static $num = -1;
                if (empty($matc[1]) || $matc[2] == __FILE__){
                    return '';
                }
                $path = str_replace('\\', '/', $matc[1]);
                if (isset($_SERVER['DOCUMENT_ROOT'])) {
                    $path = str_replace($_SERVER['DOCUMENT_ROOT'], '', $path);
                }
                $num++;
                return '#'.$num.' '.$path . $matc[3]."\n";
            },
            $trace
        );
        
        $replace = Data::getSetting('report_replace');
        
        $trace = str_replace( array_keys($replace), array_values($replace), $trace);
        
        if ($type == 2)
            $color = ['#ffeeba', '#fff3cd', '#856404'];
        elseif ($type == 3)
            $color = ['#f5c6cb', '#f8d7da', '#721c24'];
        else
            $color = ['#c3e6cb', '#d4edda', '#155724'];
        
        echo "\n<pre style='margin:5px;padding:7px;background-color: ".$color[1].";border:1px solid ".$color[0].";border-radius:4px;color:".$color[2].";'>\n".htmlspecialchars($text)."\n".trim($trace)."\n</pre>\n";
    }
}

/**
 * Interface _DataDBDriver
 */
interface _DataDBDriver
{
    /**
     * @return mixed
     */
    public function beginTransaction();

    /**
     * @return mixed
     */
    public function rollBack();

    /**
     * @return mixed
     */
    public function commit();

    /**
     * @return mixed
     */
    public function insertId();

    /**
     * @param $sql
     * @param $param
     * @return mixed
     */
    public function query($sql, $param);

    /**
     * @param $result
     * @return mixed
     */
    public function fetchList($result);

    /**
     * @param $result
     * @return mixed
     */
    public function fetchRow($result);

    /**
     * @param $result
     * @return mixed
     */
    public function fetchValue($result);

    /**
     * @param $param
     * @return mixed
     */
    public function connect($param);
}

/**
 * Class _DataDBDriverPDO
 */
class _DataDBDriverPDO implements _DataDBDriver
{
    public $connect;

    /**
     * @return bool
     */
    public function beginTransaction()
    {
        try {
            return $this->connect->beginTransaction();
        }
        catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function rollBack()
    {
        try {
            return $this->connect->rollBack();
        }
        catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function commit()
    {
        try {
            return $this->connect->commit();
        }
        catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * @return mixed
     */
    public function insertId()
    {
        return $this->connect->lastInsertId();
    }

    /**
     * @param $sql
     * @param array $param
     * @return mixed
     * @throws DataException
     */
    public function query($sql, $param = [])
    {
        try {
            if (! empty($param))
            {
                $stm = $this->connect->prepare($sql);
                
                if (_DataHelpers::isAssoc($param))
                {
                    foreach ($param as $key => $value)
                    {
                        $exp = explode('/', $key);
                        $value = (! empty($value) ? $value : '');
                        $set_type = \PDO::PARAM_STR;
                        
                        if (substr_count($sql, ':'.$exp[0]) == 0) {
                            continue;
                        }
                        
                        if (isset($exp[1]) && $exp[1] == 'int')
                        {
                            $value = (int) $value;
                            $set_type = \PDO::PARAM_INT;
                        }
                        
                        $stm->bindValue(':'.$exp[0], $value, $set_type);
                    }
                    
                    $stm->execute();
                }
                else {
                    $stm->execute($param);
                }
            }
            else {
                $stm = $this->connect->query($sql);
            }
            
            return $stm;
        }
        catch (\PDOException $e) {
            throw new \DataException( $e->getMessage() );
        }
    }

    /**
     * @param $result
     * @return mixed
     */
    public function fetchList($result)
    {
        return $result->fetchAll( \PDO::FETCH_ASSOC );
    }

    /**
     * @param $result
     * @return bool
     */
    public function fetchRow($result)
    {
        if ($row = $result->fetch( \PDO::FETCH_ASSOC )) {
            return $row;
        }
        
        return false;
    }

    /**
     * @param $result
     * @return bool
     */
    public function fetchValue($result)
    {
        if ($row = $result->fetch( \PDO::FETCH_NUM )) {
            return $row[0];
        }
        
        return false;
    }

    /**
     * @param $param
     * @return PDO
     * @throws DataException
     */
    public function connect($param)
    {
        if (empty($param['dbms'])) {
            $param['dbms'] = 'mysql';
        }
        
        switch ($param['dbms'])
        {
            case 'mysql':
                $dsn = 'mysql:host='.(! empty($param['host']) ? $param['host'] : 'localhost').';'.(! empty($param['port']) ? 'port='.$param['port'].';' : null).'dbname='.$param['dbname'].';charset='.(! empty($param['charset']) ? $param['charset'] : 'utf8');
                break;
            case 'postgresql':
                $dsn = 'pgsql:host='.(! empty($param['host']) ? $param['host'] : 'localhost').';dbname='.$param['dbname'].';options="--client_encoding='.(! empty($param['charset']) ? $param['charset'] : 'utf8').'"';
                break;
            case 'oracle':
                $dsn = 'oci:dbname='.(! empty($param['host']) ? $param['host'] : 'localhost').'/'.$param['dbname'].';charset='.(! empty($param['charset']) ? $param['charset'] : 'utf8');
                break;
            case 'sqlite':
                $dsn = 'sqlite:'.$param['path'];
                break;
        }
        
        if (! isset($dsn)) {
            throw new \DataException('Тип базы данных не выбран');
        }
        
        try {
            $pdo = new \PDO($dsn, (! empty($param['user']) ? $param['user'] : 'root'), (! empty($param['password']) ? $param['password'] : ''), [
                \PDO::ATTR_ERRMODE             => \PDO::ERRMODE_EXCEPTION, 
                \PDO::ATTR_DEFAULT_FETCH_MODE  => \PDO::FETCH_ASSOC,
            ]);
            
            return $pdo;
        }
        catch (\PDOException $e) {
            throw new \DataException( $e->getMessage() );
        }
    }
}

/**
 * Class _DataDBDriverMySQLi
 */
class _DataDBDriverMySQLi implements _DataDBDriver
{
    public $connect;

    /**
     * @return mixed
     */
    public function beginTransaction()
    {
        return $this->connect->begin_transaction();
    }

    /**
     * @return mixed
     */
    public function rollBack()
    {
        return $this->connect->rollback();
    }

    /**
     * @return mixed
     */
    public function commit()
    {
        return $this->connect->commit();
    }

    /**
     * @return mixed
     */
    public function insertId()
    {
        return $this->connect->insert_id;
    }

    /**
     * @param $sql
     * @param array $param
     * @return mixed
     * @throws DataException
     */
    public function query($sql, $param = [])
    {
        if (! empty($param))
        {
            if (_DataHelpers::isAssoc($param))
            {
                $tmp = [
                    'params' => [],
                    'values' => [],
                    'data_types' => [],
                ];

                foreach ($param as $key => $value)
                {
                    $exp = explode('/', $key);
                    
                    $tmp['params'][ $exp[0] ] = (
                        isset($exp[1]) && $exp[1] == 'int' ? intval($value) : (
                            ! empty($value) ? $value : ''
                        )
                    );
                }

                $sql = preg_replace_callback(
                    '/\:([a-z0-9_]+)/is', 
                    function($matches) use (&$tmp) {
                        if (! isset( $tmp['params'][ $matches[1] ] )) {
                            return $matches[0];
                        }
                        $tmp['values'] []= &$tmp['params'][ $matches[1] ];
                        $tmp['data_types'] []= is_int($tmp['params'][ $matches[1] ]) ? 'i' : 's';
                        return '?';
                    }, 
                    $sql
                );

                $stmt = $this->connect->prepare($sql);
                
                array_unshift($tmp['values'], implode('', $tmp['data_types']));
                $bind_param = &$tmp['values'];
            }
            else
            {
                $new_param = [];
                
                // Делаем параметры ссылками
                foreach ($param as $item) {
                    $new_param []= &$item;
                }
                
                $stmt = $this->connect->prepare($sql);
                
                array_unshift($new_param, str_repeat('s', count($new_param)));
                $bind_param = &$new_param;
            }
            
            if ($stmt)
            {
                call_user_func_array([$stmt, 'bind_param'], $bind_param);
                
                $stmt->execute();
                
                $result = $stmt->get_result();
            }
            else {
                $result = null;
            }
        }
        else {
            $result = $this->connect->query($sql);
        }
        
        if (! $result) {
            throw new \DataException( $this->connect->error );
        }
        
        return $result;
    }

    /**
     * @param $result
     * @return mixed
     */
    public function fetchList($result)
    {
        return $result->fetch_all( MYSQLI_ASSOC );
    }

    /**
     * @param $result
     * @return bool
     */
    public function fetchRow($result)
    {
        if ($row = $result->fetch_array( MYSQLI_ASSOC )) {
            return $row;
        }
        
        return false;
    }

    /**
     * @param $result
     * @return bool
     */
    public function fetchValue($result)
    {
        if ($row = $result->fetch_array( MYSQLI_NUM )) {
            return $row[0];
        }
        
        return false;
    }

    /**
     * @param $param
     * @return mysqli
     * @throws DataException
     */
    public function connect($param)
    {
        $mysqli = new \mysqli(
            (! empty($param['host']) ? $param['host'] : 'localhost'), 
            (! empty($param['user']) ? $param['user'] : 'root'), 
            (! empty($param['password']) ? $param['password'] : ''), 
            $param['dbname']
        );
        
        if (! empty($mysqli->connect_error)) {
            throw new \DataException( $mysqli->connect_error );
        }
        
        if (empty($param['charset'])) {
            $param['charset'] = 'utf8';
        }
        
        if (! $mysqli->set_charset( $param['charset'] )) {
            throw new \DataException( $mysqli->error );
        }
        
        return $mysqli;
    }
}

/**
 * Class _DataDBDriverMySQL
 */
class _DataDBDriverMySQL implements _DataDBDriver
{
    public $connect;

    /**
     * @return bool
     */
    public function beginTransaction()
    {
        return (bool) mysql_query('START TRANSACTION', $this->connect);
    }

    /**
     * @return bool
     */
    public function rollBack()
    {
        return (bool) mysql_query('ROLLBACK', $this->connect);
    }

    /**
     * @return bool
     */
    public function commit()
    {
        return (bool) mysql_query('COMMIT', $this->connect);
    }

    /**
     * @return int
     */
    public function insertId()
    {
        return mysql_insert_id($this->connect);
    }

    /**
     * @param $sql
     * @param array $param
     * @return resource
     * @throws DataException
     */
    public function query($sql, $param = [])
    {
        if (! empty($param))
        {
            if (_DataHelpers::isAssoc($param))
            {
                $new = [];
                
                foreach ($param as $key => $value)
                {
                    $exp = explode('/', $key);
                    
                    if (isset($exp[1]) && $exp[1] == 'int') {
                        $new[ $exp[0] ] = intval($value);
                    } else {
                        $new[ $exp[0] ] = '"'.mysql_real_escape_string($value, $this->connect).'"';
                    }
                }
                
                $sql = preg_replace_callback(
                    '/\:([a-z0-9_]+)/is', 
                    function($matches) use (&$new) {
                        if (! isset( $new[ $matches[1] ] )) {
                            return $matches[0];
                        }
                        return $new[ $matches[1] ];
                    }, 
                    $sql
                );
            }
            else
            {
                $key = -1;
                
                $sql = preg_replace_callback(
                    '/\?/is', 
                    function($matches) use (&$key, &$param) {
                        $key++;
                        return '"'.mysql_real_escape_string($param[ $key ], $this->connect).'"';
                    }, 
                    $sql
                );
            }
        }
        
        $result = mysql_query($sql, $this->connect);
        
        if (! $result) {
            throw new \DataException( mysql_error($this->connect) );
        }
        
        return $result;
    }

    /**
     * @param $result
     * @return array
     */
    public function fetchList($result)
    {
        $list = [];
        
        if ($result)
        {
            while ($row = mysql_fetch_assoc($result)) {
                $list[] = $row;
            }
        }
        
        return $list;
    }

    /**
     * @param $result
     * @return array|bool
     */
    public function fetchRow($result)
    {
        if ($row = mysql_fetch_assoc($result)) {
            return $row;
        }
        
        return false;
    }

    /**
     * @param $result
     * @return mixed
     */
    public function fetchValue($result)
    {
        if ($row = mysql_fetch_row($result)) {
            return $row[0];
        }
        
        return false;
    }

    /**
     * @param array $param
     * @return resource
     * @throws DataException
     */
    public function connect($param)
    {
        $mysql = @mysql_connect(
            (! empty($param['host']) ? $param['host'] : 'localhost'), 
            (! empty($param['user']) ? $param['user'] : 'root'), 
            (! empty($param['password']) ? $param['password'] : '')
        );
        
        if (! $mysql) {
            throw new \DataException( mysql_error() );
        }
        
        if (! mysql_select_db($param['dbname'], $mysql)) {
            throw new \DataException( mysql_error($mysql) );
        }
        
        if (empty($param['charset'])) {
            $param['charset'] = 'utf8';
        }
        
        if (! mysql_set_charset($param['charset'], $mysql)) {
            throw new \DataException( mysql_error($mysql) );
        }
        
        return $mysql;
    }
}

/**
 * Interface _DataCacheDriver
 */
interface _DataCacheDriver
{
    /**
     * @param $param
     * @return mixed
     */
    public function connect($param);

    /**
     * @param $key
     * @param $value
     * @param $exp
     * @param $en_json
     * @return mixed
     */
    public function set($key, $value, $exp, $en_json);

    /**
     * @param $key
     * @param $un_json
     * @param $extended_info
     * @return mixed
     */
    public function get($key, $un_json, $extended_info);

    /**
     * @param $key
     * @return mixed
     */
    public function del($key);
}

/**
 * Class _DataCacheDriverMemcached
 */
class _DataCacheDriverMemcached implements _DataCacheDriver
{
    public $connect;

    /**
     * @param $param
     * @return Memcached
     * @throws DataException
     */
    public function connect($param)
    {
        if (empty($param['servers'])) {
            $param['servers'] = [
                ['127.0.0.1', 11211]
            ];
        }
        
        $memcached = new \Memcached();
        $memcached->addServers($param['servers']);
        
        $stats = $memcached->getVersion();
        
        foreach ($stats as $version)
        {
            if (! empty($version) && $version != '255.255.255') {
                return $memcached;
            }
        }
        
        throw new \DataException('Не удалось подключиться к memcached');
    }

    /**
     * @param $key
     * @param $value
     * @param int $exp
     * @param bool $en_json
     * @return mixed
     */
    public function set($key, $value, $exp = 0, $en_json = false)
    {
        if ($en_json) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        
        return $this->connect->set($key, $value, $exp);
    }

    /**
     * @param $key
     * @param bool $un_json
     * @param bool $extended_info
     * @return array|bool|mixed
     */
    public function get($key, $un_json = false, $extended_info = false)
    {
        $data = $this->connect->get($key);
        
        if ($this->connect->getResultCode() == \Memcached::RES_SUCCESS)
        {
            if ($un_json) {
                $data = json_decode($data, true);
            }
            
            return $extended_info 
                ? ['success' => true, 'data' => $data] 
                : $data;
        }
        
        return $extended_info 
            ? ['success' => false, 'data' => false] 
            : false;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function del($key)
    {
        return $this->connect->delete($key);
    }
}

/**
 * Class _DataCacheDriverMemcache
 */
class _DataCacheDriverMemcache implements _DataCacheDriver
{
    public $connect;

    /**
     * @param array $param
     * @return Memcache
     * @throws DataException
     */
    public function connect($param)
    {
        if (empty($param['server'])) {
            $param['server'] = ['127.0.0.1', 11211];
        }
        
        $memcache = new \Memcache();
        
        $memcache->addServer($param['server'][0], $param['server'][1]);
        
        if (! $stats = $memcache->getVersion()) {
            throw new \DataException('Не удалось подключиться к memcache');
        }
        
        return $memcache;
    }

    /**
     * @param string       $key
     * @param string|array $value
     * @param int          $exp
     * @param bool         $en_json
     * @return mixed
     */
    public function set($key, $value, $exp = 0, $en_json = false)
    {
        if ($value === false) {
            $value = ':false';
        }
        
        if ($en_json) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        
        return $this->connect->set($key, $value, null, $exp);
    }

    /**
     * @param string $key
     * @param bool   $un_json
     * @param bool   $extended_info
     * @return array|bool
     */
    public function get($key, $un_json = false, $extended_info = false)
    {
        if ($data = $this->connect->get($key))
        {
            if ($data == ':false') {
                $data = false;
            }
            
            if ($un_json) {
                $data = json_decode($data, true);
            }
            
            return $extended_info 
                ? ['success' => true, 'data' => $data] 
                : $data;
        }
        
        return $extended_info 
            ? ['success' => false, 'data' => false] 
            : false;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function del($key)
    {
        return $this->connect->delete($key);
    }
}

/**
 * Class _DataUsingDB
 */
class _DataUsingDB
{
    protected $driverDB,
        $usingCache;

    /**
     * _DataUsingDB constructor.
     * @param $driverDB
     * @param $usingCache
     */
    public function __construct( _DataDBDriver $driverDB, _DataUsingCache $usingCache = null)
    {
        $this->driverDB   = $driverDB;
        $this->usingCache = $usingCache;
    }

    /**
     * @param array $opt
     * @return bool
     * @throws DataException
     */
    public function beginTransaction($opt = [])
    {
        $debug = ! empty($opt['debug']);
        
        if ($this->driverDB->beginTransaction())
        {
            _DataReport::view('Инициализация транзакции', 1, $debug);
            
            return true;
        }
        
        _DataReport::view('Ошибка при инициализации транзакции', 3, $debug);
        
        return false;
    }

    /**
     * @param array $opt
     * @return bool
     * @throws DataException
     */
    public function rollBack($opt = [])
    {
        $debug = ! empty($opt['debug']);
        
        if ($this->driverDB->rollBack())
        {
            _DataReport::view('Откат транзакции', 1, $debug);
            
            return true;
        }
        
        _DataReport::view('Ошибка при откате транзакции', 3, $debug);
        
        return false;
    }

    /**
     * @param array $opt
     * @return bool
     * @throws DataException
     */
    public function commit($opt = [])
    {
        $debug = ! empty($opt['debug']);
        
        if ($this->driverDB->commit())
        {
            _DataReport::view('Фиксация транзакции', 1, $debug);
            
            return true;
        }
        
        _DataReport::view('Ошибка при фиксации транзакции', 3, $debug);
        
        return false;
    }

    /**
     * @return mixed
     */
    public function getInsertID()
    {
        _DataReport::call();
        
        return $this->driverDB->insertId();
    }
    
    /**
     * @param string       $table
     * @param string|array $mixed_var
     * @param array        $param
     * @param array        $opt
     * @return mixed
     */
    public function select($table, $mixed_var = [], $param = [], $opt = [])
    {
        list($part_sql, $param) = _DataHelpers::genPartSQL($mixed_var, $param, ' AND ');
        
        return $this->getRow(
            'SELECT 
              * 
            FROM 
              `'.addslashes($table).'` 
            WHERE 
              '.$part_sql.' 
            LIMIT
              1', 
            $param,
            $opt
        );
    }
    
    /**
     * @param string $table
     * @param array  $mixed_var
     * @param array  $param
     * @param array  $opt
     * @return bool
     */
    public function delete($table, $mixed_var = [], $param = [], $opt = [])
    {
        list($part_sql, $param) = _DataHelpers::genPartSQL($mixed_var, $param, ' AND ');
        
        return $this->exec(
            'DELETE FROM 
              `'.addslashes($table).'` 
            WHERE 
              '.$part_sql, 
            $param,
            $opt
        );
    }
    
    /**
     * @param string $table
     * @param array  $mixed_var
     * @param array  $param
     * @param array  $opt
     * @return bool
     */
    public function update($table, $mixed_var = [], $param = [], $opt = [])
    {
        list($sel_sql, $sel_params) = _DataHelpers::genPartSQL($mixed_var, [], ' AND ', 'sel_');
        list($up_sql, $up_params) = _DataHelpers::genPartSQL($param, [], ', ', 'up_');
        
        return $this->exec(
            'UPDATE 
              `'.addslashes($table).'` 
            SET 
              '.$up_sql.' 
            WHERE 
              '.$sel_sql, 
            array_merge($sel_params, $up_params),
            $opt
        );
    }

    /**
     * @param string $table
     * @param array  $param
     * @param array  $opt
     * @return bool|mixed
     */
    public function insert($table, $param = [], $opt = [])
    {
        $columns = [];
        $values = [];
        $params = [];
        
        foreach ($param as $column => $value)
        {
            $name_column = explode('/', $column)[0];
            
            $columns []= '`'.$name_column.'`';
            $values []= ':'.$name_column;
            
            $params[ $column ] = $value;
        }
        
        $result = $this->exec(
            'INSERT INTO 
              `'.addslashes($table).'` (
                '.implode(', ', $columns).'
              ) 
            VALUES 
              ('.implode(', ', $values).')', 
            $params, 
            $opt
        );
        
        if (! $result) {
            return false;
        }
        
        return $this->getInsertID();
    }

    /**
     * @param string $sql
     * @param array  $param
     * @param array  $opt
     * @return array|bool
     * @throws DataException
     */
    public function getListWithCount($sql, $param = [], $opt = [])
    {
        $debug = ! empty($opt['debug']);
        
        if (! empty($opt['cache']) && $this->usingCache)
        {
            if (empty($opt['cache_key']))
            {
                _DataReport::view('Ключ кэша не передан', 3, $debug);
                
                return false;
            }
            
            if (empty($opt['cache_sec'])) {
                $opt['cache_sec'] = Data::getSetting('cache_sec');
            }
            
            $this->usingCache->keyVersionInit($opt['cache_key']);
            
            $result = $this->usingCache->get($opt['cache_key'], true, true);
            
            if ($result['success'])
            {
                $keys = $result['data'];
            }
            else
            {
                $num = _DataHelpers::timeRand();
                
                $keys = [
                    'count' => $opt['cache_key'].'_count_'.$num,
                    'items' => $opt['cache_key'].'_items_'.$num,
                ];
                
                $this->usingCache->set($opt['cache_key'], $keys, $opt['cache_sec'], true);
            }
        }
        
        $count_sql = _DataHelpers::extractCountSQL($sql);
        $error = [];
        
        if (isset($keys)) {
            $opt['cache_key'] = $keys['count'];
        }
        
        $count = $this->getValue($count_sql, $param, $opt);
        
        $error []= _DataReport::$last_error;
        
        if (isset($keys)) {
            $opt['cache_key'] = $keys['items'];
        }
        
        $items = $this->getList($sql, $param, $opt);
        
        $error []= _DataReport::$last_error;
        
        _DataReport::call( implode("\n", $error) );
        
        return [
            'count' => $count,
            'items' => $items,
        ];
    }

    /**
     * @param string $sql
     * @param array  $param
     * @param array  $opt
     * @return bool
     */
    public function exec($sql, $param = [], $opt = [])
    {
        $opt['cache'] = false;
        
        return (bool) $this->queryLayer($sql, $param, $opt);
    }

    /**
     * @param string $sql
     * @param array  $param
     * @param array  $opt
     * @return mixed
     */
    public function getList($sql, $param = [], $opt = [])
    {
        return $this->queryLayer($sql, $param, $opt, true, function($result){
            return $this->driverDB->fetchList($result);
        });
    }

    /**
     * @param string $sql
     * @param array  $param
     * @param array  $opt
     * @return mixed
     */
    public function getRow($sql, $param = [], $opt = [])
    {
        return $this->queryLayer($sql, $param, $opt, true, function($result){
            return $this->driverDB->fetchRow($result);
        });
    }

    /**
     * @param string $sql
     * @param array  $param
     * @param array  $opt
     * @return mixed
     */
    public function getValue($sql, $param = [], $opt = [])
    {
        return $this->queryLayer($sql, $param, $opt, false, function($result){
            return $this->driverDB->fetchValue($result);
        });
    }

    /**
     * @param string $sql
     * @param array  $param
     * @param array  $opt
     * @param bool   $json
     * @param        $callback
     * @return mixed
     * @throws DataException
     */
    protected function queryLayer($sql, $param, $opt, $json = false, $callback = null)
    {
        $debug = ! empty($opt['debug']);
        $desc = '';
        
        if (! is_array($param)) {
            $param = [];
        }
        
        if (Data::getSetting('debug') || $debug)
        {
            $param_info = [];
            
            foreach ($param as $key => $value) {
                $param_info[] = '['.$key.'] => '.$value;
            }
            
            $desc .= preg_replace('/ {2,}/', ' ', str_replace(["\r","\n","\t"], ' ', $sql))."\n";
            $desc .= (! empty($param_info) ? implode("\n", $param_info)."\n" : null);
        }
        
        if (! empty($opt['cache']) && $this->usingCache)
        {
            if (empty($opt['cache_key']))
            {
                _DataReport::view($desc.'Ключ кэша не передан', 3, $debug);
                
                return false;
            }
            
            if (empty($opt['cache_sec'])) {
                $opt['cache_sec'] = Data::getSetting('cache_sec');
            }
            
            $this->usingCache->keyVersionInit($opt['cache_key']);
            
            $result = $this->usingCache->get($opt['cache_key'], $json, true);
            
            if ($result['success'])
            {
                _DataReport::view($desc.'Результат получен из кэша: '.$opt['cache_key'], 1, $debug);
                
                return $result['data'];
            }
        }
        
        try {
            $start = microtime(true);
            
            $result = $this->driverDB->query($sql, $param);
            
            $sec = microtime(true) - $start;
            
            _DataReport::view($desc.'Время запроса: '.number_format($sec, 4, '.', '').' sec', ($sec > 0.3 ? 2 : 1), $debug);
        }
        catch (\DataException $e)
        {
            _DataReport::view('Ошибка запроса: '.$e->getMessage()."\n".trim($desc), 3, $debug);
            
            return null;
        }
        
        if (is_callable($callback)) {
            $result = call_user_func_array($callback, [$result]);
        }
        
        if (! empty($opt['cache']) && $this->usingCache)
        {
            $this->usingCache->set($opt['cache_key'], $result, $opt['cache_sec'], $json);
        }
        
        return $result;
    }
}

/**
 * Class _DataUsingCache
 */
class _DataUsingCache
{
    protected $driver,
        $data = [];

    /**
     * _DataUsingCache constructor.
     * @param $driver
     */
    public function __construct( _DataCacheDriver $driver)
    {
        $this->driver = $driver;
    }

    /**
     * @param $key
     * @param bool $un_json
     * @param bool $extended_info
     * @return mixed
     */
    public function get($key, $un_json = false, $extended_info = false)
    {
        _DataReport::call();
        
        $this->keyVersionInit($key);
        
        if (Data::getSetting('app_cache') && isset($this->data[ $key ])) {
            return $this->data[ $key ];
        }
        
        $value = $this->driver->get($key, $un_json, $extended_info);
        
        if (Data::getSetting('app_cache')) {
            $this->data[ $key ] = $value;
        }
        
        return $value;
    }

    /**
     * @param $key
     * @param $value
     * @param int $exp
     * @param bool $en_json
     * @return mixed
     */
    public function set($key, $value, $exp = 0, $en_json = false)
    {
        _DataReport::call();
        
        $this->keyVersionInit($key);
        
        $result = $this->driver->set($key, $value, $exp, $en_json);
        
        if (Data::getSetting('app_cache') && $result) {
            $this->data[ $key ] = $value;
        }
        
        return $result;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function del($key)
    {
        _DataReport::call();
        
        $this->keyVersionInit($key);
        
        $result = $this->driver->del($key);
        
        if (Data::getSetting('app_cache') && $result) {
            unset($this->data[ $key ]);
        }
        
        return $result;
    }

    /**
     * @param $version
     * @param $key
     * @param $param
     * @return string
     */
    public function getKeyWithVersion($version, $key, $param)
    {
        _DataReport::call();
        
        if (! $num = $this->driver->get($version))
        {
            $num = _DataHelpers::timeRand();
            
            $this->driver->set($version, $num, 3600 * 24);
        }
        
        $part = [
            $version,
            $key,
            md5( $num .'_'. serialize($param) )
        ];
        
        return implode('_', $part);
    }

    /**
     * @param $key
     */
    public function keyVersionInit(&$key)
    {
        _DataReport::call();
        
        if (is_array($key)) {
            $key = $this->getKeyWithVersion($key[0], $key[1], ! empty($key[2]) ? $key[2] : []);
        }
    }
}

/**
 * Class _DataHelpers
 */
class _DataHelpers
{
    /**
     * @param $main_sql
     * @return mixed
     */
    public static function extractCountSQL($main_sql)
    {
        // Убираем на время вторичные запросы если они есть
        list($sql, $attach) = self::getNested($main_sql);

        // Удаляем у запроса order by и limit
        $sql = trim(
            preg_replace('/(ORDER BY|LIMIT).*$/is', '', $sql)
        );

        // Узнаём, групируются ли данные
        preg_match('/(GROUP BY)/is', $sql, $preg_gb);

        // В зависимости от типа получения количества строк, получаем его
        if (empty($preg_gb)) {
            $sql = preg_replace('/SELECT.*FROM/is', 'SELECT COUNT(*) FROM', $sql);
        } else {
            $sql = preg_replace('/SELECT.*FROM/is', 'SELECT * FROM', $sql);
            $sql = 'SELECT COUNT(*) FROM ('.$sql.') AS `tmp_count`';
        }

        // Возвращаем вторичные запросы на место
        return self::setNested($sql, $attach);
    }

    /**
     * @param $mixed_var
     * @param array $param
     * @param string $glue
     * @param string $prefix
     * @return array
     */
    public static function genPartSQL($mixed_var, $param = [], $glue = ', ', $prefix = '')
    {
        if (is_numeric($mixed_var))
        {
            $part_sql = '`id` = :id';
            $param = [
                'id' => $mixed_var
            ];
        }
        elseif (is_array($mixed_var))
        {
            $part_sql = [];
            $param = [];
            
            foreach ($mixed_var as $column => $value)
            {
                $name_column = explode('/', $column)[0];
                
                $part_sql[] = '`'.$name_column.'` = :'. $prefix . $name_column;
                
                $param[ $prefix . $column ] = $value;
            }
            
            $part_sql = implode($glue, $part_sql);
        }
        else
        {
            $part_sql = $mixed_var;
        }
        
        return [
            $part_sql, 
            $param
        ];
    }

    /**
     * @return string
     */
    public static function timeRand()
    {
        return microtime(true) . mt_rand(10000, 99999);
    }
    
    /**
     * @param array $array
     * @return bool
     */
    public static function isAssoc($array)
    {
        $keys = array_keys($array);
        return $keys !== array_keys($keys);
    }

    /**
     * @param $sql
     * @param $attach
     * @return mixed
     */
    protected static function setNested($sql, $attach)
    {
        if (empty($attach)) {
            return $sql;
        }

        return str_replace(
            array_keys($attach),
            array_values($attach),
            $sql
        );
    }

    /**
     * @param $sql
     * @return array
     */
    protected static function getNested($sql)
    {
        $attach = [];
        $i = 1;

        $sql = preg_replace_callback(
            '/\((.*?)\)/is',
            function ($matches) use (&$attach, &$i) {
                $key = '#prc'.$i++.'#';
                $attach[ $key ] = $matches[1];
                return '('.$key.')';
            },
            $sql
        );

        return [$sql, $attach];
    }
}
