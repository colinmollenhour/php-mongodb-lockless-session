<?php

class Mage_Core_Model_Session_Abstract_MongoDB extends Varien_Object
{
    protected $_namespace;
    protected $_operations = [];

    public function init(string $namespace, $sessionName = null): static
    {
        $this->_namespace = $namespace;
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $this->start($sessionName);
        }
        if (!isset($_SESSION[$namespace])) {
            $_SESSION[$namespace] = [];
        }
        if (!isset($_SESSION[$namespace]['__operations'])) {
            $_SESSION[$namespace]['__operations'] = [];
        }

        $this->validate();

        return $this;
    }

    /**
     * Set session data
     *
     * @param string|array $key
     * @param mixed $value
     * @return $this
     */
    public function setData($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->_trackOperation('set', $k, $v);
            }
        } else {
            $this->_trackOperation('set', $key, $value);
        }
        return parent::setData($key, $value);
    }

    /**
     * Unset session data
     *
     * @param string $key
     * @return $this
     */
    public function unsetData($key = null)
    {
        if ($key === null) {
            foreach (array_keys($_SESSION[$this->_namespace]) as $k) {
                if ($k !== '__operations') {
                    $this->_trackOperation('unset', $k, null);
                }
            }
        } else {
            $this->_trackOperation('unset', $key, null);
        }
        return parent::unsetData($key);
    }

    /**
     * Track operation for MongoDB updates
     *
     * @param string $type
     * @param string $key
     * @param mixed $value
     */
    protected function _trackOperation($type, $key, $value)
    {
        $_SESSION[$this->_namespace]['__operations'][] = [
            'type' => $type,
            'key' => $key,
            'value' => $value
        ];
    }
}