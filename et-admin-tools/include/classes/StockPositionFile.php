<?php
/*
 * Searches emails in specified account, grabs the attachment and processes the file.
 * 
 * @author Bruce LeCaptain <brulecap@europeantableware.com>
 *
 * @todo Not sure what to do with this "class". Seems to be a conglomoration of disparate
 *       parts rather than a "class".
 *
 */
class StockPositionFile {
  private $message_index = null;
  private $logger;
  private $subject = null;
  private $attachment_reader = false;
  private $email_reader;
  private $database;
  // Header values - These are the values in the "header" row of the data file
  private $column_array_template;
  
  private $raw_attachment = array('attachment'=>'',
                                  'encoding'=>'',
                                  'type'=>'');

  /*
   * StockPositionFile constructor
   *
   * Finds and processes email attachments
   *
   * @param string $subject_line is email subject we will search on to find emails of interest.
   */
  function __construct($subject_line, $header_array) {
    $this->column_array_template = $header_array;
    $this->logger = BBL\Classes\get_logger();
    $this->subject = $subject_line;
  }

  /*
   * Processes attachment
   *
   * Reads emails, find emails of interest, and grabs attachment.
   *
   * @return bool true on success of false on failure
   *
   * Failure can happen on a number of different levels and the log file/command line
   * output needs to be insepcted for the specific type of failure.
   */
  public function process() {
    $error = true;
    $this->email_reader = new ImapEmailReader(BBL\Classes\Config\ImapConfig::IMAPHOST,
                                              BBL\Classes\Config\ImapConfig::IMAPPORT,
                                              BBL\Classes\Config\ImapConfig::IMAPSERVICE,
                                              BBL\Classes\Config\ImapConfig::IMAPFLAGS,
                                              BBL\Classes\Config\ImapConfig::IMAPUSER,
                                              BBL\Classes\Config\ImapConfig::IMAPPASS,
                                              BBL\Classes\Config\ImapConfig::IMAPOPTIONS);
    if ($this->getStockEmail()) {
      // Create Reader class
      $this->attachment_reader = $this->getReader($this->email_reader->imapDecode($this->raw_attachment['attachment'],
                                                  $this->raw_attachment['encoding']));
      if ($this->attachment_reader !== null) {
        $this->logger->output('Got reader.', BBL\Classes\LogLevel::INFO);
        // Verify that the header row has the expected values
        if ($this->attachment_reader->setColumns($this->column_array_template)) {
          $this->logger->output('Got header.', BBL\Classes\LogLevel::INFO);
          if ($this->updateDatabase()) {
            $this->logger->output('Database updated. Moving email to INBOX.Processed.', BBL\Classes\LogLevel::INFO);
            $this->email_reader->moveToProcessedFolder($this->message_index);
            $error = false;
          }
        } else {
          $this->logger->output("Could not find header row.", BBL\Classes\LogLevel::ERROR);
        }
      } else {
        $this->logger->output('Problem retreiving reader..', BBL\Classes\LogLevel::ERROR);
      }
      if ($error) {
        $this->logger->output('Moving email to INBOX.Error.', BBL\Classes\LogLevel::ERROR);
        $this->email_reader->moveToErrorFolder($this->message_index);
      }
    }
    $this->sendLog('', 'Stock Position File Processed', 'Stock Position File Processed. See attached log file.');
  }

  /*
   * Updates database
   * 
   * Gets database connection and iterates through all of the rows in the spreadsheet,
   * updating the database depending on values in spreadsheet.
   *
   * @return bool true on success or false on failure
   *
   * Failure can happen on a number of different levels and the log file/command line
   * output needs to be insepcted for the specific type of failure. Failure here propogates
   * up.
   */
  private function updateDatabase() {
    $result = true;
    $this->database = BBL\Classes\get_db();
    if ($this->database->isConnected()) {
      $prepared_statement = "update products set products_quantity=9999, products_status=1, manufacturer_out_of_stock=? where products_model like ?";
      $stmt = $this->database->prepareStatement($prepared_statement);
      if ($stmt) {
        // Assuming the first row is the header row.
        $num_rows = $this->attachment_reader->numRows();
        for ($i=2;$i<=$num_rows;$i++) {
          /*
           * Get the values, make sure they are vaild and update database.
           */
          $sku = $this->attachment_reader->getValue($i,$this->attachment_reader->getColumn('sku'));
          $quantity = $this->attachment_reader->getValue($i,$this->attachment_reader->getColumn('quantity'));
          $this->logger->output('Processing sku: ' . $sku . ', quantity: ' . $quantity, BBL\Classes\LogLevel::DEBUG);
          if (($sku !=='') && is_numeric($quantity)) {
            $this->logger->output('Updating sku ' . $sku . ' and marking it ' . ($quantity>=10?'in stock.':'out of stock.'), BBL\Classes\LogLevel::DEBUG);
            /*
             * If the quantity in the spreadsheet>10, set manufacturer_out_of_stock to 0, 1 otherwise.
             * 
             * Note: I am not checking if the product is in the database.
             */
            if (!$this->database->executeStatement($stmt, array("si",($quantity>=10?0:1), $sku))) {
              $result = false;
            }
          }
        }
      }
      $this->database->closeStatement($stmt);
    } else {
      $this->logger->output('Not connected to the database.', BBL\Classes\LogLevel::CRITICAL);
      $result = false;
    }
    return $result;
  }

  /*
   * Find email containing subject line.
   *
   */
  private function getStockEmail() {
    $found = false;
    if ($this->email_reader->getNumberMessages() > 0) {
      $result = $this->email_reader->search('SUBJECT "' . $this->subject . '"');
      if ($result) {
        foreach ($result as $index) {
          $this->raw_attachment = $this->email_reader->getImapRawAttachment($index);
          if (is_array($this->raw_attachment) &&  ($this->raw_attachment['attachment'] != '')) {
            $this->logger->output('Found attachment. Type: ' . $this->raw_attachment['type'] . ' Encoding: ' . $this->raw_attachment['encoding'], BBL\Classes\LogLevel::INFO);
            $this->message_index = $index;
            $found = true;
            break;
          }
        }
      }
    }
    if (!$found) {
      $result = false;
      $this->logger->output('Email with matching subject line and attachment NOT found.', BBL\Classes\LogLevel::INFO);
    }
    return $result;
  }

  /*
   * Creates file reader
   *
   * Creates file reader based on file type. Each reader should implement the
   *
   * @param string $data is the decoded string representation of the attached file.
   *
   * @return mixed Reader implementation or null if reader could not be created
   *
   * @todo Currently this only recognizes a file type of 'VND.MS-EXCEL' which is the file.
   *       Other types will be added as they are needed.
   */
  private function getReader($data) {
    $result = null;
    switch ($this->raw_attachment['type']) {
      case 'VND.MS-EXCEL':
        $this->logger->output('Getting Excel Reader. Data: ' . substr(bin2hex($data), 0, 4), BBL\Classes\LogLevel::INFO);
        $result = new XLSStreamReader($data);
        if (!$result->is_readable) $result = null;
        break;
      default:
        $result = null;
    }
    return $result;
  }

  /*
   * Sends the log file as an attachment to an email
   * 
   * @param string $to email address
   * @param string $subject email subject
   * @param string $body body of email
   * @param bool $purge optional parameter where if true emails will be deleted if they are older than $age
   * @param string $age optional paramter specifying emails older $age than to be deleted.
   * 
   */
  private function sendLog($to, $subject, $body, $purge=true, $age='-30 days') {
    if ($purge) {
      // Delete messages 30 or more days old
      $this->email_reader->deleteOlderThan(date("j-F-Y", strtotime($age)));
    }
    if ($to === '') $to = BBL\Classes\Config\SMTPConfig::DEFAULTSENDTO;
    /*
     * It may seem obvious, but the log received in the email will have none of the logging
     * that occurs while processing the SMTP commands. You will need to check the log file
     * on the server for that. The reason I mention this is because I WAS looking for the
     * logging. It was a DOHHHH with a palm slap to the forehead moment...
     */
    $smtp_server = new SMTPSender(BBL\Classes\Config\SMTPConfig::SMTPHOST,
                                  BBL\Classes\Config\SMTPConfig::SMTPPORT,
                                  BBL\Classes\Config\SMTPConfig::SMTPFQDN, 
                                  BBL\Classes\Config\SMTPConfig::SMTPUSER,
                                  BBL\Classes\Config\SMTPConfig::SMTPPASS);
    $attachment['log_record'] = $this->logger->getLogRecord();
    $attachment['log_name'] = str_replace(' ', '', $this->subject) . '.txt';
    $attachment['encoding'] = '8bit';
    $smtp_server->send($to, $subject, $body, $attachment);
  }  
}