#WPPM
##Composer-based package manager for WordPress
This libraries simplifies dependency management for WordPress plugins (and soon themes). 

When installed in a WordPress site, a WPPM-enabled plugin will automatically resolve conflicts with other Composer-based plugins.

If a plugin cannot be resolved, the user will be notified that there is a problem and instructed to either disable the plugin or 
talk to a developer to resolve it manually.

## Why is something like WPPM required?
Unless you have a fully composer-enabled WordPress install (eg: roots.io/bedrock), plugins will attempt to load
all of their dependencies. When two plugins include the same dependency (different versions), first-to-be-loaded wins.

If one of those plugins is not compatible with the loaded dependency, the site could completely break - or worse (IMO), 
 it could not show any errors until a visitor attempt to do something that uses a missing feature.

## How to use
To add Composer support to your plugin, you will need to do the following:
 
 1 - Add hotsource/wppm to your required packages.
 
 2 - Run composer install
 
 3 - Add the following code snippet to autoload your plugin:
  
  [code]
  require_once \_\_dir\_\_ . "/vendor/hotsource/wppm/wppm.php";
  if ( ! WPPM:autoload( __FILE__ ) )
      return;
  [/code]

## Other notes

WPPM::autoload() will return a boolean value indicating whether or not your plugin successfully loaded all dependencies.
If it returns false, it's best to simply "return;" as this would prevent your plugin from continuing to load.
A notification about the issue will be shown in the WP Admin area.