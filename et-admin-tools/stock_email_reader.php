<?php
/*
 * Script to grab email message attachments and process them.
 * 
 * We receive an email with an xls file attachment from one of our suppliers. This
 * file has their current product inventory. This script will grab the file,
 * move the email to a processed folder and then update our system with the current
 * inventory. Currently this is configured for a specific stock position file we receive from
 * one of our suppliers but it could easily be expanded to handle mulitple messages from
 * any of our suppliers.
 * 
 * Currently I have this running daily as a cron job.
 * 
 * @author Bruce LeCaptain <brulecap@europeantableware.com>
 * 
 * Command line options:
 *   --mode 
 *     Recognized value is silent(--mode="silent"). If --mode="silent", logging will go to file. If some other value
 *     is supplied (or --mode is not a command line option) than logging will go to STDOUT.
 *   --db
 *     Recognized value is ops(--db="ops"). If --db="ops", the operational database will be used. If some other value
 *     is supplied (or --db is not a command line option) than the test database will be used.
 *   --debug
 *     Recognized value is true(--debug="true"). If --debug="true", then log will include debug statements. If some other value
 *     is supplied (or --debug is not a command line option) than log will include all log entries with the exception of debug
 *     statements. This is also configurable via the Logger setLogLevel method.
 * 
 */

  // Set default time zone
  date_default_timezone_set('America/New_York');
  // Set internal encoding
  mb_internal_encoding('UTF-8');
  // Include path
  define ('CLASS_PATH', __DIR__ . '/include/classes/');
  define ('CONFIG_CLASS_PATH', __DIR__ . '/include/config/');
  // Files for the logger
  $mode_options = getopt("",array("mode:"));
  if (isset($mode_options['mode']) && ($mode_options['mode'] === 'silent')) {
    require_once CONFIG_CLASS_PATH . 'FileLoggerConfig.php';
//    require_once CONFIG_CLASS_PATH . 'FileLoggerConfig.php';
  } else {
    require_once CONFIG_CLASS_PATH . 'STDOUTLoggerConfig.php';
  }
  require_once CLASS_PATH . 'Logger.php';
  $log = BBL\Classes\get_logger();
  $debug_options = getopt("",array("debug:"));
  if (isset($debug_options['debug']) && ($debug_options['debug'] === 'true')) {
    // Log everything
    $log->setLogLevel(BBL\Classes\LogLevel::LOGALL);
  } else {
    // Log everything except debug
    $log->setLogLevel(BBL\Classes\LogLevel::DEBUG ^ BBL\Classes\LogLevel::LOGALL);
  }
  // Files for IMAP connection
  require_once CLASS_PATH . 'ImapEmailReader.php';
  require_once CONFIG_CLASS_PATH . 'ImapConfig.php';
  // Files for SMTP connection
  require_once CLASS_PATH . 'SMTPSender.php';
  require_once CONFIG_CLASS_PATH . 'SMTPConfig.php';
  // Files required for database connection 
  require_once CLASS_PATH . 'MySQLIDataBaseConnection.php';
  $db_options = getopt("",array("db:"));
  if (isset($db_options['db']) && ($db_options['db'] === 'ops')) {
    // Connect to operational database
    $log->output('Using operational database.',  BBL\Classes\LogLevel::INFO);
    require_once CONFIG_CLASS_PATH . 'DBConfig.php';
  } else {
    // Connect to test database
    $log->output('Using TEST database.',  BBL\Classes\LogLevel::INFO);
    require_once CONFIG_CLASS_PATH . 'TestDBConfig.php';
  }
  // Files for reading and processing the spreadsheet
  require_once CLASS_PATH . 'Reader.php';
  require_once CLASS_PATH . 'excel_reader2.php';
  require_once CLASS_PATH . 'XLSStreamReader.php';
  require_once CLASS_PATH . 'StockPositionFile.php';
  $header_keys = array('sku'=>array('text'=>'SKU','required'=>true),'quantity'=>array('text'=>'AVAIL QTY', 'required'=>true));
  $stock_position_file = new StockPositionFile('Stock Position',$header_keys);
  $stock_position_file->process();
?>