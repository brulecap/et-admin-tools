<?php
/*
 * Simple SMTP mail sender class
 *
 * The way I am reading the socket may block for an extended period
 * of time while fgets is waiting for something to read. In the connect
 * method I use stream_set_timeout to override the default timeout. The
 * timeout value is an optional paramater in the constructor. The default
 * timeout value may need to be tweaked depending on what is seen operationally.
 *
 * @author Bruce LeCaptain <brulecap@europeantableware.com>
 *
 */
class SMTPSender {
	private $logger;
	private $server;
	private $port;
	private $helo_fqdn;
	private $user;
	private $password;
	private $authorized;
	private $conn;

  /*
   * SMTPSender constructor
   * 
   * @param string $server SMTP server hostname
   * @param int $port SMTP server port number
   * @param string $helo_fqdn fully qualified domain name of client connecting to
   *                          the SMTP server. This is used in the smtp helo command
   * @param string $user user name of client connecting to the smtp server
   * @param string $password password of client connecting to the smtp server
   * @param int $socket_timeout timeout value for the socket created by php {@link PHP_MANUAL#fsockopen}
   */
	function __construct($server, $port, $helo_fqdn, $user, $password, $socket_timeout=1) {
		$this->logger = BBL\Classes\get_logger();
		$this->server= $server;
		$this->port = $port;
		$this->helo_fqdn = $helo_fqdn;
		$this->user = $user;
		$this->password = $password;
		$this->authorized = false;
		$this->conn = false;
		$this->socket_timeout = $socket_timeout;
	}

  /*
   * SMTPSender destructor
   * 
   * Close email server smtp connection.
   */
	function __destruct() {
		$this->close();
	}

  /*
   * Close email server smtp connection.
   */
	private function close() {
		fputs ($this->conn, "QUIT" . BBL\Classes\Config\SMTPConfig::CRLF);
		fclose($this->conn);
	}

  /*
   * Verify response returned for an smtp command
   * 
   * Read smtp socket and verify response is appropriate
   * 
   * @param string $command smtp command to verify
   * @param int $size size of buffer
   * @param array $return_code array of integers, each integer a valid return code
   * 
   * @return bool true if valid, false otherwise
   */
	private function verifySMTPCommand($command, $size, $return_code=array(0)) {
		$result = true;
		try {
			while (($buffer = fgets($this->conn)) !== false) {
				if (!$this->goodSMTPReturn($command, explode(' ', $buffer, 2), $return_code)) {
					$result = false;
				}
			}
		} catch (Exception $e) {
			$this->logger->output('Exception verifying ' . $command . ': ' . $e->getMessage(), BBL\Classes\LogLevel::ERROR);
		}
		return $result;
	}

  /*
   * Connect to email server
   */
	private function connect() {
		if (!$this->conn) {
			$this->conn = fsockopen($this->server, $this->port, $errno, $errstr);
			if ($this->conn) {
				stream_set_timeout($this->conn,$this->socket_timeout);
				fputs ($this->conn, "HELO " . $this->helo_fqdn . BBL\Classes\Config\SMTPConfig::CRLF);
				if (!$this->verifySMTPCommand('HELO', 4096, array(220,250))) {
					$this->logger->output('SMTP command not verified. Quitting...', BBL\Classes\LogLevel::ERROR);
					fputs ($this->conn, "QUIT" . BBL\Classes\Config\SMTPConfig::CRLF);
					fclose($this->conn);
					$this->conn = false;
				}
			} else {
				$this->logger->output('fsockopen error. Error code: '. $errno . ' Error string: ' . $errstr, BBL\Classes\LogLevel::ERROR);
			}
		}
		return $this->conn;
	}

  /*
   * Login to smtp server
   * 
   * Send authlogin, user and password commands to smtp server
   */
	private function authorize() {
		if ($this->connect()) {
			if (!$this->authorized) {
				fputs($this->conn, "auth login" . BBL\Classes\Config\SMTPConfig::CRLF);
				if ($this->verifySMTPCommand('AUTH LOGIN', 1024, array(334))) {
				  $this->logger->output('Encoded user: ' . base64_encode($this->user), BBL\Classes\LogLevel::DEBUG);
          $this->logger->output('Encoded pass: ' . base64_encode($this->password), BBL\Classes\LogLevel::DEBUG);
					fputs($this->conn, base64_encode($this->user) . BBL\Classes\Config\SMTPConfig::CRLF);
					if ($this->verifySMTPCommand('USER', 256, array(334))) {
						fputs($this->conn, base64_encode($this->password) . BBL\Classes\Config\SMTPConfig::CRLF);
						if ($this->verifySMTPCommand('PASSWORD', 256, array(235))) {
							$this->authorized = true;
						}
					}
				}
			}
		}
		return $this->authorized;
	}

	/*
	 * Create and send SMTP email
	 *
	 * @param string $to
	 * @param string $subject
	 * @param string $body
	 * @param array $attachment of the form $attachment['log_record'] is the file contents
	 *                                      $attachment['log_name'] is the file name
	 *                                      $attachment['encoding'] is the file encoding
	 *
	 */
	public function send($to, $subject, $body, $attachment=array()) {
		if ($this->connect()) {
			if ($this->authorize()) {
				fputs ($this->conn, "MAIL FROM: <" . $this->user . ">" . BBL\Classes\Config\SMTPConfig::CRLF);
				if ($this->verifySMTPCommand('MAIL FROM', 256, array(250))) {
					fputs ($this->conn, "RCPT TO: <" . $to . ">" . BBL\Classes\Config\SMTPConfig::CRLF);
					if ($this->verifySMTPCommand('RCPT TO', 256, array(250))) {
						fputs($this->conn, "DATA" . BBL\Classes\Config\SMTPConfig::CRLF);
						if ($this->verifySMTPCommand('DATA', 256, array(354))) {
							// The following commands do not send a response.
							fputs($this->conn, "From: <" . $this->user . ">" . BBL\Classes\Config\SMTPConfig::CRLF);
							fputs($this->conn, "To: <" . $to . ">" . BBL\Classes\Config\SMTPConfig::CRLF);
							fputs($this->conn, "Subject:" . $subject . "" . BBL\Classes\Config\SMTPConfig::CRLF);
							if (!empty($attachment)) {
								fputs($this->conn, 'Content-type: multipart/mixed; boundary=_frontier' . BBL\Classes\Config\SMTPConfig::CRLF . BBL\Classes\Config\SMTPConfig::CRLF);
								fputs($this->conn, 'Comments' . BBL\Classes\Config\SMTPConfig::CRLF);
								fputs($this->conn, "--_frontier" . BBL\Classes\Config\SMTPConfig::CRLF);
							} else {
								fputs($this->conn,  BBL\Classes\Config\SMTPConfig::CRLF);
							}
							fputs($this->conn,  BBL\Classes\Config\SMTPConfig::CRLF . $body);
							if (!empty($attachment)) {
								fputs($this->conn, BBL\Classes\Config\SMTPConfig::CRLF . "--_frontier" . BBL\Classes\Config\SMTPConfig::CRLF);
								fputs($this->conn, "Content-Type: application;" . BBL\Classes\Config\SMTPConfig::CRLF);
								fputs($this->conn, "Content-Disposition: attachment; filename=". $attachment['log_name'] . ";" . BBL\Classes\Config\SMTPConfig::CRLF);
								if ($attachment['encoding'] !== '') {
									fputs($this->conn, "Content-Transfer-Encoding: " . $attachment['encoding'] . ";" .BBL\Classes\Config\SMTPConfig::CRLF . BBL\Classes\Config\SMTPConfig::CRLF);
								}
								fputs($this->conn, $attachment['log_record'] . BBL\Classes\Config\SMTPConfig::CRLF);
								fputs($this->conn, "--_frontier--");
							}
							fputs($this->conn, BBL\Classes\Config\SMTPConfig::CRLF);
							fputs($this->conn, "." . BBL\Classes\Config\SMTPConfig::CRLF); // End email and send
							$this->verifySMTPCommand('Mail sent', 256, array(250));
						}
					}
				}
			}
		}
	}

  /*
   * Check response from SMTP server
   * 
   * Checks response against expected response
   * 
   * @param string $command smtp command to verify
   * @param int $size size of buffer
   * @param array $return_code array of integers, each integer a valid return code
   * 
   * @return bool true if valid, false otherwise
   */
	private function goodSMTPReturn($command, $return_array, $expected_codes) {
		$result = true;
		if (!in_array((int)$return_array[0],$expected_codes)) {
			$result = false;
			$this->logger->output('Error on smtp command ' . $command . '. Error code: ' . (int)$return_array[0] . ' Error string: ' . $return_array[1], BBL\Classes\LogLevel::ERROR);
		}
		$this->logger->output('smtp command ' . $command . '. Code: ' . (int)$return_array[0] . ' String: ' . $return_array[1], BBL\Classes\LogLevel::DEBUG);
		return $result;
	}
}
?>