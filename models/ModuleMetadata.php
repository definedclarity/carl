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

namespace carl\models;

/**
 * @Document(collection="carl_module_metadata")
 */
class ModuleMetadata extends \silk\model\Model
{
	/**
	 * @Id(strategy="UUID")
	 **/
	protected $id;

	/**
	 * @Field
	 * @Validation:NotEmpty
	 **/
	protected $name;

	/**
	 * @Field
	 * @Validation:NotEmpty
	 **/
	protected $version;

	/**
	 * @Field(type="boolean")
	 **/
	protected $active;

	/**
	 * @Field(type="date")
	 **/
	protected $createDate;

	/**
	 * @Field(type="date")
	 **/
	protected $modifiedDate;
}

# vim:ts=4 sw=4 noet