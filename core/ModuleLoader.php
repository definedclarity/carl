<?php // -*- mode:php; tab-width:4; indent-tabs-mode:t; c-basic-offset:4; -*-
//
// carl -- A convenience library for the Silk Framework
// Copyright (c) 2008-2011 Defined Clarity
//
// The MIT License
//
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in
// all copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
// THE SOFTWARE.

namespace carl\core;

use \carl\exceptions\LoaderException;
use \carl\models\ModuleMetadata;

class ModuleLoader extends \silk\core\Object
{
	public static $module_list = null;
	public static $event_lookup = array();
	public static $plugin_handlers = array();
	public static $plugin_registrations = array();
	
	function __construct()
	{
		parent::__construct();
	}
	
	public static function loadModuleData($module_dir = '')
	{
		$files = self::findModuleInfoFiles($module_dir);
		$installed_data = self::getInstalledModuleDetails();
		$module_list = array();
		foreach ($files as $one_file)
		{
			if (is_array($one_file))
				$module_data = $one_file;
			else if (endsWith($one_file, '.yml'))
				$module_data = self::ymlifyModuleInfoFile($one_file);
			
			$module_data = self::injectInstalledDataForModule($module_data, $installed_data);
			$module_list[$module_data['name']] = $module_data;
			$module_list[$module_data['name']]['module_file'] = $one_file;
		}

		self::checkDependencies($module_list);
		self::checkUninstallAll($module_list);
		
		self::$module_list = $module_list;

		self::registerPlugins();
		self::registerEventHandlers();
		self::initInstalledModules();
	}

	public static function unloadModuleData()
	{
		self::$module_list = null;
		self::$event_lookup = array();
		self::$plugin_handlers = array();
		foreach (self::$plugin_registrations as $one)
		{
			smarty()->unregisterPlugin($one[0], $one[1]);
		}
		self::$plugin_registrations = array();
	}
	
	public static function registerPlugins()
	{
		$module_list = self::getModuleList(true);
		foreach ($module_list as $one_module)
		{
			$dir = joinPath(self::getModuleDirectory($one_module['name']), 'plugins');
			if (is_dir($dir))
			{
				if (!in_array($dir, smarty()->plugins_dir))
				{
					smarty()->plugins_dir[] = $dir;
				}
			}
			if (isset($one_module['plugins']))
			{
				foreach ($one_module['plugins'] as $val)
				{
					if (isset($val['name']))
					{
						$type = 'function';
						if (isset($val['type']))
							$type = $val['type'];

						self::$plugin_registrations[] = array($type, $val['name']);

						if (isset($val['callback']))
							PluginProxy::getInstance()->register($one_module['name'], $type, $val['name'], $val['callback']);
						else
							PluginProxy::getInstance()->register($one_module['name'], $type, $val['name']);
					}
				}
			}
		}
	}

	public static function getPluginHandler($module_name)
	{
		if (in_array($module_name, self::$plugin_handlers))
		{
			return self::$plugin_handlers[$module_name];
		}

		$filename = self::getModuleFile($module_name, 'PluginHandler.php');
		if ($filename)
		{
			{
				//We don't check the result -- we just run it and hope it doesn't crash
				$class_name = joinNamespace(self::getModuleInfo($module_name, 'namespace'), 'PluginHandler');
				if (!class_exists($class_name))
				{
					include_once($filename);
				}

				if (class_exists($class_name))
				{
					$class = new $class_name;
					if ($class)
					{
						self::$plugin_handlers[$module_name] = $class;
						return $class;
					}
				}
			}
		}

		return null;
	}
	
	public static function registerEventHandlers()
	{
		$module_list = self::getModuleList(true);
		foreach ($module_list as $one_module)
		{
			if (isset($one_module['events_watched']))
			{
				foreach ($one_module['events_watched'] as $event_name)
				{
					\silk\core\EventManager::registerEventHandler($event_name, '\carl\core\ModuleLoader::eventProxy');
					self::$event_lookup[$event_name][] = $one_module['name'];
				}
			}
		}
	}
	
	public static function eventProxy($event_name, &$params)
	{
		if (isset(self::$event_lookup[$event_name]) && is_array(self::$event_lookup[$event_name]))
		{
			foreach (self::$event_lookup[$event_name] as $module_name)
			{
				$params['__calling_event_module__'] = $module_name;
				$filename = self::getModuleFile($module_name, 'EventHandler.php');
				if ($filename)
				{
					{
						//We don't check the result -- we just run it and hope it doesn't crash
						$class_name = joinNamespace(self::getModuleInfo($module_name, 'namespace'), 'EventHandler');
						if (!class_exists($class_name))
						{
							@include_once($filename);
						}

						if (class_exists($class_name))
						{
							$class = new $class_name;
							if ($class)
							{
								$class->handleEvent($event_name, $params);
							}
						}
					}
				}
				else
				{
					//They didn't make a specific EventHandler -- just call the core one and see
					//if they used individual files instead.
					{
						\carl\core\EventHandler::handleEventStatic($event_name, $params);
					}
				}
				unset($params['__calling_event_module__']);
			}
		}
	}
	
	public static function checkUninstallAll(&$module_list)
	{
		foreach ($module_list as &$one_module)
		{
			self::checkUninstall($one_module['name'], $module_list);
		}
	}
	
	public static function checkUninstall($module_name, &$module_list)
	{
		if (isset($module_list[$module_name]))
		{
			if (!isset($module_list[$module_name]['can_uninstall']))
			{
				$module_list[$module_name]['can_uninstall'] = true;
				if (isset($module_list[$module_name]['dependencies']))
				{
					foreach ($module_list[$module_name]['dependencies'] as $one_dep)
					{
						$dep_mod = $module_list[$one_dep['name']];
						self::checkUninstall($one_dep['name'], $module_list);

						if ($dep_mod && $dep_mod['installed'])
						{
							$module_list[$module_name]['can_uninstall'] = false;
						}
					}
				}
			}
		}
	}
	
	public static function checkDependencies(&$module_list)
	{
		foreach ($module_list as $one_module)
		{
			self::checkDependenciesForModule($one_module['name'], $module_list);
		}
	}
	
	public static function checkDependenciesForModule($module_name, &$module_list)
	{
		//Make sure we haven't done this one yet -- no point in repeating
		if (!isset($module_list[$module_name]['meets_dependencies']))
		{
			$module_list[$module_name]['meets_dependencies'] = true;
			
			if (isset($module_list[$module_name]['dependencies']) && is_array($module_list[$module_name]['dependencies']))
			{
				for ($i = 0; $i < count($module_list[$module_name]['dependencies']); $i++)
				{
					//If a module dependency (only kind for now)
					if (isset($module_list[$module_name]['dependencies'][$i]))
					{
						$one_dep = $module_list[$module_name]['dependencies'][$i];

						//Does this dependency exist at all?
						if (isset($module_list[$one_dep['name']]))
						{
							//Make sure we process any dependencies first
							self::checkDependenciesForModule($one_dep['name'], $module_list);
						
							//Now that it's processed, check for active and installed_version stuff
							if ($module_list[$one_dep['name']]['active'] == false)
							{
								$module_list[$module_name]['meets_dependencies'] = false;
								$module_list[$module_name]['active'] = false;
							}
							else if (isset($one_dep['minimum_version']) && version_compare($one_dep['minimum_version'], $module_list[$one_dep['name']]['installed_version'], '>'))
							{
								$module_list[$module_name]['meets_dependencies'] = false;
								$module_list[$module_name]['active'] = false;
							}
						}
						else
						{
							$module_list[$module_name]['meets_dependencies'] = false;
							$module_list[$module_name]['active'] = false;
						}
					}
				}
			}
		}
	}
	
	public static function getProperModuleCase($name)
	{
		if (self::$module_list != null)
		{
			foreach (self::$module_list as $k=>$v)
			{
				if (strtolower($k) == strtolower($name))
				{
					return $k;
				}
			}
		}
		
		return $name;
	}
	
	public static function getModuleList($only_installed_and_active = false)
	{
		if (!$only_installed_and_active)
		{
			return self::$module_list;
		}
		else
		{
			$modules = array();

			foreach (self::$module_list as $one_module)
			{
				if (self::isInstalled($one_module['name']) && self::isActive($one_module['name']))
				{
					$modules[] = $one_module;
				}
			}

			return $modules;
		}
	}

	public static function getModuleInfo($name, $key = '')
	{
		if ($key != '')
		{
			if (isset(self::$module_list[$name]) && isset(self::$module_list[$name][$key]))
				return self::$module_list[$name][$key];
		}
		else
		{
			if (isset(self::$module_list[$name]))
				return self::$module_list[$name];
		}
		
		return false;
	}

	public static function getModuleDirectory($name)
	{
		$dir = self::getModuleInfo($name, 'module_file');
		if (!$dir)
			$dir = '';
		return dirname($dir);
	}
	
	public static function getModuleFile($name, $filename = '')
	{
		$dir = self::getModuleDirectory($name);
		if ($dir != '')
		{
			$filename = joinPath($dir, $filename);
			if ($filename && is_file($filename))
			{
				return $filename;
			}
		}

		return '';
	}
	
	public static function isInstalled($name)
	{
		return self::getModuleInfo($name, 'installed');
	}
	
	public static function isActive($name)
	{
		return self::getModuleInfo($name, 'active');
	}
	
	public static function getInstalledModuleDetails()
	{
		return ModuleMetadata::findAll();
	}
	
	public static function hasCapability($capability_name, $module_name = '')
	{
		$modules = array();
		
		foreach (self::$module_list as $one_module)
		{
			if (($module_name == '' || $module_name == $one_module['name']) && isset($one_module['capabilities']))
			{
				if (self::isInstalled($one_module['name']) && self::isActive($one_module['name']))
				{
					foreach ($one_module['capabilities'] as $k => $one_item)
					{
						if ($one_item == $capability_name)
						{
							$modules[] = $one_module['name'];
						}
					}
				}
			}
		}
		
		return $modules;
	}
	
	public static function injectInstalledDataForModule($module_data, $installed_data)
	{
		$module_data['installed'] = false;
		$module_data['active'] = false;
		$module_data['installed_version'] = $module_data['version'];
		$module_data['needs_upgrade'] = false;
		
		foreach($installed_data as $one_row)
		{
			if ($one_row['name'] == $module_data['name'])
			{
				$module_data['installed'] = true;
				$module_data['active'] = $one_row['active'];
				$module_data['installed_version'] = $one_row['version'];
				$module_data['needs_upgrade'] = version_compare($module_data['installed_version'], $one_row['version'], '<');
			}
		}
		
		return $module_data;
	}
	
	public static function ymlifyModuleInfoFile($file)
	{
		$cache = get('cache');
		$file_mod_time = filemtime($file);

		if ($cache->contains('module_metadata:' . str_replace('.info.yml', '', basename($file))))
		{
			list($ary, $db_mod_time) = $cache->fetch('module_metadata:' . str_replace('.info.yml', '', basename($file)));
			
			if ($db_mod_time != null && $file_mod_time <= $db_mod_time)
			{
				if ($ary)
					return $ary;
			}
		}
		
		$yml = \silk\format\Yaml::loadFile($file);
		
		$cache->save('module_metadata:' . str_replace('.info.yml', '', basename($file)), array($yml, $file_mod_time));
		
		return $yml;
	}
	
	public static function findModuleInfoFiles($module_dir = '')
	{
		$filelist = array();
		
		$dir = joinPath(ROOT_DIR, 'vendor', 'modules');
		if ($module_dir != '')
			$dir = $module_dir;

		if (is_dir($dir))
		{
			if ($dh = opendir($dir))
			{
				while (($file = readdir($dh)) !== false)
				{
					if ($file != '.' && $file != '..')
					{
						$mod_dir = joinPath($dir, $file);
						if (is_dir($mod_dir))
						{
							$mod_info_file = joinPath($dir, $file, 'module.info');
							if (is_file($mod_info_file . '.yml') && is_readable($mod_info_file . '.yml'))
							{
								$filelist[] = $mod_info_file . '.yml';
							}
						}
					}
				}
				closedir($dh);
			}
		}
		
		return $filelist;
	}

	function install($name, $include_file = 'module.install.php')
	{
		if (self::getModuleInfo($name) === false)
		{
			throw new LoaderException('Module doesn\'t exist');
		}
		else if (self::isInstalled($name))
		{
			throw new LoaderException('Module is already installed');
		}
		else if (!self::getModuleInfo($name, 'meets_dependencies'))
		{
			throw new LoaderException('Module does not meet dependencies');
		}
		else
		{
			$version = self::getModuleInfo($name, 'version');

			$module_obj = new ModuleMetadata();
			$module_obj->fillParameters(array('name' => $name, 'version' => $version, 'active' => true));
			if ($module_obj->isValid())
			{
				//Do we have an install file?
				$filename = self::getModuleFile($name, $include_file);
				if ($filename)
				{
					try
					{
						@include($filename);
					}
					catch (\Exception $e)
					{
						throw new LoaderException($include_file . ' failed to load');
					}
				}

				//We made it this far -- call it installed
				if ($module_obj->save())
				{
					$event_params = array('name' => $name, 'version' => $version);
					\silk\core\EventManager::sendEvent('carl:module:installed', $event_params);

					return true;
				}
				else
				{
					throw new LoaderException('Could not save module metadata');
				}
			}
		}

		return false;
	}

	function uninstall($name, $include_file = 'module.uninstall.php')
	{
		if (self::getModuleInfo($name) === false)
		{
			throw new LoaderException('Module doesn\'t exist');
		}
		else if (!self::isInstalled($name))
		{
			throw new LoaderException('Module is not installed');
		}
		else if (!self::getModuleInfo($name, 'can_uninstall'))
		{
			throw new LoaderException('Module cannot be uninstalled');
		}
		else
		{
			$version = self::getModuleInfo($name, 'installed_version');

			$module_obj = ModuleMetadata::findOneBy(array('name' => $name));
			if ($module_obj)
			{
				//Do we have an uninstall file?
				$filename = self::getModuleFile($name, $include_file);
				if ($filename)
				{
					try
					{
						@include($filename);
					}
					catch (\Exception $e)
					{
						throw new LoaderException($include_file . ' failed to load');
					}
				}

				//Doctrine doesn't tell us if the delete was a success, so we assume
				//that it was -- we know the object is there.
				$module_obj->delete();

				$event_params = array('name' => $name, 'version' => $version);
				\silk\core\EventManager::sendEvent('carl:module:uninstalled', $event_params);

				return true;
			}
		}

		return false;
	}

	function upgrade($name, $include_file = 'module.upgrade.php')
	{
		if (self::getModuleInfo($name) === false)
		{
			throw new LoaderException('Module doesn\'t exist');
		}
		else if (!self::isInstalled($name))
		{
			throw new LoaderException('Module is not installed');
		}
		else if (!self::getModuleInfo($name, 'needs_upgrade'))
		{
			throw new LoaderException('Module is already the latest version');
		}
		else
		{
			$old_version = self::getModuleInfo($name, 'installed_version');
			$new_version = self::getModuleInfo($name, 'version');

			$module_obj = ModuleMetadata::findOneBy(array('name' => $name));
			if ($module_obj)
			{
				//Do we have an upgrade file?
				$filename = self::getModuleFile($name, $include_file);
				if ($filename)
				{
					try
					{
						@include($filename);
					}
					catch (\Exception $e)
					{
						throw new LoaderException($include_file . ' failed to load');
					}
				}

				$module_obj['version'] = $new_version;
				if ($module_obj->save())
				{
					$event_params = array('name' => $name, 'old_version' => $old_version, 'new_version' => $new_version);
					\silk\core\EventManager::sendEvent('carl:module:upgraded', $event_params);

					return true;
				}
				else
				{
					throw new LoaderException('Could not save module metadata');
				}
			}
		}

		return false;
	}

	function activate($name)
	{
		if (self::getModuleInfo($name) !== false && self::isInstalled($name) && !self::getModuleInfo($name, 'active'))
		{
			$module_obj = ModuleMetadata::findOneBy(array('name' => $name));
			if ($module_obj)
			{
				$module_obj['active'] = true;
				if ($module_obj->save())
				{
					$event_params = array('name' => $name);
					\silk\core\EventManager::sendEvent('carl:module:activated', $event_params);

					return true;
				}
			}
		}

		return false;
	}

	function deactivate($name)
	{
		if (self::getModuleInfo($name) !== false && self::isInstalled($name) && self::getModuleInfo($name, 'active'))
		{
			$module_obj = ModuleMetadata::findOneBy(array('name' => $name));
			if ($module_obj)
			{
				$module_obj['active'] = false;
				if ($module_obj->save())
				{
					$event_params = array('name' => $name);
					\silk\core\EventManager::sendEvent('carl:module:deactivated', $event_params);

					return true;
				}
			}
		}

		return false;
	}

	function initInstalledModules($include_file = 'module.init.php')
	{
		$modules = self::getModuleList(true);
		foreach ($modules as $one_module)
		{
			$filename = self::getModuleFile($one_module['name'], $include_file);
			if ($filename)
			{
				{
					//We don't check the result -- we just run it and hope it doesn't crash
					@include($filename);
				}
			}
		}
	}
}

# vim:ts=4 sw=4 noet
