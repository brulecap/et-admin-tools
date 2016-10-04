<?php
/**
 * @package BBL\Classes\Config
 *
 * @author Bruce LeCaptain <brulecap@europeantableware.com>
 *
 */
namespace BBL\Classes\Config;

/**
 * ImapConfig contains the login parameters for the incoming IMAP server
 *
 * See php {@link PHP_MANUAL#imap_open} for details.
 */
class ImapConfig {
  const IMAPHOST = 'mail.yourdomain.com';
  const IMAPPORT = 143;
  const IMAPSERVICE = 'imap';
  const IMAPFLAGS = '/novalidate-cert';
  const IMAPUSER = 'yourname@yourdomain.com';
  const IMAPPASS = 'password';
  const IMAPOPTIONS = null;
  const INBOX = 'INBOX';
  const PROCESSEDFOLDER = 'INBOX.Processed';
  const ERRORFOLDER = 'INBOX.Error';
}
?>