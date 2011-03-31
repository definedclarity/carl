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

use \silk\test\TestCase;
use \carl\core\ModuleLoader;
use \carl\models\ModuleMetadata;

class ModuleTest extends TestCase
{
	public function beforeTest()
	{
		ModuleMetadata::migrate();
		ModuleLoader::loadModuleData(joinPath(dirname(dirname(__FILE__)), 'modules'));
	}

	public function afterTest()
	{
		ModuleLoader::unloadModuleData();
		ModuleMetadata::dropTable();
	}

	public function reloadModules()
	{
		ModuleLoader::unloadModuleData();
		ModuleLoader::loadModuleData(joinPath(dirname(dirname(__FILE__)), 'modules'));
	}

	public function testInstall()
	{
		$all = ModuleMetadata::findAll();
		$this->assertEquals(0, count($all));
		$this->assertFalse(ModuleLoader::isInstalled('Test'));

		ModuleLoader::install('Test');
		$this->reloadModules();

		$all = ModuleMetadata::findAll();
		$this->assertEquals(1, count($all));
		$this->assertTrue(ModuleLoader::isInstalled('Test'));

		//Make sure it can't be reinstalled
		try
		{
			ModuleLoader::install('Test');
		}
		catch (\carl\exceptions\LoaderException $e) {}
		$this->reloadModules();

		$all = ModuleMetadata::findAll();
		$this->assertEquals(1, count($all));
		$this->assertTrue(ModuleLoader::isInstalled('Test'));
	}

	public function testUninstall()
	{
		$all = ModuleMetadata::findAll();
		$this->assertEquals(0, count($all));
		$this->assertFalse(ModuleLoader::isInstalled('Test'));

		ModuleLoader::install('Test');
		$this->reloadModules();

		$all = ModuleMetadata::findAll();
		$this->assertEquals(1, count($all));
		$this->assertTrue(ModuleLoader::isInstalled('Test'));

		ModuleLoader::uninstall('Test');
		$this->reloadModules();

		$all = ModuleMetadata::findAll();
		$this->assertEquals(0, count($all));
		$this->assertFalse(ModuleLoader::isInstalled('Test'));

		//Make sure it cleanly doesn't try to re-uninstall
		try
		{
			ModuleLoader::uninstall('Test');
		}
		catch (\carl\exceptions\LoaderException $e) {}
		$this->reloadModules();

		$all = ModuleMetadata::findAll();
		$this->assertEquals(0, count($all));
		$this->assertFalse(ModuleLoader::isInstalled('Test'));
	}

	public function testActive()
	{
		ModuleLoader::install('Test');
		$this->reloadModules();

		$this->assertTrue(ModuleLoader::isActive('Test'));

		ModuleLoader::deactivate('Test');
		$this->reloadModules();
		$this->assertFalse(ModuleLoader::isActive('Test'));

		ModuleLoader::activate('Test');
		$this->reloadModules();
		$this->assertTrue(ModuleLoader::isActive('Test'));

		ModuleLoader::uninstall('Test');
		$this->reloadModules();
	}

	public function testInstallUninstallFiles()
	{
		$cache = get('cache');

		//Just in case
		$cache->delete('install_test_thing');
		$this->assertFalse($cache->contains('install_test_thing'));

		//Install file sets the thing in the cache
		ModuleLoader::install('Test');
		$this->reloadModules();
		$this->assertTrue($cache->contains('install_test_thing'));

		//Uninstall file removes the thing from the cache
		ModuleLoader::uninstall('Test');
		$this->reloadModules();
		$this->assertfalse($cache->contains('install_test_thing'));

		//Just in case
		$cache->delete('install_test_thing');
	}

	public function testModuleInit()
	{
		$cache = get('cache');

		//Just in case
		$cache->delete('init_test_thing');
		$this->assertFalse($cache->contains('init_test_thing'));

		ModuleLoader::install('Test');
		$this->reloadModules();
		$this->assertTrue($cache->contains('init_test_thing'));

		ModuleLoader::uninstall('Test');
		$this->reloadModules();
	}

	public function testModuleLazyLoadEventWatcher()
	{
		$cache = get('cache');

		//Just in case
		$cache->delete('event_test_thing');
		$this->assertFalse($cache->contains('event_test_thing'));

		ModuleLoader::install('Test');
		$this->reloadModules();

		$my_value = 'my_value_here_too';
		$event_params = array('my_value' => $my_value);
		\silk\core\EventManager::sendEvent('some_module:some_event', $event_params);
		$this->assertTrue($cache->contains('event_test_thing'));
		$this->assertEquals($my_value, $cache->fetch('event_test_thing'));

		ModuleLoader::uninstall('Test');
		$this->reloadModules();

		$cache->delete('event_test_thing');
		$this->assertFalse($cache->contains('event_test_thing'));
	}

	public function testEventsInSeparateFiles()
	{
		$cache = get('cache');

		//Just in case
		$cache->delete('event_test_other_thing');
		$this->assertFalse($cache->contains('event_test_other_thing'));

		ModuleLoader::install('Test');
		$this->reloadModules();

		$my_value = 'my_value_here_too';
		$event_params = array('my_value' => $my_value);
		\silk\core\EventManager::sendEvent('some_module:some_separate_event', $event_params);
		$this->assertTrue($cache->contains('event_test_other_thing'));
		$this->assertEquals($my_value, $cache->fetch('event_test_other_thing'));

		ModuleLoader::uninstall('Test');
		$this->reloadModules();

		$cache->delete('event_test_other_thing');
		$this->assertFalse($cache->contains('event_test_other_thing'));
	}

	public function testPluginRegistration()
	{
		ModuleLoader::install('Test');
		$this->reloadModules();

		$this->assertEquals("Default Handler Works!", smarty()->fetch('eval:{test_default_plugin}'));
		$this->assertEquals("Physical Plugin Works!", smarty()->fetch('eval:{test_physical_plugin}'));
		$this->assertEquals("Virtual Plugin Works!", smarty()->fetch('eval:{test_virtual_plugin}'));

		ModuleLoader::uninstall('Test');
		$this->reloadModules();
	}
}

# vim:ts=4 sw=4 noet
