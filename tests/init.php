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

if (!defined('ROOT_DIR')) die();

addClassDirectory(joinPath(ROOT_DIR, 'vendor', 'modules'));
\carl\core\ModuleLoader::loadModuleData();

//Grab list of just installed and active modules -- we don't
//want tests to break because of missing dependencies
$list = \carl\core\ModuleLoader::getModuleList(false);
foreach($list as $one_module)
{
	try
	{
		\carl\core\ModuleLoader::uninstall($one_module['name']);
	}
	catch (\Exception $e) {}

	\carl\core\ModuleLoader::unloadModuleData();
	\carl\core\ModuleLoader::loadModuleData();

	try
	{
		\carl\core\ModuleLoader::install($one_module['name']);
	}
	catch (\Exception $e) {}
}

\carl\core\ModuleLoader::unloadModuleData();
\carl\core\ModuleLoader::loadModuleData();

$list = \carl\core\ModuleLoader::getModuleList('true');
foreach($list as $one_module)
{
	$dir_to_mod = joinPath(dirname($one_module['module_file']), 'tests');
	foreach($sub_dirs as $ext_dir)
	{
		$this->findAndAddTests($dir_to_mod, $ext_dir, $filter);
	}
}

# vim:ts=4 sw=4 noet
