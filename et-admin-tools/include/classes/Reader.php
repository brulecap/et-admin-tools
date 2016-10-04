<?php
/**
 * Reader interface allowing various implementations of the file(stream) reader
 *
 * @author Bruce LeCaptain <brulecap@europeantableware.com>
 *
 */
interface Reader {

  public function setColumns($header_array_template);
  public function getColumn($column_type);
  public function getValue($row, $column);
  public function numRows();
}
?>