<?php
namespace pdyn\config;

/**
 * Interface defining public footprint of ConfigStore object.
 */
interface ConfigStoreInterface {
	/**
	 * Set the internal database connection.
	 *
	 * @param \pdyn\database\DbDriverInterface &$DB A database driver.
	 */
	public function set_db(\pdyn\database\DbDriverInterface &$DB);

	/**
	 * Load settings from the database. Overrides non-locked values.
	 */
	public function load_database_settings();

	/**
	 * Set a config value.
	 *
	 * @param string $component The component to set for.
	 * @param string $name The name of the config setting.
	 * @param mixed $value The value to set.
	 * @param bool $save Whether to save the value to the database.
	 */
	public function set($component, $name, $value, $save = true);

	/**
	 * Get a config value.
	 *
	 * @param string $component The setting's component (plugin).
	 * @param string $name The name of the config setting.
	 * @param mixed $fallback A value to return if the setting is not found.
	 * @return mixed The config setting or the fallback.
	 */
	public function get($component, $name, $fallback = null);
}
