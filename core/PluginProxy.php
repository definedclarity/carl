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

class PluginProxy extends \silk\core\Singleton
{
	var $plugin_lookup = array();
	
	public function register($module_name, $type = 'function', $name, $callback = '', $filename = '')
	{
		smarty()->registerPlugin($type, $name, array($this, $name));
		$this->plugin_lookup[$name] = array($module_name, $callback);
	}
	
	public function __call($function, $variables)
	{
		$params = $variables[0];
		$smarty = $variables[1];
		
		$lookup = $this->plugin_lookup[$function];

		$module = ModuleLoader::getPluginHandler($lookup[0]);
		if ($module)
		{
			$func_name = $lookup[1];
			if ($func_name == '')
				$func_name = $function;

			if (is_callable(array($module, $func_name)))
			{
				return call_user_func_array(array($module, $func_name), array($params, &$smarty));
			}
			else
			{
				return call_user_func_array(array($module, 'handlePlugin'), array($params, &$smarty));
			}
		}
	}
}

# vim:ts=4 sw=4 noet
