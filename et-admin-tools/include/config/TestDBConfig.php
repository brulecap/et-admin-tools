<?php
/**
 * @package BBL\Classes\Config
 *
 * @author Bruce LeCaptain <brulecap@europeantableware.com>
 *
 */
namespace BBL\Classes\Config;

/**
 * This DBConfig contains the login parameters for the "test" MySQL database.
 *
 * See php {@link PHP_MANUAL#mysqli.construct} for details.
 */
class DBConfig {
  const DBHOST = 'localhost';
  const DBUSER = 'testdbuser';
  const DBPASS = 'testdbpassword';
  const DBNAME = 'testdbname';
  const DBTEST = true; // Used to verify test or operational configuration
}