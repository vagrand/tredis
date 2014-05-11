<?php
/**
 * Include rediska file
 */
require_once 'Rediska.php';

/**
 * TRedis
 *
 * Class to work with redis
 *
 * @copyright (C) 2014 point.od.ua <vagrand@mail.ru>
 * @author Tishenko Vladimir <vagrand@mail.ru>
 */
class TRedis {

	/**
	 * Variable to stopre instance of class
	 *
	 * @var TRedis
	 */
	private static $_instance = null;

	/**
	 * Variable to store Redis config
	 *
	 * @var array
	 */
	private $_config = array();

	/**
	 * Variable to store Redis connection
	 *
	 * @var array
	 */
	private $_connections = array();

	/**
	 * Variable to store aliases of connections for this call of
	 * class instance
	 *
	 * @var array
	 */
	private $_currentConnectionAliases = array();

	/**
	 * Class constructor
	 *
	 * @param array $config
	 * @return void
	 */
	public function __construct($config)
	{
		// Check config
		if (empty($config)) {
			throw new Exception('Empty config', 'empty_config');
		}

		// Store config
		$this->_config = $config;
	}

	/**
	 * Method return instance of current class.
	 * Instance has to be created by method self::create()
	 *
	 * @param string|array $alias - Redis connection alias
	 * @return TRedis
	 */
	public static function instance($alias)
	{
		// Check instance
		if (empty(self::$_instance)) {
			throw new Exception('Instance wasn\'t created yet', 'empty_instance');
		}

		// Open connection(s)
		self::$_instance->openConnections($alias);

		// Return instance
		return self::$_instance;
	}

	/**
	 * Method to open connections to Redis DB
	 *
	 * @param string|array $alias - Redis connection alias
	 * @throws Exception
	 * @return void
	 */
	public function openConnections($alias)
	{
		// Aliases
		if (empty($alias)) {
			throw new Exception('Empty connection alias(es)', 'empty_connection_alias');
		}

		// Prepare connection aliases
		$aliases = is_array($alias) ? $alias : array($alias);

		// Open connections
		foreach ($aliases as $alias) {
			// Check is connection already open
			if (empty($this->_connections[$alias])) {
				// Check is exists config for connection with such alias
				if (empty($this->_config[$alias])) {
					throw new Exception('Not exists config for alias "' . $alias . '"');
				}

				// Create connection
				$this->_connections[$alias] = new Rediska($this->_config[$alias]);
			}
		}

		// Store current connection aliases
		$this->_currentConnectionAliases = $aliases;
	}

	/**
	 * Method to create class entity
	 *
	 * @param array $config
	 * @return TRedis
	 */
	public static function create($config)
	{
		return self::$_instance = new TRedis($config);
	}

	/**
	 * Method to delete all keys by tag(s)
	 *
	 * @param string|array $tag - tag name
	 * @return bool
	 */
	public function deleteByTag($tag)
	{
		// Prepare tag
		$tags = is_array($tag) ? $tag : array($tag);

		// Process connections
		$result = true;
		foreach ($this->_currentConnectionAliases as $alias) {
			// Get Rediska connection
			$connection = $this->_connections[$alias];

			// Process tags
			foreach ($tags as $tag) {
				// Get tag keys
				$tagKeys = $connection->get($tag);

				// Delete all keys from tag
				if (!empty($tagKeys) && !$connection->delete($tagKeys)) {
					$result = false;
				}

				// Delete tag
				if (!$connection->delete($tag)) {
					$result = false;
				}
			}
		}

		return $result;
	}

	/**
	 * Method to get values of all keys by tag(s)
	 *
	 * @param string|array $tag - tag name
	 * @return mixed
	 */
	public function getByTag($tag)
	{
		// Prepare tag
		$tags = is_array($tag) ? $tag : array($tag);

		// Process connections
		$results = array();
		foreach ($this->_currentConnectionAliases as $alias) {
			// Get Rediska connection
			$connection = $this->_connections[$alias];

			// Process tags
			foreach ($tags as $tag) {
				// Get tag keys
				$tagKeys = $connection->get($tag);

				// Get keys values
				$results[$alias] = !empty($results[$alias]) ? $results[$alias] : array();
				$results[$alias][$tag] = $connection->get($tagKeys);
			}
		}

		// Prepare and return result
		reset($results);
		return sizeof($results) > 1 ? $results : current($results);
	}

	/**
	 * Method to add cache key to cache tag
	 *
	 * @param string|array $tag - tag name
	 * @param string|array $key
	 * @return mixed
	 */
	public function addKeyToTag($tag, $key)
	{
		// Prepare tag
		$tags = is_array($tag) ? $tag : array($tag);

		// Process connections
		$result = true;
		foreach ($this->_currentConnectionAliases as $alias) {
			// Get Rediska connection
			$connection = $this->_connections[$alias];

			// Process tags
			foreach ($tags as $tag) {
				// Get tag keys
				$tagKeys = $connection->get($tag);

				// Prepare current keys
				$currentKeys = is_array($key) ? array_keys($key) : array($key);

				// Store key into tag info
				if (empty($tagKeys) || !in_array($key, $tagKeys)) {
					$tagKeys = array_merge(is_array($tagKeys) ? $tagKeys : array(), $currentKeys);
					$connection->set($tag, $tagKeys);
				}
			}
		}

		return $result;
	}
	
	/**
	 * Method to store string or array of strings into Redis by tag(s)
	 *
	 * @param string|array $tag - tag name
	 * @param string|array $key
	 * @param string $value
	 * @return mixed
	 */
	public function setByTag($tag, $key, $value = null)
	{
		// Prepare tag
		$tags = is_array($tag) ? $tag : array($tag);

		// Process connections
		$result = true;
		foreach ($this->_currentConnectionAliases as $alias) {
			// Get Rediska connection
			$connection = $this->_connections[$alias];

			// Process tags
			foreach ($tags as $tag) {
				// Get tag keys
				$tagKeys = $connection->get($tag);

				// Prepare current keys
				$currentKeys = is_array($key) ? array_keys($key) : array($key);

				// Store key into tag info
				$tagKeys = array_merge(is_array($tagKeys) ? $tagKeys : array(), $currentKeys);
				$connection->set($tag, $tagKeys);
			}

			// Set info
			if (!$connection->set($key, $value)) {
				$result = false;
			}
		}

		return $result;
	}

	/**
	 * Method to get Rediska connection
	 *
	 * @param string $alias - Redis connection alias
	 * @return Rediska
	 */
	private function _getConnection($alias)
	{
		// Check is connection already open
		if (empty($this->_connections[$alias])) {
			// Check is exists config for connection with such alias
			if (empty($this->_config[$alias])) {
				throw new Exception('Not exists config for alias "' . $alias . '"', 'no_config');
			}

			// Create connection
			$this->_connections[$alias] = new Rediska($this->_config[$alias]);
		}

		return $this->_connections[$alias];
	}

	/**
	 * Magic method to call Rediska methods
	 *
	 * @param string $name - method name
	 * @param array $arguments - method arguments
	 * @return mixed
	 */
	public function __call($name, $arguments = array()) {
		// Process methods
		$results = array();
		foreach ($this->_currentConnectionAliases as $alias) {
			// Get Rediska connection
			$connection = $this->_connections[$alias];

			// Check is method exists
			if (!method_exists($connection, $name)) {
				throw new Exception('Not exists method Rediska::' . $name . '()');
			}

			// Call method
			$results[$alias] = call_user_func_array(array($connection, $name), $arguments);
		}

		// Prepare and return result
		reset($results);
		return sizeof($results) > 1 ? $results : current($results);
	}
}