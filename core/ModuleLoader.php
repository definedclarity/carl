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

use \carl\models\ModuleMetadata;

class ModuleLoader extends \silk\core\Object
{
	public static $module_list = null;
	public static $event_lookup = array();
	
	function __construct()
	{
		parent::__construct();
	}
	
	public static function loadModuleData()
	{
		$files = self::findModuleInfoFiles();
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
		}
		
		self::checkDependencies($module_list);
		self::checkUninstallAll($module_list);
		self::registerPlugins($module_list);
		self::registerEventHandlers($module_list);
		
		self::$module_list = $module_list;
	}
	
	public static function registerPlugins(&$module_list)
	{
		foreach ($module_list as &$one_module)
		{
			//self::check_uninstall($one_module['name'], $module_list);
			if (isset($one_module['plugins']))
			{
				foreach ($one_module['plugins'] as $k => $v)
				{
					if (isset($v['plugin']))
					{
						$val = $v['plugin'];
						if (isset($val['name']))
						{
							if (isset($val['callback']))
								CmsModuleFunctionProxy::getInstance()->register($one_module['name'], $val['name'], $val['callback']);
							else
								CmsModuleFunctionProxy::getInstance()->register($one_module['name'], $val['name'], 'function_plugin');
						}
					}
				}
			}
		}
	}
	
	public static function registerEventHandlers(&$module_list)
	{
		foreach ($module_list as &$one_module)
		{
			if (isset($one_module['events_watched']))
			{
				foreach ($one_module['events_watched'] as $event_name)
				{
					\silk\core\EventManager::registerEventHandler($event_name, '\carl\ModuleLoader::eventProxy');
					self::$event_lookup[$event_name][] = $one_module['name'];
				}
			}
		}
	}
	
	public static function eventProxy($event_name, $params)
	{
		if (isset(self::$event_lookup[$event_name]) && is_array(self::$event_lookup[$event_name]))
		{
			foreach (self::$event_lookup[$event_name] as $module_name)
			{
				/*
				$obj = self::getModuleClass($module_name);
				if (is_object($obj))
				{
					$obj->doEvent($event_name, $params);
				}
				*/
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
	
	public static function getModuleClass($name, $check_active = true, $check_deps = true)
	{
		if (self::$module_list != null)
		{
			//Make sure we can call modules without checking case
			$name = self::getProperModuleCase($name);
			
			if (isset(self::$module_list[$name]))
			{
				if ($check_active)
				{
					if (self::$module_list[$name]['active'] != true || self::$module_list[$name]['installed'] != true)
					{
						return null;
					}
				}
				
				if (isset(self::$module_list[$name]['object']))
				{
					return self::$module_list[$name]['object'];
				}
				else
				{
					require_once(joinPath(ROOT_DIR, 'modules', $name, $name . '.module.php'));
					if (class_exists($name) && (is_subclass_of($name, 'CmsModuleBase') || is_subclass_of($name, 'CmsModule')))
					{
						if ($check_deps && isset(self::$module_list[$name]['dependencies']))
						{
							foreach (self::$module_list[$name]['dependencies'] as $dep)
							{
								//If the dependency doesn't load, this one doesn't either.
								if (self::getModuleClass($dep['module']['name'], $check_active) == null)
									return null;
							}
						}
						
						self::$module_list[$name]['object'] = new $name();
						
						return self::$module_list[$name]['object'];
					}
				}
			}
		}
		
		return null;
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
			if ($one_row['module_name'] == $module_data['name'])
			{
				$module_data['installed'] = true;
				$module_data['active'] = ($one_row['active'] == '1' ? true : false);
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
	
	public static function findModuleInfoFiles()
	{
		$filelist = array();
		
		$dir = joinPath(ROOT_DIR, 'modules');
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

	function install($name)
	{
		if (self::getModuleInfo($name) !== false && !self::getModuleInfo($name, 'installed') && self::getModuleInfo($name, 'meets_dependencies'))
		{
			$version = self::getModuleInfo($name, 'version');

			/*
			$filename = joinPath(ROOT_DIR, 'modules', $name, 'method.install.php');
			if (@is_file($filename))
			{
				$module = self::getModuleClass($name, false, false);
				$module->include_file_in_scope($filename);
			}
			*/

			$module_obj = new ModuleMetadata();
			$module_obj->fillParameters(array('name' => $name, 'version' => $version, 'active' => true));
			$module_obj->save();

			$event_params = array('name' => $name, 'version' => $version);
			\silk\core\EventManager::sendEvent('carl:module:installed', $event_params);
		}
	}

	function uninstall($name)
	{
		if (self::getModuleInfo($name) !== false && self::getModuleInfo($name, 'installed') && self::getModuleInfo($name, 'can_uninstall'))
		{
			$version = self::getModuleInfo($name, 'installed_version');

			/*
			$filename = joinPath(ROOT_DIR, 'modules', $name, 'method.uninstall.php');
			if (@is_file($filename))
			{
				$module = CmsModuleLoader::get_module_class($name, false, false);
				$module->include_file_in_scope($filename);
			}
			*/

			$module_obj = ModuleMetadata::findOneBy(array('name' => $name));
			if ($module_obj)
				$module_obj->delete();

			$event_params = array('name' => $name, 'version' => $version);
			\silk\core\EventManager::sendEvent('carl:module:uninstalled', $event_params);
		}
	}

	function upgrade($name)
	{
		if (self::getModuleInfo($name) !== false && self::getModuleInfo($name, 'installed') && self::getModuleInfo($name, 'needs_upgrade'))
		{
			$old_version = self::getModuleInfo($name, 'installed_version');
			$new_version = self::getModuleInfo($name, 'version');

			/*
			$filename = joinPath(ROOT_DIR, 'modules', $name, 'method.upgrade.php');
			if (@is_file($filename))
			{
				$module = self::getModuleClass($name, false, false);
				$module->include_file_in_scope($filename);
			}
			*/

			$module_obj = ModuleMetadata::findOneBy(array('name' => $name));
			if ($module_obj)
			{
				$module_obj->version = $new_version;
				$module_obj->save();
			}

			$event_params = array('name' => $name, 'old_version' => $old_version, 'new_version' => $new_version);
			\silk\core\EventManager::sendEvent('carl:module:upgraded', $event_params);
		}
	}


	function activate($name)
	{
		if (self::getModuleInfo($name) !== false && self::getModuleInfo($name, 'installed') && !self::getModuleInfo($name, 'active'))
		{
			$module_obj = ModuleMetadata::findOneBy(array('name' => $name));
			if ($module_obj)
			{
				$module_obj->active = true;
				$module_obj->save();
			}
		}
	}

	function deactivate($name)
	{
		if (self::getModuleInfo($name) !== false && self::getModuleInfo($name, 'installed') && self::getModuleInfo($name, 'active'))
		{
			$module_obj = ModuleMetadata::findOneBy(array('name' => $name));
			if ($module_obj)
			{
				$module_obj->active = false;
				$module_obj->save();
			}
		}
	}
}

# vim:ts=4 sw=4 noet
