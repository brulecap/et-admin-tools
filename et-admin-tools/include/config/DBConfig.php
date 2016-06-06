<?php
/*
 * @package BBL\Classes\Config
 *
 * @author Bruce LeCaptain <brulecap@europeantableware.com>
 *
 */
namespace BBL\Classes\Config;

/*
 * This DBConfig contains the login parameters for the "operational" MySQL database.
 *
 * See php {@link PHP_MANUAL#mysqli.construct} for details.
 */
class DBConfig {
  const DBHOST = 'localhost';
  const DBUSER = 'dbuser';
  const DBPASS = 'dbpassword';
  const DBNAME = 'dbname';
  const DBTEST = false; // Used to verify test or operational configuration
}