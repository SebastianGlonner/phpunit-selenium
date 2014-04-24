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
 * Keeps a Session object shared between selenium server sessions to save time during development.
 * Do not restart browser after finishing tests and reuse the same browser session after
 * redoing tests.
 *
 * @package    PHPUnit_Selenium
 * @author     Sebastian Glonner <sebastian.glonner@zeyon.net>
 * @copyright  2010-2013 Sebastian Bergmann <sebastian@phpunit.de>
 * @license    http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 * @version    Release: @package_version@
 * @link       http://www.phpunit.de/
 * @since      Class available since Release 1.2.6
 */
class PHPUnit_Extensions_Selenium2TestCase_SessionStrategy_Persistent
    extends PHPUnit_Extensions_Selenium2TestCase_SessionStrategy_Shared
{

    const TMP_SESSION_ID_LOCATION = './';

    static private $tmpSessionFileLocation = null;

    static public function setTmpSessionFileLocation($location) {
        self::$tmpSessionFileLocation = $location;
    }

    static private function getTmpSessionFileLocation() {
        if ( self::$tmpSessionFileLocation )
            return self::$tmpSessionFileLocation;

        return self::TMP_SESSION_ID_LOCATION;
    }

    public function session(array $parameters)
    {
        // has been already validated
        $browserName = $parameters['browserName'];
        $location = self::getTmpSessionFileLocation() . '.session_' . $browserName;

        $session = null;
        if ( file_exists($location) ) {
            $sessionParameters = json_decode(file_get_contents($location), true);

            $seleniumServerUrl = PHPUnit_Extensions_Selenium2TestCase_URL::fromHostAndPort($sessionParameters['host'], $sessionParameters['port']);
            $driver = new PHPUnit_Extensions_Selenium2TestCase_Driver($seleniumServerUrl, $sessionParameters['seleniumServerRequestsTimeout']);

            $id                            = $sessionParameters['id'];
            $host                          = $sessionParameters['host'];
            $port                          = $sessionParameters['port'];
            $sessionPrefix                 = $sessionParameters['sessionPrefix'];
            $seleniumServerRequestsTimeout = $sessionParameters['seleniumServerRequestsTimeout'];

            $sessionPrefixUrlClass = new PHPUnit_Extensions_Selenium2TestCase_URL($sessionPrefix);

            // check if our session is still running, otherwise create new one
            $response = $driver->curl('GET', $seleniumServerUrl->descend("/wd/hub/sessions"), NULL);
            $sessions = $response->getValue();

            $isValid = false;
            foreach ( $sessions as $info ) {
                if ( $info['id'] == $id ) {
                    $isValid = true;
                    break;
                }
            }

            if ( $isValid ) {
                $timeouts = new PHPUnit_Extensions_Selenium2TestCase_Session_Timeouts(
                    $driver,
                    $sessionPrefixUrlClass->descend('timeouts'),
                    $seleniumServerRequestsTimeout * 1000
                );

                $session = new PHPUnit_Extensions_Selenium2TestCase_Session(
                    $driver,
                    $sessionPrefixUrlClass,
                    $seleniumServerUrl,
                    $timeouts
                );
            }
        }

        if ( !$session ) {
            $session  = parent::session($parameters);

            $id                            = $session->id();
            $host                          = $parameters['host'];
            $port                          = $parameters['port'];
            $sessionPrefix                 = $session->getSessionUrl()->__toString();
            $seleniumServerRequestsTimeout = $parameters['seleniumServerRequestsTimeout'];
        }

        $sessionParameters = [
            'id'                            => $id,
            'host'                          => $host,
            'port'                          => $port,
            'sessionPrefix'                 => $sessionPrefix,
            'seleniumServerRequestsTimeout' => $seleniumServerRequestsTimeout,
        ];
        file_put_contents($location, json_encode($sessionParameters));

        $session->setPersistentSession(true);
        return $session;
    }
}
