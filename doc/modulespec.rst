Files
=====

module.info.yml
    A metadata file for showing the various characteristics of the module. By
    using a static metadata file, it allows us to keep the module as lazy
    loaded as possible. For example, if a module is only watching for certain
    events and that event is never fired in the request, there is no reason to
    load that module for this request.

    See doc/module.info.yml for an example metadata file.

module.install.php
    A file containing all of the functions necessary to "install" the module.
    Things like creating database tables, initializating preferences, etc would
    be done here.

module.uninstall.php
    A file containing all of the functions necessary to "uninstall" the module,
    meaning that it should fully clean itself up while being uninstalled.
    Removing things like preferences, data from the database, permission names,
    etc should be done here so that the system is clean after the module is
    removed.

module.init.php
    A file containing all of the functions necessary to initialize the module.
    Things like initializing data, clearing caches, etc would go here. If the
    module is installed and active, this file will be loaded and ran, so make
    sure it's functionality is kept to a minimum, so that it doesn't affect
    every request's performance.

module.routes.php
    A file that defines the routes used by this module. Only necessary if the
    module has controllers and views that need to be displayed. If the file
    exists and the module is installed and active, this file will be loaded on
    every request, so keep it as small as possible for performance reasons.

RequestHandler.php
    If the module is defined as a "request_handler" in the metdata, this file
    will be loaded. Request handlers will be treated similar to Rack middleware
    in that a class named RequestHandler will be instantiated, and the method
    call(&$env) will be called. If the handler can handle the request, it can
    return an array of status code, header strings and body strings. If not,
    it can defer the call to the next request_handler in line. This class
    should extend \car\core\RequestHandler and live in the module's base
    namepsace.

    See doc/RequestHandler.php.txt for an example RequestHandler.

EventHandler.php
    If the module has defined itself to watch for events, then this class
    will be instantiated and called on for any fired event it's watching out
    for.  This class should extend \carl\core\EventHandler and live in the
    module's base namespace.

    See doc/EventHandler.php.txt for an example EventHandler.

PluginHandler.php
    If the module has defined itself as having plugins with callbacks, then
    this class will be instantiated and called on for any use of that plugin
    in Smarty templates. This class should extend \carl\core\PluginHandler
    and live in the module's base namespace.

    See doc/PluginHandler.php.txt for an example PluginHandler.

Directories
===========

models/
    All of the models classes for this module. They should extend
    \silk\model\Model and be annotatated properly w/ Doctrine annotations.
    For models to be migrated properly on installation, they should be defined
    as "auto_migrate" in the module.info.yml file.

controllers/
    All of the controller classes for this module. They should extend
    \silk\action\Controller and have properly set routes in the
    module.routes.php file. These are standard Silk controllers and don't act
    any differently in this context.

plugins/
    If your module has defined any plugins that aren't callbacks, they should
    be placed here. Plugins are defined using standard Smarty plugin
    conventions and will only work on Smarty enabled templates/output.

tests/
    If this module has a test suite, those tests should be placed in this
    directory.
