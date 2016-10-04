<?php
/**
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

  /**
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

  /**
   * Set the header array such that header['key'] is the column number corresponding to key.
   *
   * Searches the spreadsheet for values as defined in header_array_template and populates the
   * header array with each found value and it's corresponding column in the spreadsheet.
   *
   * @param array $header_array_template is an array of arrays where each array is of the form
   * 'user_key'=>('header text','required flag')
   *
   * 'user key' is the key the code uses as a reference in the header array.
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

  /**
   * Checks a cell value against a list of possible header values.
   *
   * Checks a cell value against a list of possible header values and if found sets the
   * header array 'key' to the column we are checking.
   *
   * @param int $row is the row in the spreadsheet
   * @param int $col is the column in the spreadsheet
   * @param array $header_array_template is an array of arrays where each array is of the form
   * 'user_key'=>('header text','required flag')
   *
   * 'user key' is the key the code is going as a reference in the header array.
   * 'header text' is the header text as it appears in the spreadsheet
   * 'required' is a bool where if true then it is a required element when validating the header array
   *
   */
  private function getHeaderRow($row, $col, $header_array_template) {
    foreach ($header_array_template as $key => $value) {
      if ($this->val($row, $col) === $value['text']) {
        $this->logger->output('Found header column. key: ' . $key . ' text: ' . $value['text'], BBL\Classes\LogLevel::DEBUG);
        $this->header[$key] = $col;
        break; //Found it. No need to continue.
      }
    }
  }

  /**
   * Checks to see if the header array is valid.
   *
   * Checks the header array to see if it contains all the required key/column number entries.
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

  /**
   * Returns the column corresponding to the column type as specified by the header array.
   *
   * @param string $column_type is the value in the header array and is used to access the column number.
   *
   * @return mixed column number if found or '' is not found.
   */
  public function getColumn($column_type) {
    $result = '';
    if (isset($this->header[$column_type])) $result = $this->header[$column_type];
    return $result;
  }

  /**
   * Returns the the value in a cell specified by the row and column.
   *
   * @param int $row
   * @param int $column
   *
   * @return string cell value.
   */
  public function getValue($row, $column) {
    return $this->val($row, $column);
  }

  /**
   * Returns the number of rows in the worksheet.
   *
   * @return int number of rows.
   */
  public function numRows() {
    return $this->num_rows;
  }
}
?>