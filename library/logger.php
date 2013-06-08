<?php

/**
 * Finally, a light, permissions-checking logging class.
 *
 * Originally written for use with wpSearch
 *
 * Usage:
 * $log = KLogger::instance('KFileLogger', KLogger::INFO, '/var/log/');
 * $log->logInfo('Returned a million search results'); //Prints to the log file
 * $log->logFatal('Oh dear.'); //Prints to the log file
 * $log->logDebug('x = 5'); //Prints nothing due to current severity threshhold
 *
 * @author  Kenny Katzgrau <katzgrau@gmail.com>  Ryan Liu <azhai@126.com>
 * @since   July 26, 2008 — Last update June 4, 2013
 * @link    http://codefury.net
 * @version 0.2.2
 */

/**
 * Logging dispatcher
 */
abstract class KLogger
{
    /**
     * Error severity, from low to high. From BSD syslog RFC, secion 4.1.1
     * @link http://www.faqs.org/rfcs/rfc3164.html
     */
    const EMERG  = 0;  // Emergency: system is unusable
    const ALERT  = 1;  // Alert: action must be taken immediately
    const CRIT   = 2;  // Critical: critical conditions
    const ERR    = 3;  // Error: error conditions
    const WARN   = 4;  // Warning: warning conditions
    const NOTICE = 5;  // Notice: normal but significant condition
    const INFO   = 6;  // Informational: informational messages
    const DEBUG  = 7;  // Debug: debug messages

    //custom logging level
    /**
     * Log nothing at all
     */
    const OFF    = 8;
    /**
     * Alias for CRIT
     * @deprecated
     */
    const FATAL  = 2;

    /**
     * Internal status codes
     */
    const STATUS_LOG_OPEN    = 1;
    const STATUS_OPEN_FAILED = 2;
    const STATUS_LOG_CLOSED  = 3;

    /**
     * We need a default argument value in order to add the ability to easily
     * print out objects etc. But we can't use NULL, 0, FALSE, etc, because those
     * are often the values the developers will test for. So we'll make one up.
     */
    const NO_ARGUMENTS = 'KLogger::NO_ARGUMENTS';
    
    /**
     * Current minimum logging threshold
     * @var integer
     */
    public $severityThreshold = self::INFO;
    /**
     * Default severity of log messages, if not specified
     * @var integer
     */
    protected static $_defaultSeverity    = self::DEBUG;
    /**
     * Valid PHP date() format string for log timestamps
     * @var string
     */
    protected static $_dateFormat         = 'Y-m-d H:i:s';
    /**
     * Array of KLogger instances, part of Singleton pattern
     * @var array
     */
    private static $instances           = array();
    /**
     * Whether record the client ip to log
     * @var bool
     */
    protected static $_recordClientIP   = false;
    /**
     * Array of KLogger instances, part of Observer pattern
     * The backend logger's severity MUST NOT below current logger's
     * @var array
     */
    private $backends                    = array();
    

    /**
     * Partially implements the Singleton pattern. Each $logDirectory gets one
     * instance.
     *
     * @param string  $logDirectory File path to the logging directory
     * @param integer $severity     One of the pre-defined severity constants
     * @return KLogger
     */
    public static function instance($logClass = 'KFileLogger', $severity = false, 
                                      $logStorage = false, array $options=array())
    {        
        if ($severity === false) {
            $severity = self::$_defaultSeverity;
        }
        
        if ($logClass === 'KFileLogger') {
            if ($logStorage === false) {
                if (count(self::$instances) > 0) {
                    return current(self::$instances);
                } else {
                    $logStorage = dirname(__FILE__);
                }
            }
            $logStorage = rtrim($logStorage, '\\/');
        }
        
        $uniqueKey = $logClass . $logStorage;
        if (! in_array($uniqueKey, self::$instances)) {
            self::$instances[$uniqueKey] = new $logClass($severity, $logStorage, $options);
        }
        return self::$instances[$uniqueKey];
    }
    
    public function addBackend(KLogger $backend)
    {
        if ($backend->severityThreshold < $this->severityThreshold) {
            $backend->severityThreshold = $this->severityThreshold;
        }
        $this->backends[] = $backend;
    }
    
    public static function setRecordClientIP($recordClientIP)
    {
        self::$_recordClientIP = $recordClientIP;
    }
    
    /**
     * Writes a $line to the log with a severity level of DEBUG
     *
     * @param string $line Information to log
     * @return void
     */
    public function logDebug($line, $args = self::NO_ARGUMENTS)
    {
        $this->log($line, self::DEBUG);
    }

    /**
     * Sets the date format used by all instances of KLogger
     * 
     * @param string $dateFormat Valid format string for date()
     */
    public static function setDateFormat($dateFormat)
    {
        self::$_dateFormat = $dateFormat;
    }

    /**
     * Writes a $line to the log with a severity level of INFO. Any information
     * can be used here, or it could be used with E_STRICT errors
     *
     * @param string $line Information to log
     * @return void
     */
    public function logInfo($line, $args = self::NO_ARGUMENTS)
    {
        $this->log($line, self::INFO, $args);
    }

    /**
     * Writes a $line to the log with a severity level of NOTICE. Generally
     * corresponds to E_STRICT, E_NOTICE, or E_USER_NOTICE errors
     *
     * @param string $line Information to log
     * @return void
     */
    public function logNotice($line, $args = self::NO_ARGUMENTS)
    {
        $this->log($line, self::NOTICE, $args);
    }

    /**
     * Writes a $line to the log with a severity level of WARN. Generally
     * corresponds to E_WARNING, E_USER_WARNING, E_CORE_WARNING, or 
     * E_COMPILE_WARNING
     *
     * @param string $line Information to log
     * @return void
     */
    public function logWarn($line, $args = self::NO_ARGUMENTS)
    {
        $this->log($line, self::WARN, $args);
    }

    /**
     * Writes a $line to the log with a severity level of ERR. Most likely used
     * with E_RECOVERABLE_ERROR
     *
     * @param string $line Information to log
     * @return void
     */
    public function logError($line, $args = self::NO_ARGUMENTS)
    {
        $this->log($line, self::ERR, $args);
    }

    /**
     * Writes a $line to the log with a severity level of FATAL. Generally
     * corresponds to E_ERROR, E_USER_ERROR, E_CORE_ERROR, or E_COMPILE_ERROR
     *
     * @param string $line Information to log
     * @return void
     * @deprecated Use logCrit
     */
    public function logFatal($line, $args = self::NO_ARGUMENTS)
    {
        $this->log($line, self::FATAL, $args);
    }

    /**
     * Writes a $line to the log with a severity level of ALERT.
     *
     * @param string $line Information to log
     * @return void
     */
    public function logAlert($line, $args = self::NO_ARGUMENTS)
    {
        $this->log($line, self::ALERT, $args);
    }

    /**
     * Writes a $line to the log with a severity level of CRIT.
     *
     * @param string $line Information to log
     * @return void
     */
    public function logCrit($line, $args = self::NO_ARGUMENTS)
    {
        $this->log($line, self::CRIT, $args);
    }

    /**
     * Writes a $line to the log with a severity level of EMERG.
     *
     * @param string $line Information to log
     * @return void
     */
    public function logEmerg($line, $args = self::NO_ARGUMENTS)
    {
        $this->log($line, self::EMERG, $args);
    }

    /**
     * Writes a $line to the log with the given severity
     *
     * @param string  $line     Text to add to the log
     * @param integer $severity Severity level of log message (use constants)
     */
    public function log($line, $severity, $args = self::NO_ARGUMENTS)
    {
        if ($this->severityThreshold >= $severity) {
            $time = null;
            $ipv4 = null;
            if($args !== self::NO_ARGUMENTS) { //Get time and ipv4 if args is a array 
                if (is_array($args)) {
                    if (isset($args['time'])) {
                        $time = $args['time'];
                        unset($args['time']);
                    }
                    if (isset($args['ipv4'])) {
                        $ipv4 = $args['ipv4'];
                        unset($args['ipv4']);
                    }
                    $args = empty($args) ? self::NO_ARGUMENTS : $args;
                }
                
            }
            
            $time = empty($time) ? self::_getTime() : $time;
            $ipv4 = empty($ipv4) ? self::_getIPv4() : $ipv4;
            $this->logTo($severity, $line, $args, $time, $ipv4);
            foreach ($this->backends as $backend) {
                if ($backend->severityThreshold >= $severity) {
                    $backend->logTo($severity, $line, $args, $time, $ipv4);
                }
            }
        }
    }
    
    public function fromHttp()
    {
        $severity = isset($_POST['severity']) ? $_POST['severity'] : self::DEBUG;
        $line = isset($_POST['line']) ? $_POST['line'] : '-';
        $args = isset($_POST['args']) ? $_POST['args'] : self::NO_ARGUMENTS;
        $args = json_decode($args, true);
        if (! is_array($args)) {
            $args = array();
        }
        $args['time'] = isset($_POST['time']) ? $_POST['time'] : '-';
        $args['ipv4'] = isset($_POST['ipv4']) ? $_POST['ipv4'] : '-';
        $this->log($line, $severity, $args);
    }
    
    abstract function logTo($severity, $line, $args, $time, $ipv4);
    
    public function __call($method, $args)
    {
        if (method_exists($this, 'log' . $method)) {
            return call_user_func_array(array($this, 'log' . $method), $args);
        }
    }
    
    public static function getClientRealIP()
    {
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        $forwardedKeys = array(
            'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED',
        );
        foreach ($forwardedKeys as $key) {
            if (isset($_SERVER[$key])) {
                return $_SERVER[$key];
            }
        }
        return $_SERVER['REMOTE_ADDR'];
    }

    protected static function _getTime()
    {
        if (self::$_dateFormat) {
            return date(self::$_dateFormat);
        }
        return '-';
    }

    protected static function _getIPv4()
    {
        if (self::$_recordClientIP) {
            return self::getClientRealIP();
        }
        return '-';
    }

    protected static function _getLevelName($level)
    {
        $levelNames = array(
            'EMERG', 'ALERT', 'CRIT', 'ERROR', 'WARN', 'NOTICE', 'INFO', 'DEBUG'
        );
        return ($level < 8) ? $levelNames[$level] : 'LOG';
    }
}


/**
 * File logging handler
 */
class KFileLogger extends KLogger
{
    /**
     * Current status of the log file
     * @var integer
     */
    private $_logStatus         = self::STATUS_LOG_CLOSED;
    /**
     * Holds messages generated by the class
     * @var array
     */
    private $_messageQueue      = array();
    /**
     * Path to the log directory
     * @var string
     */
    private $_logDirectory      = null;
    /**
     * Path to the log file
     * @var string
     */
    private $_logFilePath       = null;
    /**
     * This holds the file handle for this instance's log file
     * @var resource
     */
    private $_fileHandle        = null;

    /**
     * Standard messages produced by the class. Can be modified for il8n
     * @var array
     */
    private $_messages = array(
        //'writefail'   => 'The file exists, but could not be opened for writing. Check that appropriate permissions have been set.',
        'writefail'   => 'The file could not be written to. Check that appropriate permissions have been set.',
        'opensuccess' => 'The log file was opened successfully.',
        'openfail'    => 'The file could not be opened. Check permissions.',
    );
    /**
     * Octal notation for default permissions of the log file
     * @var integer
     */
    private static $_defaultPermissions = 0777;

    /**
     * Class constructor
     *
     * @param string  $logDirectory File path to the logging directory
     * @param integer $severity     One of the pre-defined severity constants
     * @return void
     */
    public function __construct($severity, $logDirectory)
    {
        if ($severity === self::OFF) {
            return;
        }
        $this->severityThreshold = $severity;
        $this->_logDirectory = $logDirectory;
    }
    
    public function openlogFile($tailname='')
    {
        $logDirectory = rtrim($this->_logDirectory, '\\/');
        $this->_logFilePath = $logDirectory
            . DIRECTORY_SEPARATOR
            . 'log_'
            . $tailname
            . '.txt';

        if (!file_exists($logDirectory)) {
            mkdir($logDirectory, self::$_defaultPermissions, true);
        }

        if (file_exists($this->_logFilePath) && !is_writable($this->_logFilePath)) {
            $this->_logStatus = self::STATUS_OPEN_FAILED;
            $this->_messageQueue[] = $this->_messages['writefail'];
            return;
        }

        if (($this->_fileHandle = fopen($this->_logFilePath, 'a'))) {
            $this->_logStatus = self::STATUS_LOG_OPEN;
            $this->_messageQueue[] = $this->_messages['opensuccess'];
        } else {
            $this->_logStatus = self::STATUS_OPEN_FAILED;
            $this->_messageQueue[] = $this->_messages['openfail'];
        }
    }

    /**
     * Class destructor
     */
    public function __destruct()
    {
        if ($this->_fileHandle) {
            fclose($this->_fileHandle);
        }
    }

    /**
     * Returns (and removes) the last message from the queue.
     * @return string
     */
    public function getMessage()
    {
        return array_pop($this->_messageQueue);
    }

    /**
     * Returns the entire message queue (leaving it intact)
     * @return array
     */
    public function getMessages()
    {
        return $this->_messageQueue;
    }

    /**
     * Empties the message queue
     * @return void
     */
    public function clearMessages()
    {
        $this->_messageQueue = array();
    }

    /**
     * Writes a line to the log without prepending a status or timestamp
     *
     * @param string $line Line to write to the log
     * @return void
     */
    public function writeFreeFormLine($line)
    {
        if ($this->_logStatus == self::STATUS_LOG_OPEN
            && $this->severityThreshold != self::OFF) {
            if (fwrite($this->_fileHandle, $line) === false) {
                $this->_messageQueue[] = $this->_messages['writefail'];
            }
        }
    }

    /**
     * Writes a $line to the log with the given severity
     *
     * @param string  $line     Text to add to the log
     * @param integer $severity Severity level of log message (use constants)
     */
    public function logTo($severity, $line, $args, $time, $ipv4)
    {
        if (empty($this->_logFilePath)) {
            $logtime = (empty($time) || $time === '-') ? time() : strtotime($time);
            $this->openlogFile(date('Y-m-d', $logtime));
        }
        if($args !== self::NO_ARGUMENTS) { /* Print the passed object value */
            $line = $line . '; ' . var_export($args, true);
        } 
        $level = self::_getLevelName($severity);
        $status = sprintf("%s %s %s -->", $time, $ipv4, $level);
        $this->writeFreeFormLine("$status $line" . PHP_EOL);
        return true;
    }
}


/**
 * HTTP post logging handler
 */
class KHTTPLogger extends KLogger
{
    private $_httpURL = '';
    private $_options = array();
    
    public function __construct($severity, $httpURL, array $options=array())
    {
        $this->severityThreshold = $severity;
        $this->_httpURL = trim($httpURL);
        $this->_options = $options;
    }
    
    public function logTo($severity, $line, $args, $time, $ipv4)
    {
        try {
            $args = json_encode($args);
        }
        catch (Exception $e) {
            $args = self::NO_ARGUMENTS;
        }
        $this->post(array(
            'severity' => $severity,
            'line' => $line, 'args' => $args,
            'time' => $time, 'ipv4' => $ipv4
        ));
    }
    
    public function post(array $data)
    {
        if (isset($this->_options['client'])) {
            $client = $this->_options['client'];
            $result = $client->post($data);
            if (is_object($result) && method_exists($result, 'isSuccess')) {
                return $result->isSuccess();
            }
            return $result;
        }
        else if (class_exists('Requests')) { //use https://github.com/rmccue/Requests
            Requests::post($this->_httpURL, array(), $data, $this->_options);
            return true;
        }
        return false;
    }
}


/**
 * DB logging handler
 */
class KPDOLogger extends KLogger
{
    private $_pdo = null;
    private $_dsn = null;
    private $_options = array('table'=>'logs');
    
    public function __construct($severity, $dsn, array $options=array())
    {
        $this->severityThreshold = $severity;
        $this->_dsn = trim($dsn);
        $this->_options = array_merge($this->_options, $options);
        $this->createTable();
    }
    
    public function logTo($severity, $line, $args, $time, $ipv4)
    {
        if($args !== self::NO_ARGUMENTS) { /* Print the passed object value */
            $args = var_export($args, true);
        }
        $level = self::_getLevelName($severity);
        $sql = "INSERT INTO `" . $this->_options['table'] . "` (level, time, ipv4, message, extra) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute(array($level, $time, $ipv4, $line, $args));
        return true;
    }
    
    public function connect($db_type='')
    {
        if (is_null($this->_pdo)) {
            $dsn = $this->_dsn;
            $user = isset($this->_options['user']) ? $this->_options['user'] : '';
            $password = isset($this->_options['password']) ? $this->_options['password'] : '';
            $conn_options = array();
            //限制charset为utf8，sqlite只有utf8这一种字符集
            if ($db_type === 'mysql') {
                if (version_compare('5.3.6') >= 0) {
                    $dsn .= ';charset=utf8';
                }
                else {
                    $conn_options = array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8');
                }
            }
            $this->_pdo = new PDO($dsn, $user, $password, $conn_options);
        }
        return $this->_pdo;
    }
    
    public function createTable()
    {
        $table_name = $this->_options['table'];
        $sql_mysql = <<<EOD
CREATE TABLE IF NOT EXISTS `$table_name` (
`id`  int(10) UNSIGNED NOT NULL AUTO_INCREMENT ,
`level`  varchar(10) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' ,
`time`  datetime NULL DEFAULT NULL ,
`ipv4`  varchar(15) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL ,
`message`  tinytext CHARACTER SET utf8 COLLATE utf8_general_ci NULL ,
`extra`  text CHARACTER SET utf8 COLLATE utf8_general_ci NULL ,
PRIMARY KEY (`id`),
INDEX `time` (`time`) USING BTREE ,
INDEX `ipv4` (`ipv4`) USING BTREE 
)
ENGINE=InnoDB
DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci
AUTO_INCREMENT=1
ROW_FORMAT=COMPACT
;
EOD
;
        $sql_sqlite = <<<EOD
CREATE TABLE "main"."$table_name" (
"id"  INTEGER NOT NULL,
"level"  TEXT(10) NOT NULL,
"time"  TEXT,
"ipv4"  TEXT(15),
"message"  TEXT,
"extra"  TEXT,
PRIMARY KEY ("id" ASC)
)
;
EOD
;
        $db_type = strtolower(substr($this->_dsn, 0, 5));
        if ($db_type === 'mysql') {
            $this->connect($db_type)->exec($sql_mysql);
        }
        else if ($db_type === 'sqlit') {
            $this->connect($db_type)->exec($sql_sqlite);
        }
    }
}

