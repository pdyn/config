<?php
namespace pdyn\config;

/**
 * A simple key-value store.
 */
class ConfigStore implements \pdyn\config\ConfigStoreInterface {
	/** @var \pdyn\database\DbDriverInterface An active database connection. */
	protected $DB;

	/** @var array Internal store of config settings. */
	protected $config = [
		'core' => [],
	];

	/**
	 * Set the internal database connection.
	 *
	 * @param \pdyn\database\DbDriverInterface &$DB A database driver.
	 */
	public function set_db(\pdyn\database\DbDriverInterface &$DB) {
		$this->DB = $DB;
	}

	/**
	 * Load settings from the database. Overrides non-locked values.
	 */
	public function load_database_settings() {
		$settings = $this->DB->get_recordset('config');
		foreach ($settings as $i => $setting) {
			try {
				$val = unserialize($setting['val']);
			} catch (\Exception $e) {
				$val = $setting['val'];
			}
			$this->config[$setting['component']][$setting['name']] = $val;
		}
	}

	/**
	 * Set a config value. Note: Cannot set locked value.
	 *
	 * @param string $name The name of the config setting.
	 * @param mixed $val The value to set.
	 */
	public function __set($name, $val) {
		$this->set('core', $name, $val, false);
	}

	/**
	 * Get a config value.
	 *
	 * @param string $name The name of the config setting.
	 * @return mixed The value.
	 */
	public function __get($name) {
		return $this->get('core', $name, null);
	}

	/**
	 * Determine whether a config value is set.
	 *
	 * @param string $name The name of the config setting.
	 * @return bool Whether a setting is set.
	 */
	public function __isset($name) {
		return (isset($this->config['core'][$name])) ? true : false;
	}

	/**
	 * Set a config value.
	 *
	 * @param string $component The component to set for.
	 * @param string $name The name of the config setting.
	 * @param mixed $value The value to set.
	 * @param bool $save Whether to save the value to the database.
	 */
	public function set($component, $name, $value, $save = true) {
		$this->config[$component][$name] = $value;
		$value = serialize($value);
		if ($save === true) {
			$existing = $this->DB->get_record('config', ['component' => $component, 'name' => $name]);
			if (empty($existing)) {
				$this->DB->insert_record('config', ['component' => $component, 'name' => $name, 'val' => $value]);
			} else {
				$this->DB->update_records('config', ['val' => $value], ['id' => $existing['id']]);
			}
		}
	}

	/**
	 * Get a config value.
	 *
	 * @param string $component The setting's component (plugin).
	 * @param string $name The name of the config setting.
	 * @param mixed $fallback A value to return if the setting is not found.
	 * @return mixed The config setting or the fallback.
	 */
	public function get($component, $name = null, $fallback = null) {
		if ($name === null) {
			if (isset($this->config[$component])) {
				return $this->config[$component];
			} else {
				return $fallback;
			}
		} else {
			if (isset($this->config[$component][$name])) {
				return $this->config[$component][$name];
			} else {
				return $fallback;
			}
		}
	}
}
