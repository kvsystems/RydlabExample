<?php
namespace RydlabTools;

use WebSocket\Client;

/**
 * Class DevTools
 * @package RydlabTools
 */
class DevTools {

    /**
     * Message ID
     * @var int
     */
    private $_id = 0;

    /**
     * Web Socket Client
     * @var Client
     */
    private $_client;

    /**
     * Host for Web Socket Client
     * @var string
     */
    private $_host;

    /**
     * Port for Web Socket Client
     * @var int
     */
    private $_port;

    /**
     * List of open Google Chrome tabs
     * @var array
     */
    private $_tabs;

    /**
     * Timeout in seconds
     * @var int
     */
    private $_timeout;

    /**
     * Available domains
     * @var array
     */
    private $_domains = ['Network','Page', 'Runtime', 'DOM'];

    /**
     * Current domain to call methods on
     * @var string
     */
    private $_current;

    /**
     * Gets WebSocket address
     * @param $tab
     * @return null
     */
    private function _getWsUrl($tab)   {
        return $this->_tabs[$tab]['webSocketDebuggerUrl'] ?? null;
    }

    /**
     * Close WebSocket client
     */
    private function _close() {
        if ($this->_client) {
            $this->_client->close();
        }
    }

    /**
     * Creates WebSocket client
     * @param $wsUrl
     * @return Client
     */
    private function _getWsClient($wsUrl) {
        return new Client($wsUrl);
    }

    /**
     * Gets DevTools tabs
     */
    private function _getTabs() {
        $ch = curl_init('http://'.$this->_host.':'.$this->_port.'/json');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        if (false === $result) {
            die('Cant use');
        }
        $this->_tabs = json_decode($result, true);
    }

    /**
     * DevTools constructor.
     * @param string $host
     * @param int $port
     * @param int $timeout
     */
    public function __construct($host = 'localhost', $port = 9222, $timeout = 5)   {
        $this->_host    = $host;
        $this->_port    = $port;
        $this->_timeout = $timeout;
    }

    /**
     * Gets DevTools domain
     * @param $name
     * @return $this
     */
    public function __get($name)    {
        if (in_array($name, $this->_domains)) {
            $this->_current = $name;
            return $this;
        }
        die('Bad domain');
    }

    /**
     * Calls DevTools domain function
     * @param $name
     * @param $arguments
     * @return mixed|null
     * @throws \WebSocket\BadOpcodeException
     */
    public function __call($name, $arguments)
    {
        $payload = [
            'method' => $this->_current . '.' . $name,
            'id' => $this->_id
        ];
        if (!empty($arguments)) {
            $payload['params'] = $arguments[0];
        }
        $this->_client->send(json_encode($payload));
        $response = $this->waitResult($this->_id);
        return $response;
    }

    /**
     * Connects to tab
     * @param int $tab
     */
    public function connect($tab = 0)   {
        $this->_getTabs();
        $this->_close();
        $url = $this->_getWsUrl($tab);
        $this->_client = $this->_getWsClient($url);
        $this->_client->setTimeout($this->_timeout);
    }

    /**
     * Waits result from WebSocket
     * @param $resultId
     * @param null $timeout
     * @return mixed|null
     */
    public function waitResult($resultId, $timeout = null)
    {
        $timeout = $timeout?? $this->_timeout;
        $startTime = time();
        $result = null;
        while (true) {
            $now = time();
            if (($now - $startTime) > $timeout) {
                break;
            }
            try {
                $message = json_decode($this->_client->receive(), true);
                if (isset($message['result']) && $message['id'] == $resultId) {
                    $result = $message;
                    break;
                }
            } catch (\Exception $e) {
                break;
            }
        }
        return $result;
    }

    /**
     * Waits event from WebSocket
     * @param $eventName
     * @param null $timeout
     * @return array
     */
    public function waitEvent($eventName, $timeout = null)
    {
        $timeout = $timeout?? $this->_timeout;
        $startTime = time();
        $messages = [];
        $matchingMessage = null;
        while (true) {
            $now = time();
            if (($now - $startTime) > $timeout) {
                break;
            }
            try {
                $message = json_decode($this->_client->receive(), true);
                $messages[] = $message;
                if (isset($message['method']) && $message['method'] === $eventName) {
                    $matchingMessage = $message;
                    break;
                }
            } catch (\Exception $e) {
                break;
            }
        }
        return ['matching_message' => $matchingMessage, 'messages' => $messages];
    }
}