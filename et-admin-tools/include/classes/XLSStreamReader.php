<?php
/*
 * XLSStreamReader provides additional functionality for Spreadsheet_Excel_Reader and
 * implements the Reader interface so that other classes/functions can process xls, csv, ...
 * with a common way.
 * 
 * @author Bruce LeCaptain <brulecap@europeantableware.com>
 * 
 */
class XLSStreamReader extends Spreadsheet_Excel_Reader implements Reader {
  private $num_rows;
  private $num_cols;
  private $header = array();
  private $logger;

  /*
   * MySQLIDataBaseConnection constructor
   *
   * Set logger, connect to database server
   *
   * @param string $data xls file as a string
   */
  function __construct($data) {
    $this->logger = BBL\Classes\get_logger();
    parent::__construct('','','',$data);
    if ($this->is_readable) {
      $this->num_rows = $this->rowcount();
      $this->num_cols = $this->colcount();
    }
  }

  /*
   * Set the header array such that header['key'] is the column number corresponding to key.
   *
   * Searches the spreadsheet for values as defined in header_array_template and populates the
   * header array with each found value and it's corresponding column in the spreadsheet.
   * 
   * @param array $header_array_template is an array of arrays where each array is of the form
   * 'user_key'=>('header text','required flag')
   * 
   * 'user key' is the key the code is going as a reference in the header array.
   * 'header text' is the header text as it appears in the spreadsheet
   * 'required' is a bool where if true then it is a required element when validating the header array
   *  
   * @return bool true if all required elements where found, false otherwise.
   */
  public function setColumns($header_array_template) {
    $result = false;
    for ($row=1;$row<$this->num_rows;$row++) {
      for ($col=1;$col<$this->num_cols;$col++) {
        $this->getHeaderRow($row, $col, $header_array_template);
        if ($this->isValidHeaderArray($header_array_template)) {
          $result = true;
          break 2; // Found the values we need. Break out of both for loops.
        }
      }
    }
    return $result;
  }
  
  private function getHeaderRow($row, $col, $header_array_template) {
    foreach ($header_array_template as $key => $value) {
      if ($this->val($row, $col) === $value['text']) {
        $this->logger->output('Found header column. key: ' . $key . ' text: ' . $value['text'], BBL\Classes\LogLevel::DEBUG);
        $this->header[$key] = $col;
        break;
      }
    }
  }
  
  private function isValidHeaderArray($header_array_template) {
    $result = true;
    foreach ($header_array_template as $key => $value) {
      if ($value['required'] && (!array_key_exists($key, $this->header) || ($this->header[$key] === 0))) {
        $result = false;
      }
    }
    $this->logger->output('Returning header array is ' . ($result?'vaild':'INVALID') . '.', BBL\Classes\LogLevel::DEBUG);
    return $result;
  }

  public function getColumn($column_type) {
    $result = '';
    if (isset($this->header[$column_type])) $result = $this->header[$column_type];
    return $result;
  }

  public function getValue($row, $column) {
    return $this->val($row, $column);
  }

  public function numRows() {
    return $this->num_rows;
  }
}
?>