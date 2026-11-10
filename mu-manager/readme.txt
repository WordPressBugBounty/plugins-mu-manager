=== Mu Manager - Manage mu-plugins like standard plugins ===

Contributors:      giuse
Requires at least: 6.0
Tested up to: 7.1
Requires PHP:      8.0
Stable tag:        0.0.7
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Tags:              mu plugin, must use, must-use

It lets you disable, enable, delete, download, and edit mu-plugins as you do with the standard plugins.


== Description ==

With Mu Manager you can manage the mu-plugins like the standard plugins.
You can disable, enable, delete, download, and edit mu-plugins as you do with normal plugins.

The mu-plugins are the plugins that load before any standard plugin. In a normal WordPress installation they are located in the folder wp-content/mu-plugins.

Usually, WordPress doesn't allow you to deactivate the mu-plugins. With Mu Manager you can enable, disable, and delete mu-plugins without the need for FTP.

It's useful if you need to temporarily deactivate a mu-plugin, or to get rid of some mu-plugins installed by some hosting providers that you don't need.
Some hosting providers, such as HostGator or Bluehost, might automatically install for instance a must-use plugin that enables the Endurance Page Cache, which you totally don't need if you use a caching plugin.
With Mu Plugin Manager you can easily get rid of Endurance Page Cache, or whatever mu-plugin that you don't need.


== How to deactivate a mu-plugin ==
* Go to the page of Plugins
* Click on Must-Use
* Click on Deactivate on the row of the mu-plugin that you want to deactivate


== How to enable a previously disabled mu-plugin ==
* Go to the page of Plugins
* Click on Must-Use
* Click on Activate on the row of the mu-plugin that you want to activate


== How to delete a previously disabled mu-plugin ==
* Go to the page of Plugins
* Click on Must-Use
* Click on Delete on the row of the mu-plugin that you want to delete


== How to edit an mu-plugin ==
* Go to the page of Plugins
* Click on Must-Use
* Click on Edit on the row of the mu-plugin that you want to edit

Edit opens the same Plugin File Editor used by standard plugins (wp-admin/plugin-editor.php).


== How to download an mu-plugin ==
* Go to the page of Plugins
* Click on Must-Use
* Click on Download on the row of the mu-plugin that you want to download

The download is a zip archive. It contains the mu-plugin PHP file and, when present, the folder with the same name.


== Help ==
If you have any issues don't hesitate to open a thread on the support forum.

== Changelog ==

= 0.0.7 =
*Security: create only the requested editor bridge and harden bridge directories
*Security: gate bridge cleanup behind edit_plugins capability
*Security: remove editor bridges on uninstall
*Security: validate mu-plugin basenames and paths before rename/delete/download
*Security: block mu-plugin actions from sub-site admin on multisite
*Changed: permanently delete disabled mu-plugins instead of renaming them

= 0.0.6 =
*Changed: remove the editor links after an mu-plugin save has finished

= 0.0.5 =
*Added: edit mu-plugins with the Plugin File Editor

= 0.0.4 =
*Added: download action link on the Must-Use plugins screen

= 0.0.3 =
*Fix: MU Plugins page not showing if only no mu-plugins are active

= 0.0.2 =
*Added: hash to file extensions
*Fix: PHP warnings

= 0.0.1 =
*Initial release
