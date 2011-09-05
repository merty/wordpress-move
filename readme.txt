=== WordPress Move ===
Contributors: merty
Donate link: http://www.mertyazicioglu.com
Tags: domain, migrate, migration, move
Requires at least: 3.2
Tested up to: 3.2.1
Stable tag: 1.1

A migration assistant for WordPress capable of changing the domain name in use and/or migrating your installation to another server.

== Description ==

WordPress Move is a migration assistant for WordPress that is capable of changing the domain name in use and/or migrating your installation to another server either as is or based on your choices. In addition to these, you can use WordPress Move to transfer your database or create backups of your installation. For further information on using the plugin, please refer to the documentation provided with the plugin.

**Disclaimer:** Even though this plugin is heavily tested, please use it at your own risk and do not forget to back up your files beforehand.

== Installation ==

1. Upload `wordpress-move` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin through the WordPress Move page under the 'Settings' menu
4. Start using the tools added under the 'Tools' menu

== Frequently Asked Questions ==

= Do I need to install WordPress and WordPress Move on the new server as well, if I want to use WordPress Move for migration purposes? =

Yes you do.

= Is it possible to both migrate to another server and change the domain name? =

Yes. You can choose whether you want to change your domain name or not on the migration screen. Note that it does not change the domain name used by the current installation, it just replaces instances of the old domain name with the new one on the fly while creating a database backup for migration.

= Can I use WordPress Move to create backups of my installation? =

Yes, you can. Clicking the Backup Now button on the Backup Manager page will back up your database and files. Your backup files will be stored under the backup directory. You can use Complete Migration mode whenever you want to use those files to revert to a former state of your installation.

= Can I use WordPress Move to transfer my database backup only? =

Yes, you can. All you need to do is selecting the Advanced Migration during the migration type selection page and not selecting any files when the file tree is displayed. Once you click the Start Migration button, the plugin will create a backup of your database only and transfer it to your new server. When you run WordPress Move on your new server in Complete Migration mode, the plugin will import the database backup created by your old WordPress installation.

= Does WordPress Move take care of changing the whole domain name changing process? =

No, it does not. WordPress Move just replaces instances of your old domain name in your database with the new domain name you provide. It is still your responsibility to point your domain name to the name servers used by your hosting company and make necessary configurations on the control panel provided by your hosting company. Before starting this process, please request assistance from your hosting company as some companies' systems erase all your data without creating backups when you change your domain name. Also, do not forget that it is always a good idea to have a backup of your files and the database before starting operations like these.

= Plugin fails to create backup files because it says my backup directory is not writable. How can I fix this? =

As the warning suggests, you need to make the backup directory writable by the plugin. Permission settings vary from server to server so there is no specific value to set the directory permissions to. The easiest way to fix this problem yourself is using an FTP client to alter permission settings of the backup directory until plugin successfully creates backup files. You may also prefer requesting assistance from your hosting company.

== Changelog ==

= 1.1 =
* Added "Backup Now" functionality to Backup Manager.
* Added the capability of migrating and changing the domain name at the same time.

= 1.0 =
* Initial release.

== Upgrade Notice ==

= 1.1 =
You can now create a full backup of your installation using the Backup Now button on the Backup Manager page. Also, migrating and changing the domain name at the same time is now supported.

= 1.0 =
Initial release.