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

class EventHandler extends \silk\core\Object
{
	public function handleEvent($event_name, &$params)
	{
		self::handleEventStatic($event_name, $params);
	}

	public function handleEventStatic($event_name, &$params)
	{
		//Split the event out into filename of some sort
		$event_filename = str_replace(':', DS, $event_name) . '.php';
		$filename = ModuleLoader::getModuleFile($params['__calling_event_module__'], joinPath('events', $event_filename));
		if ($filename)
		{
			{
				@include($filename);
			}
		}
	}
}

# vim:ts=4 sw=4 noet
