<?php
/*
 * @package BBL\Classes\Config
 *
 * @author Bruce LeCaptain <brulecap@europeantableware.com>
 *
 */
namespace BBL\Classes\Config;

/*
 * This LoggerConfig contains the parameters for creating a logger that will output STDOUT.
 */
class LoggerConfig {
  const LOGGERTYPE = 'File';
  /*
   * If LOGFILENAME is null the log file will be named "Date""ScriptName".log.
   * "Date" is formatted YMD where Y is four digit year, M is the 2 digit representation
   * of the month and D is the 2 digit day of the month. Scriptname is the name of the
   * script without the ".php" extension. 
   */
  const LOGFILENAME = null; //If null the log file will  be named
  const CRLF = "\r\n";
}
?>