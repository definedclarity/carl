Carl
====

Carl is a dynamic module loader for the Silk Framework. It allows
you to build highly modular, large-scale applications while still
handling dependency checking, installs, upgrades, etc. It's an
extra layer of intelligence above the traditional extension model.

Features
--------

* Simple API for installing, upgrading and uninstalling the module
  cleanly.
* Ability to flag a module (and all of it's dependents) as inactive
  or active with simple API call.
* Controlled via simple .yml.
* Modules are fully lazy-loaded, so they're only invoked if needed.
* Ability for modules to define traditional Silk routes, controllers,
  etc., but also allow a module to fully parse the URL and return
  output.
* Can define plugins which will automatically load into Smarty (if
  used).

### What's coming?

* Modules distributed as .phar files
* Install and upgrade modules by giving Carl a .zip or .phar file
  and if permissions are incorrect, attempt via FTP.
* Circular dependency checking
* Other databases besides MongoDB using traditional Doctrine ORM

Requirements
------------

* Silk application
* Configured database (MongoDB at the moment -- will support regular
  Doctrine ORM as well, though)

Resources
---------

* [Issue Tracker][1]

Contributing
------------

1. Fork it.
2. Create a branch (`git checkout -b kick_ass_feature`)
3. Commit your changes (`git commit -am "Added Kick Ass Feature"`)
4. Push to the branch (`git push origin kick_ass_feature`)
5. Create an [Issue][1] with a link to your branch
6. Bask in the glory of your cleverness and wait

[1]: http://issues.silkframework.com/browse/CARL
