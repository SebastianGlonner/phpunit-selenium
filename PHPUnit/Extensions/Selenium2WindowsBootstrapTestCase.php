<?php
/**
 * PHPUnit
 *
 * Copyright (c) 2010-2013, Sebastian Bergmann <sebastian@phpunit.de>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Sebastian Bergmann nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package    PHPUnit_Selenium
 * @author     Sebastian Glonner <sebastian.glonner@zeyon.net>
 * @copyright  2010-2013 Sebastian Bergmann <sebastian@phpunit.de>
 * @license    http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 * @link       http://www.phpunit.de/
 * @since      File available since Release 1.2.6
 */

/**
 * Windows specific selenium2 bootstrap test case. Automatically loads server if is not
 * running with several browser extensions.
 * Require:
 * 		RESTClient
 *
 * close running server through REST:
 * http://localhost:4444/selenium-server/driver/?cmd=shutDownSeleniumServer
 *
 * check status of running server through REST:
 * http://localhost:4444/wd/hub/status
 *
 * start server through command line in debug mode and with all drivers:
 * java java -jar Y:/htdocs/zeyconnect/tests/lib/selenium/selenium-server-standalone-2.41.0.jar -debug -port4444 -Dwebdriver.chrome.driver="Y:/htdocs/zeyconnect/tests/lib/selenium/chromedriver.exe" -Dphantomjs.binary.path="Y:/htdocs/zeyconnect/tests/lib/selenium/phantomjs.exe" -Dwebdriver.ie.driver="Y:/htdocs/zeyconnect/tests/lib/selenium/IEDriverServer.exe"
 *
 *
 * @package    PHPUnit_Selenium
 * @author     Sebastian Glonner <sebastian.glonner@zeyon.net>
 * @copyright  2010-2013 Sebastian Bergmann <sebastian@phpunit.de>
 * @license    http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 */
class PHPUnit_Extensions_Selenium2Windows_Bootstrap_TestCase extends PHPUnit_Extensions_Selenium2TestCase {

	const DEBUG_SELENIUM_SERVER           = false;

	const SELENIUM_HOST                   = '127.0.0.1';
	const SELENIUM_PORT                   = 4444;
	const SELENIUM_RUNNING_PATH           = '/wd/hub/status';

	const SELENIUM_BROWSER_PHANTOMJS      = 'phantomjs';
	const SELENIUM_BROWSER_FIREFOX        = 'firefox';
	const SELENIUM_BROWSER_CHROME         = 'chrome';
	const SELENIUM_BROWSER_IE             = 'ie';

	const EXTENSION_ROOT                  = '..\\..\\';

	const SELENIUM_SERVER_JAR_PATH        = 'Server\\selenium-server-standalone-2.41.0.jar';

	static private $restClient            = null;

	static public $browserDrivers         = [
		self::SELENIUM_BROWSER_CHROME    => 'Drivers\\chromedriver.exe',
		self::SELENIUM_BROWSER_IE        => 'Drivers\\IEDriverServer.exe',
		self::SELENIUM_BROWSER_FIREFOX   => null, // no driver neccessary
		self::SELENIUM_BROWSER_PHANTOMJS => 'Drivers\\phantomjs.exe',
	];

	static public $browserConfigNames    = [
		self::SELENIUM_BROWSER_CHROME    => 'Dwebdriver.chrome.driver',
		self::SELENIUM_BROWSER_IE        => 'Dwebdriver.ie.driver',
		self::SELENIUM_BROWSER_FIREFOX   => null, // no driver neccessary
		self::SELENIUM_BROWSER_PHANTOMJS => 'Dphantomjs.binary.path',
	];

	static public function browsers() {
		$result = [];
		$browsers= [
			// self::SELENIUM_BROWSER_PHANTOMJS,
			self::SELENIUM_BROWSER_CHROME,
			// self::SELENIUM_BROWSER_FIREFOX,
			// self::SELENIUM_BROWSER_IE,
		];
		foreach ( $browsers as $browser ) {
			$result[] = array(
				'browserName'     => $browser,
				'host'            => self::SELENIUM_HOST,
				'port'            => self::SELENIUM_PORT,
				'sessionStrategy' => 'persistent',
			);
		}
		return $result;
	}

	static public function setUpBeforeClass()
	{
		self::$restClient =  new RESTclient();

		try {
			// is server running?
			// This will throw ErrorException if host is not reachable
			$response = self::$restClient->get(null,
				'http://'.self::SELENIUM_HOST.':'.self::SELENIUM_PORT.self::SELENIUM_RUNNING_PATH
			);

			$json = @json_decode($response, true);
			if ( !is_array($json) || !isset($json['state']) || $json['state'] !== 'success' ) {
				throw new Exception('Server is not running!');
			}

		} catch (Exception $e) {
			// start server
			$path = dirname(__FILE__).'\\'.self::EXTENSION_ROOT;

			$cmd = 'start /B "" "java" -jar ' . $path.self::SELENIUM_SERVER_JAR_PATH .
				' -port ' . self::SELENIUM_PORT;

			if (self::DEBUG_SELENIUM_SERVER) {
				$cmd .= ' -debug';
			}

			$browsers = self::browsers();
			foreach ($browsers as $params) {
				$browserName = $params['browserName'];

				if ( !isset(self::$browserDrivers[$browserName]) || self::$browserDrivers[$browserName] === null )
					continue;

				$driverFile = $path.self::$browserDrivers[$browserName];
				if ( !file_exists($driverFile) ) {
					throw new Exception('Missing required driver file at: '.$driverFile);
				}

				$cmd .= ' -'.self::$browserConfigNames[$browserName].'="'.$driverFile.'"';
			}

			$handle = @popen($cmd, 'r');
			if ( $handle === false )
				throw new Exception('Could not start selenium standalone server.');

			$read = fread($handle, 8192);
			$returnCode = @pclose($handle);
			if ( $returnCode !== 0 )
				throw new Exception('Invalid return code for command starting standalone selenium server.');
		}
	}

}

?>