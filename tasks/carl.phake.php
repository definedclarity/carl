<?php // -*- mode:php; tab-width:4; indent-tabs-mode:t; c-basic-offset:4; -*-
// The MIT License
// 
// Copyright (c) 2008-2011 Ted Kulp
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

group('carl', function()
{
	desc('Lists the modules we have access to');
	task('list', function($app)
	{
		\carl\core\ModuleLoader::loadModuleData();
		$list = \carl\core\ModuleLoader::getModuleList();
		foreach ($list as $one_module)
		{
			echo "Name: {$one_module['name']} | ";
			echo "Version: {$one_module['version']} | ";
			echo "Installed/Active: " . ($one_module['installed']?'true':'false') . "/" . ($one_module['active']?'true':'false') . "\n";
		}
		echo "\n";
	});

	desc('Install a module');
	task('install', function($app)
	{
		\carl\core\ModuleLoader::loadModuleData();
		if (isset($app['arg0']))
		{
			try
			{
				if (\carl\core\ModuleLoader::install($app['arg0']))
				{
					echo "Module installed.\n";
				}
				else
				{
					echo "Module install failed.\n";
				}
			}
			catch (\carl\exceptions\LoaderException $e)
			{
				echo $e->getMessage() . "\n";
			}
		}
		else
		{
			echo "No module name given.  Exiting.\n";
		}
	});

	desc('Uninstall a module');
	task('uninstall', function($app)
	{
		\carl\core\ModuleLoader::loadModuleData();
		if (isset($app['arg0']))
		{
			try
			{
				if (\carl\core\ModuleLoader::uninstall($app['arg0']))
				{
					echo "Module uninstalled.\n";
				}
				else
				{
					echo "Module uninstall failed.\n";
				}
			}
			catch (\carl\exceptions\LoaderException $e)
			{
				echo $e->getMessage() . "\n";
			}
		}
		else
		{
			echo "No module name given.  Exiting.\n";
		}
	});

	desc('Upgrade a module');
	task('upgrade', function($app)
	{
		\carl\core\ModuleLoader::loadModuleData();
		if (isset($app['arg0']))
		{
			try
			{
				if (\carl\core\ModuleLoader::upgrade($app['arg0']))
				{
					echo "Module upgraded.\n";
				}
				else
				{
					echo "Module upgrade failed.\n";
				}
			}
			catch (\carl\exceptions\LoaderException $e)
			{
				echo $e->getMessage() . "\n";
			}
		}
		else
		{
			echo "No module name given.  Exiting.\n";
		}
	});
});

# vim:ts=4 sw=4 noet filetype=php
