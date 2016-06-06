<?php
/**
 * @package BBL\Classes
 * 
 * @author Bruce LeCaptain <brulecap@europeantableware.com>
 *
 */
namespace BBL\Classes;
/*
 * Create logger
 */
function get_logger() {
  if (Config\LoggerConfig::LOGGERTYPE === 'File') {
    try {
      $log = FileLogger::getInstance();
    } catch (Exception $e) {
      $log = STDOUTLogger::getInstance();
      $log->output($e->getMessage());
    }
  } else {
    $log = STDOUTLogger::getInstance();
  }
  return $log;
}

/*
 * LogLevel defines the constants of possible logging levels. 
 */
class LogLevel {
  const EMERGENCY = 128;
  const ALERT = 64;
  const CRITICAL = 32;
  const ERROR = 16;
  const WARNING = 8;
  const NOTICE = 4;
  const INFO = 2;
  const DEBUG = 1;
  /*
   * LOGALL is the sum of all possible logging levels. We can use it along with a bitwise operator
   * to set the log level. Example: Calling the method setLogLevel on an instance of Logger
   * with parameter (BBL\Log\LogLevel::DEBUG ^ BBL\Log\LogLevel::LOGALL) would cause all subsequent
   * logging to include everything with the exlusion of debug level logs.
   * 
   */
  const LOGALL    = 255;
}

/*
 * Logger interface allows various implementations of the logging method
 */
interface Logger {
  public static function getInstance();
  public function output($log_message, $log_level);
  public function getLogRecord();
  public function setLogLevel($level_to_log);

}

/*
 * Logger to output to stdout
 */
class STDOUTLogger implements Logger {
  private $log_level = LogLevel::LOGALL;
  private $log_record;
  private static $instance = NULL;

  function __construct() {
    $log_record = '';
  }

  /*
   * Get an instance of STDOUTLogger.
   */
  public static function getInstance() {
    if (!self::$instance) {
      self::$instance = new STDOUTLogger();
    }
    return self::$instance;
  }
  /*
   * Output log message to stdout.
   *
   * @param string $log_message Message to be logged
   * @param int $log_level Indicates the level of the message
   */
  public function output($log_message, $log_level=LogLevel::DEBUG) {
    if ($this->log_level & $log_level ) {
      // Get calling method and class
      $trace = debug_backtrace();
      // Assuming if class is empty then call is coming from "main".
      $temp = (isset($trace[1]['class'])?$trace[1]['class']:'Main') . ':' . (isset($trace[1]['function'])?$trace[1]['function']:'') . ' - ' . $log_message . Config\LoggerConfig::CRLF;
      echo $temp;
      $this->log_record .= $temp;
    }
  }

  public function getLogRecord() {
    return $this->log_record;
  }

  public function setLogLevel($level_to_log) {
    $this->log_level = $level_to_log;
  }

} //End STDOUTLogger

/*
 * Logger to output to a file
 */
class FileLogger implements Logger {
  private $log_level = LogLevel::LOGALL;
  private $log_file;
  private $log_file_location = '';
  private $log_file_pointer = '';
  private static $instance = NULL;

  /*
   * FileLogger constructor.
   *
   * Sets default time zone and opens the log file.
   */
  function __construct() {
    $this->log_file_location = (substr($_SERVER['PHP_SELF'],0,strrpos($_SERVER['PHP_SELF'],'/')) !== ''?substr($_SERVER['PHP_SELF'],0,strrpos($_SERVER['PHP_SELF'],'/')):'.') . '/logs/';
    if (Config\LoggerConfig::LOGFILENAME === null) {
      $this->log_file = date("Ymd", strtotime('now')) . pathinfo(__FILE__, PATHINFO_FILENAME) . '.log';
    } else {
      $this->log_file = Config\LoggerConfig::LOGFILENAME;
    }
    $this->openLogFile();
  }

  /*
   * Get an instance of FileLogger.
   */
  public static function getInstance() {
    if (!self::$instance) {
      self::$instance = new FileLogger();
    }
    return self::$instance;
  }

  /*
   * Opens log file. Requires script to have the permission to create the log file
   * directory(if necessary) and log file.
   */
  private function openLogFile() {
    if (!file_exists($this->log_file_location)) {
      if (!mkdir($this->log_file_location)) {
        throw new Exception ('Failed to create folder ' . $this->log_file_location . '. FileLogger could not be created.');
      }
    }
    $this->log_file_pointer = fopen($this->log_file_location.$this->log_file, 'ab');
    if (!$this->log_file_pointer) {
      throw new Exception ('Failed to open log file. FileLogger could not be created.');
    }
  }

  /*
   * Output log message to file.
   *
   * @param string $log_message Message to be logged
   * @param string $log_level Indicates the level of the message
   */
  public function output($log_message, $log_level=LogLevel::DEBUG) {
    if ($this->log_level && $log_level ) {
      $trace = debug_backtrace();
      fprintf($this->log_file_pointer, "%s" . Config\LoggerConfig::CRLF, date("H:i:s", strtotime('now')) . ' : ' . (isset($trace[1]['class'])?$trace[1]['class']:'Main') . ':' . (isset($trace[1]['function'])?$trace[1]['function']:'') . ' - ' . $log_message);
    }
  }

  public function getLogRecord() {
    $result = '';
    if ($fp = fopen($this->log_file_location.$this->log_file, 'rb')) {
      while (!feof($fp)) {
        $result .= fread($fp, 4096);
      }
      fclose($fp);
    }
    return $result;
  }

  public function setLogLevel($level_to_log) {
    $this->log_level = $level_to_log;
  }
}
?>