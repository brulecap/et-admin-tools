<?php
/**
 * @package BBL\Classes
 *
 * @uses Mysqli
 * @uses Logger
 * @uses LogLevel
 * @uses DBConfig
 *
 * @author Bruce LeCaptain <brulecap@europeantableware.com>
 *
 */
namespace BBL\Classes;
use Mysqli;
use mysqli_stmt;
/**
 * Get database connection.
 */
function get_db() {
  return MySQLIDataBaseConnection::getInstance();;
}

/**
 * Basic implementation of the mysqli class functionality.
 *
 * @todo Add more functionality and add database stub to handle occurences where
 * no connection can be made to the data base server. This would be used for testing
 * purposes. This requires an interface class which would be implemented by this class
 * and the stub class and other data base connection methods.
 */
class MySQLIDataBaseConnection {
  private $mysqli_db;
  private $logger;
  private $host;
  private $user;
  private $password;
  private $db_name;
  private static $instance = NULL;

  /**
   * MySQLIDataBaseConnection constructor
   *
   * Set logger, connect to database server
   *
   * @param string $host
   * @param string $user
   * @param string $password
   * @param string $db_name
   */
  function __construct($host, $user, $password, $db_name) {
    $this->logger = get_logger();
    $this->host = $host;
    $this->user = $user;
    $this->password = $password;
    $this->db_name = $db_name;
    $this->logger->output('DB Host: ' . $this->host . ' DB User: ' . $this->user . ' DB: ' . $this->db_name, LogLevel::INFO);
    $this->connect();
  }

  /**
   * MySQLIDataBaseConnection destructor
   *
   * Close connection
   */
  function __destruct() {
    @$this->mysqli_db->close();
  }

  /**
   * Get an instance of MySQLIDataBaseConnection.
   */
  public static function getInstance() {
    if (!self::$instance) {
      self::$instance = new MySQLIDataBaseConnection(Config\DBConfig::DBHOST,
                                                     Config\DBConfig::DBUSER,
                                                     Config\DBConfig::DBPASS,
                                                     Config\DBConfig::DBNAME);
    }
    return self::$instance;
  }

  /**
   * Connects to the database
   *
   */
  private function connect() {
    $this->logger->output('Connecting...', LogLevel::INFO);
    $this->mysqli_db = @new mysqli($this->host, $this->user, $this->password, $this->db_name);
    if (!$this->mysqli_db->connect_error) {
      $this->logger->output('Successfully connected to the database ' . $this->db_name, LogLevel::INFO);
    } else {
      $this->logger->output('Database connection error: ' . $this->mysqli_db->connect_error, LogLevel::CRITICAL);
      $this->mysqli_db = false;
    }
  }

  /**
   * Check if we are connected to database
   *
   * @return bool true if connected or false otherwise
   */
  public function isConnected() {
    $result = true;
    if ($this->mysqli_db === false) $result = false;
    return $result;
  }

  /**
   * Prepare an SQL statement for execution
   *
   * @param string $statement sql statement to "prepare"
   *
   * @return mixed mysqli_statement class or false if there was an error
   */
  public function prepareStatement($statement) {
    if ($stmt = $this->mysqli_db->prepare($statement)) {
      $this->logger->output('Statement prepared');
    } else {
      $this->logger->output('Error preparing statement "' . $statement . '"', LogLevel::ERROR);
      $this->logger->output('Error is "' . $this->mysqli_db->error . '"', LogLevel::ERROR);
    }
    return $stmt;
  }

  /**
   * Excutes prepared statement
   *
   * Uses call_usr_func_array to allow for an indeterminate number
   * of parameters to the $stmt->execute() method. See php {@link PHP_MANUAL#call_usr_func_array}
   * and php {@link PHP_MANUAL#mysqli_stmt} for details.
   *
   * @param mysqli_stmt $statement statement object
   * @param array $params array of paramters to supply to mysqli_stmt::bind_params
   *
   * @return bool true on succes and false on error
   *
   */
  public function executeStatement($stmt, $params) {
    /*
     * Using call_user_func_array to handle variable number of parameters to the
     * mysqli bind_param.
     *
     * There is something funky with the $params parameter. If the get_reference($params) function
     * is not executed, an error is returned stating that it should be a reference.
     *
     */
    $result = true;
    if ($params !== '') {
      /*
       * call_user_func_array requires the second parameter to be a reference. Using an
       * & in a function call has been deprecated in PHP 5.3 and removed in PHP 5.4.
       */
      $params_referenced = array();
      foreach($params as $key => $value) {
        $params_referenced[$key] = &$params[$key];
      }
      $result = call_user_func_array(array($stmt, 'bind_param'), $params_referenced);
      if($result) {
        $result = $stmt->execute();
        if (!$result) {
          $this->logger->output('Error executing statement', LogLevel::ERROR);
          $this->logger->output('Error is "' . $stmt->error . '"', LogLevel::ERROR);
        }
      } else {
        $this->logger->output('Error binding params', LogLevel::ERROR);
        $this->logger->output('Error is "' . $stmt->error . '"', LogLevel::ERROR);
      }
    }
    return $result;
  }

  /**
   * Perform a simple sql query.
   *
   * Note: This function is open for sql injection.
   *
   * @param string $query sql statement to execute.
   *
   * @return mixed mysqli_statement class or false if there was an error
   */
  public function performQuery($query) {
    $result = false;
    if ($query) {
      $result = @$this->mysqli_db->query($query);
    }
    return $result;
  }

  /**
   * Get number of rows returned from mysql query
   *
   * @param mysqli_result $result_set
   */
  public function numberResults(mysqli_result $result_set) {
    return $result_set->num_rows;
  }

  /**
   * Fetches response from mysql query
   *
   * Fetches response from mysql query as an object
   *
   * @param mysqli_result $execution_result
   *
   * @return object with string properties that corresponds to the fetched row or NULL if there are no more rows in resultset
   */
  public function fetchObject(mysqli_result $execution_result) {
    $result = false;
    if ($execution_result) {
      $result = $execution_result->fetch_object();
      if ($result === null) $result = false;
    }
    return $result;
  }

  /**
   * Gets the number of rows affected by the previous MySQL operation
   *
   * @return int number of rows
   */
  public function numberAffectedRows() {
    return $this->mysqli_db->affected_rows;
  }

  /**
   * Close mysqli prepared statement
   *
   * @param mysqli_stmt $statement
   */
  public function closeStatement(mysqli_stmt $statement) {
    if ($statement) {
      $statement->close();
    }
  }

  /**
   * Get error list from last command executed
   *
   * Get error list from last command executed. If php version < 5.4, this method
   * emulates the error_list.
   *
   * @return array containing the errno, error, and sqlstate
   */
  public function getErrorList() {
    if (version_compare(phpversion(), '5.4.0', '<')) {
      // emulate mysqli_error_list
      $result = array('errno'=>$this->mysqli_db->errno, 'error'=>$this->mysqli_db->error, 'sqlstate'=>$this->mysqli_db->sqlstate);
    } else {
      $result = $this->mysqli_db->error_list;
    }
    return $result;
  }
}