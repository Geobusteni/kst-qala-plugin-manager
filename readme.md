# Qala Plugin Manager :tada:
[[_TOC_]]

A Must Use plugin for Qala which lets us programmatically handle other plugins in our client sites.

This is useful in scenarios like if specific plugins must never be activated on a certain enviroment. Or vise versa, we always want certain plugins to be active on an environment.

It also extends plugin table with useful information such as which sites in a network the plugin is active etc.

For Angrycreative users, which have the `qala_full_access` capability it will also show information we find useful.

## Links :link:
* [Internal documentation](https://confluence.angrycreative.se/display/QALA/Qala+Plugin+Manager)

## Requirements and dependencies :white_check_mark:
* No dependencies.

## Changelog :scroll:
See [changelog](changelog.md)

## Actions and Filters :zap:
See [hooks](hooks.md)

## How to setup :bulb:
Simple install the plugin.
As an MU plugin it will always be active.

### Using
The usage for this is currently based on the codebase of the plugin itself. There are no WP admin settings or configurations that can be added from outside the plugin. This can and should likely change in the future but for now this is a POC version of this type of plugin.

If we need a certain plugin to always be deactivated or activated on a site, you simply edit the corresponding array values in the PluginConfigurations.php file.

The plugin will handle the rest.

There are also filters/actions to modify the values BUT they run right after Mu plugins have been loaded which means the hooks can be used only from another MU plugin and must be registered before the `muplugins_loaded` hook fires.

## Miscellaneous :speech_balloon:

## Common issues :beetle:
None so far.

## Development
### Deploying a tag
If you check the `.gitlab-ci.yml` file you'll see there's a basic build script that is run when you push or merge to master.

This build script will compile/transpile your ES6 and Scss and then commit the result to git.

The build script reads the version number of the plugin that you've defined in your `index.php` file. **You must therefore increase the version number in order to run the build as the build will fail if the tag already exists**.

The version number is per default `0.1.0`, so let's say you wanted to deploy a new tagged version of the plugin with the built assets: you'll need to first change the version number (let's say to `0.2.0`), then (following a most excellent code review of course) you will merge your code to master.

The build script will be run and in a few minutes you'll have a new tag in GitLab containing your built assets. :tada:
