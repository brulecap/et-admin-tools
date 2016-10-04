<?php
/**
 * @package BBL\Classes\Config
 *
 * @author Bruce LeCaptain <brulecap@europeantableware.com>
 *
 */
namespace BBL\Classes\Config;

/**
 * SMTPConfig contains the login parameters for the outgoing SMTP server
 */
class SMTPConfig {
  const SMTPHOST = 'smtp.yourdomain.com'; // smtp server address
  const SMTPPORT = 587;
  const SMTPFQDN = 'www.yourdomain.com'; // Your fully qualified domain name
  const SMTPUSER = 'name@yourdomain.com'; // email address
  const SMTPPASS = 'password'; // password
  const DEFAULTSENDTO = 'anothername@yourdomain.com'; // This can be used as a default "to" email address
  const CRLF = "\r\n"; // This is used as the terminating(carriage return/line feed) characters in the fputs commands sent to the SMTP server
}