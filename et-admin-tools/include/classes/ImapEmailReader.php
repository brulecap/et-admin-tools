<?php
/*
 * Basic implementation of email client using imap
 *
 * This is open for modification. I only added some basic imap
 * functionality.
 *
 * @author Bruce LeCaptain <brulecap@europeantableware.com>
 * 
 * @todo Add more imap functionality as needed.
 *
 */
class ImapEmailReader {

  // imap server connection
  private $conn;

  // folders
  private $inbox;
  private $processed_folder;
  private $error_folder;
  
  // email login credentials
  private $server;
  private $port;
  private $service;
  private $optional_flags;
  private $mailbox_server;
  private $user;
  private $pass;
  private $options;

  private $logger;

  /*
   * ImapEmailReader constructor
   *
   * Set logger, connect to email server using imap
   *
   * @param string $options Options bit mask
   *
   * See php {@link PHP_MANUAL#imap_open} for details on the above parameters(excluding the Logger).
   */
  function __construct($server, $port, $service, $optional_flags, $user, $pass, $options) {
    $this->logger = BBL\Classes\get_logger();
    $this->server = $server;
    $this->port = $port;
    $this->service = $service;
    $this->optional_flags = $optional_flags;
    $this->user = $user;
    $this->pass = $pass;
    $this->options = $options;
    $this->inbox = 'INBOX';
    $this->processed_folder = $this->inbox . '.Processed';
    $this->error_folder = $this->inbox . '.Error';
    $this->mailbox_server = '{'.$this->server.':'.$this->port.($this->service!==''?'/'.$this->service:'').$this->optional_flags.'}';
    $this->connect();
    
  }

  /*
   * Close email server imap connection.
   */
  function __destruct() {
    $this->close();
  }

  /*
   * Close email server connection
   */
  function close() {
    if ($this->conn) {
      imap_close($this->conn);
    }
  }

  /*
   * Open the server connection.
   *
   * If service is not specified imap_open defaults to the impa service.
   *
   * Currently does not support the use of n_retries or params.
   */
  private function connect() {
    $this->logger->output('Connecting to email for user ' . $this->user . '.', BBL\Classes\LogLevel::INFO);
    $this->conn = imap_open($this->mailbox_server . $this->inbox, $this->user, $this->pass, $this->options);
    if (!$this->conn) {
      $this->logger->output('Failed to connect to email server', BBL\Classes\LogLevel::ERROR);
    } else {
      $this->logger->output('Email server connect success using ' . ($this->service!==''?$this->service:'imap') . ' service.', BBL\Classes\LogLevel::INFO);
    }
  }

  /*
   * Move a specific message to processed folder
   *
   * @param int $msg_index is imap message index
   *
   */
  public function moveToErrorFolder($msg_index) {
    $this->move($msg_index, $this->error_folder);
  }

  /*
   * Move a specific message to error folder
   *
   * @param int $msg_index is imap message index
   *
   */
  public function moveToProcessedFolder($msg_index) {
    $this->move($msg_index, $this->processed_folder);
  }

  /*
   * Move a specific message to a new folder
   *
   * The pop3 service does not support folders. As a result, this method
   * will not work as anticipated for the pop3 service.
   *
   * @param int $msg_index is imap message index
   * @param string $folder Optional folder specifying where to move the message. INBOX.Processed used if not specified.
   */
  public function move($msg_index, $folder='INBOX.Processed') {
    // move on server
    if ($this->service !== 'pop3') {
      // pop3 servers do not support the concept of mailbox folders.
      if (!imap_mail_move($this->conn, $msg_index, $folder)) {
        $this->logger->output('imap_mail_move error: ' .  imap_last_error(), BBL\Classes\LogLevel::ERROR);
      }
    } else {
      $this->logger->output('Attempting to move message index ' . $msg_index . ' to folder '. $folder . ' on a pop3 server.', BBL\Classes\LogLevel::INFO);
    }
    imap_expunge($this->conn);
  }

  /*
   * Decodes strings based on encoding
   *
   * @param $encoded_string is string to be decoded
   * @param $decode_type is decoding to be performed on input string
   *
   * @return string
   *
   * @todo This was written to handle an attachment encoded in base64. Need to ensure that any other decoding is in line with expectations.
   */
  public function imapDecode($encoded_string, $decode_type) {
    if ($encoded_string) {
      switch ($decode_type) {
        case ENC7BIT:
          $result = imap_utf7_decode($encoded_string);
          break;
        case ENCBASE64:
          $this->logger->output('decoding BASE64', BBL\Classes\LogLevel::DEBUG);
          $result = base64_decode($encoded_string);
          break;
        case ENCQUOTEDPRINTABLE:
          $result = imap_qprint($encoded_string);
          break;
        case ENCBINARY:
        case ENC8BIT:
        case ENCOTHER:
        default:
          $result = $encoded_string;
          break;
      }
    }
    return $result;
  }

  /*
   * Checks message for an attachment.
   *
   * I am using ifsubtype and ifdisposition and disposition === 'ATTACHMENT'
   * to determine if message has an attahcment. Not sure if this is the correct
   * way. See php {@link PHP_MANUAL#imap_fetchstructure} for details.
   *
   * @param int $part is the part being checked
   *
   * @return bool true found, false not found
   */
  private function imapIsAttachment($part) {
    $result = false;
    $this->logger->output('Subtype: ' . $part->ifsubtype . ' Disposition: ' . $part->ifdisposition,  BBL\Classes\LogLevel::DEBUG);
    if ($part->ifsubtype) {
      if ($part->ifdisposition) {
        if (strcasecmp($part->disposition, 'ATTACHMENT') === 0) {
          return true;
        }
      }
    }
    return $result;
  }

  /*
   * Gets a message attachment.
   *
   * @param int $index is the message number returned by imap
   *
   * @return array of the form ('attachment'=>'encoded string representation of attached file,
   *                            'encoding'=>how string is encoded,
   *                            'type'=>type of file)
   *
   * @todo This is very incomplete. This works for the attachment as it appears in the emails currently
   *       being processed. i.e. Attachment is in body part #2. I need to come up with a way to extract
   *       the attachment(s) as outlined in the $structure variable. This will also change the
   *       structure of the return variable to an array of arrays of the form outlined above.
   */
  public function getImapRawAttachment($index='') {
    $result = null;
    $this->logger->output('Index: ' . $index,  BBL\Classes\LogLevel::DEBUG);
    if ($index !== '') {
      $structure = imap_fetchstructure($this->conn, $index);
      if ($structure && ($structure->type === TYPEMULTIPART)) {
        $number_parts = count($structure->parts);
        $this->logger->output('Structure type: ' . $structure->type . ' Number parts: ' . $number_parts,  BBL\Classes\LogLevel::DEBUG);
        for ($part_num=0;$part_num<$number_parts;$part_num++) {
          if ($this->imapIsAttachment($structure->parts[$part_num])) {
            $result = array('attachment'=>imap_fetchbody($this->conn, $index, $part_num+1),
                'encoding'=>$structure->parts[$part_num]->encoding,
                'type'=>$structure->parts[$part_num]->subtype);
            break;
          }
        }
      }
    }
    return $result;
  }

  /*
   * Search subject of email
   *
   * This is an alternative search method for searching email subject and should
   * be used in the case the op_silent flag needs to be set in the connection
   * parameters.
   *
   * @param string $subject string to match against the email subject
   *
   * @return array of imap message indexes that match the search criteria
   */
  function searchSubject($subject) {
    $result = false;
    $headers = imap_headers($this->conn);
    $sizeof_headers = sizeof($headers);
    for ($i=1;$i<=$sizeof_headers;$i++) {
      $header = imap_headerinfo($this->conn,$i);
      if ($header->subject == $subject) {
        $result[] = $i;
      }
    }
    return $result;
  }

  /*
   * Searches email.
   *
   * Uses imap_search function to search emails. Please note: imap_search
   * ALWAYS returns false if the op_silent flag is set in the connection parameters.
   * If for some reason this flag needs to be set, the alternative is something like
   * @see searchSubject().
   *
   * @param string $search See php {@link PHP_MANUAL#imap_search} for details.
   *
   * @return mixed array imap message indexes that match the search criteria or false if no messages found
   */
  public function search($search_string) {
    return imap_search($this->conn, $search_string);
    $imap_obj = imap_check($this->conn);
  }

  /*
   * Gets the number of messages in the mailbox.
   *
   * @return int number of messages in the mailbox
   */
  public function getNumberMessages() {
    return imap_num_msg($this->conn);
  }

  /*
   * Change to a folder.
   *
   * @param string $folder
   */
  public function changeFolder($folder) {
    if ($this->service !== 'pop3') {
      // pop3 servers do not support the concept of mailbox folders.
      imap_reopen($this->conn, $folder);
    } else {
      $this->logger->output('Attempting to change to folder ' . $folder . ' on a pop3 server.', BBL\Classes\LogLevel::INFO);
    }
  }

  /*
   * List all folders.
   *
   * The imap_list function returns all of the folders prefixed
   * by mailbox_server.
   *
   * @return mixed an array of strings, each string containing the name of a folder
   * or false if nothing is found.
   */
  public function listAllFolders() {
    return imap_list($this->conn, $this->mailbox_server, "*");
  }

  /*
   * Get current working folder.
   *
   * The imap_check function returns an object with information about the current mailbox
   * to include the mailbox name.
   *
   * @return mixed string with the current mailbox name or false on failure
   */
  public function getCurrentMailbox() {
    $result = false;
    if ($temp = imap_check($this->conn)) {
      $result = $temp->Mailbox;
    }
    return $result;
  }

  /*
   * Mark message for deletion
   *
   * @param int $message_index
   */
  public function delete($message_index) {
    $this->logger->output('Deleting message in ' . $this->getCurrentMailbox() . ', index ' . $message_index . '.', BBL\Classes\LogLevel::DEBUG);
    imap_delete($this->conn, $message_index);
  }

  /*
   * Deletes all emails marked for deletion
   *
   * imap_expunge only works within the current working
   * folder and needs to be performed within each folder
   * where messages have been marked for deletion
   */
  public function expunge() {
    $this->logger->output('Expunging messages in ' . $this->getCurrentMailbox() . '.', BBL\Classes\LogLevel::DEBUG);
    imap_expunge($this->conn);
  }

  /*
   * Deletes all email older than date in all folders
   *
   * @parm string $date of the form j-F-Y
   *              j is the day of the month without leading zeros
   *              F is the full textual representation of a month, such as January or March
   *              Y is full numeric representation of a year, 4 digits
   *
   */
  public function deleteOlderThan($date) {
    // Save current folder so we can return there after deleting messages
    $original_folder = $this->getCurrentMailbox();
    $folders = $this->listAllFolders();
    foreach ($folders as $current_folder) {
      $this->changeFolder($current_folder);
      if ($old_messages = $this->search('BEFORE ' . $date)) {
        foreach ($old_messages as $message_to_delete) {
          $this->delete($message_to_delete);
        }
        $this->expunge();
      }
    }
    $this->changeFolder($original_folder);
  }
  
  /*
   * Unmarks a message marked for deletion.
   *
   * @parm int $message_index
   * 
   * @return bool true on success, false on failure
   *
   */
  public function unDelete($message_index) {
    return imap_undelete($this->conn, $message_index);
  }

  /*
   * Gets the message number.
   *
   * @parm int $message_index
   * 
   * @return mixed int message index or false on failure
   * 
   * Checking return value requires use of the "identical" operator === as a message index
   * may be 0.
   *
   */
  public function getMessageNumber($message_index) {
    $result = false;
    $obj = imap_headerinfo($this->conn, $message_index);
    if (is_object($obj)) $result = $obj->Msgno;
    return $result;
  }

  /*
   * Gets the message id.
   *
   * @parm int $message_index
   * 
   * @return mixed string containing the  message id or false on failure
   *
   */
  public function getMessageID($message_index) {
    $result = false;
    $obj = imap_headerinfo($this->conn, $message_index);
    if (is_object($obj)) $result = $obj->message_id;
    return $result;
  }
}
?>