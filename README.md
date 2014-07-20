WordPress Move
==========

WordPress Move enables you to back up your installation to restore to at any time, change the domain name in use and migrate your installation to another server.

Description
---------

WordPress Move is a migration assistant for WordPress that is capable of changing the domain name in use and/or migrating your installation to another server either as is or based on your choices. In addition to these, you can use WordPress Move to transfer your database or create backups of your installation. For further information on using the plugin, please refer to the documentation provided with the plugin.

**Disclaimer:** Even though this plugin is heavily tested, please use it at your own risk and do not forget to back up your files beforehand.

Installation
---------

**1.** Upload `wordpress-move` to the `/wp-content/plugins/` directory

**2.** Activate the plugin through the 'Plugins' menu in WordPress

**3.** Configure the plugin through the WordPress Move page under the 'Settings' menu

**4.** Start using the tools added under the 'Tools' menu


Frequently Asked Questions
----------------------

**I am getting the "Could not activate the plugin because it generated a fatal error." error when I try to activate the plugin. Why?**

WordPress Move needs php_sockets extension to be enabled, in order to work properly. If you are getting this error message, please enable php_sockets extension and restart your web server. Once you successfully enable the extension, you will be able to activate the plugin.

**Do I need to install WordPress and WordPress Move on the new server as well, if I want to use WordPress Move for migration purposes?**

Yes you do.

**Is it possible to both migrate to another server and change the domain name?**

Yes. You can choose whether you want to change your domain name or not on the migration screen. Note that it does not change the domain name used by the current installation, it just replaces instances of the old domain name with the new one on the fly while creating a database backup for migration.

**Can I use WordPress Move to create backups of my installation?**

Yes, you can. Click either "Create a Database Backup" button or "Create a Full Backup" button to create a backup. Your backup files will be stored under the backup directory. You can use Complete Migration mode whenever you want to use those files to revert to a former state of your installation.

**Can I use WordPress Move to transfer my database backup only?**

Yes, you can. All you need to do is selecting the Advanced Migration during the migration type selection page and not selecting any files when the file tree is displayed. Once you click the Start Migration button, the plugin will create a backup of your database only and transfer it to your new server. When you run WordPress Move on your new server in Complete Migration mode, the plugin will import the database backup created by your old WordPress installation.

**Does WordPress Move take care of changing the whole domain name changing process?**

No, it does not. WordPress Move just replaces instances of your old domain name in your database with the new domain name you provide. It is still your responsibility to point your domain name to the name servers used by your hosting company and make necessary configurations on the control panel provided by your hosting company. Before starting this process, please request assistance from your hosting company as some companies' systems erase all your data without creating backups when you change your domain name. Also, do not forget that it is always a good idea to have a backup of your files and the database before starting operations like these.

**Plugin fails to create backup files because it says my backup directory is not writable. How can I fix this?**

As the warning suggests, you need to make the backup directory writable by the plugin. Permission settings vary from server to server so there is no specific value to set the directory permissions to. The easiest way to fix this problem yourself is using an FTP client to alter permission settings of the backup directory until plugin successfully creates backup files. You may also prefer requesting assistance from your hosting company.

**I am a pre-1.2 user, what will happen to the FTP Password that is already stored in my database?**

Visiting the WordPress Move Settings page any time after updating the plugin will remove it from the database permanently.

**Can I use the database backup files that WordPress Move generates with phpMyAdmin?**

You can convert a database backup file using the Convert option in the Backup Manager to use it outside the plugin. So, yes, you can use the *converted* database backup files with phpMyAdmin or any other script.

Changelog
--------

**1.3.2**

* Fixed the bug causing problems with other plugins such as Gravity Forms.

**1.3.1**

* Fixed several bugs.

* Improved the performance of the plugin in complex tasks.

* It is now possible to download a backup file by clicking on its name.

* If the Safe Mode is disabled, operations will not be interrupted by the maximum execution time error anymore.

**1.3**

* Explanation for Change Domain Name is rephrased.

* Simple and Advanced Migration methods are merged.

* Meta boxes are added to the migration page.

* A database backup converter is integrated to convert WordPress-Move-only database backup files to generic SQL files.

* Backup files to use for restoration can now be selected right on the Restore page.

* Messages are now displayed in real-time on migration and restoration pages.

* Empty HTML files are added to backup directories to prevent them being listed by people trying to access the directory via their browsers.


**1.2**

* FTP Passwords are no longer stored in the database, for security reasons. Visit the WordPress Move Settings page after updating the plugin to remove it from the database permanently.

* It is now possible to create either a full backup or a database backup, using Backup Manager.

* Fixed another PHP Catchable Fatal Error some people encounter.

* Plugin is now really able to check whether importing the database backup was successful or not.

* Explanations on the Migration Assistant page are replaced with more clear ones.

* Added meta boxes to the Migration Assistant.


**1.1.1**

* Transients are no longer included in database backups to reduce the database backup files' sizes.

* Backup files created before changing the domain name are now being stored under the old backup directory for a possible future need.

* Fixed the PHP Catchable Fatal Error some people encounter.

* Added meta boxes to the settings page.


**1.1**

* Added "Backup Now" functionality to Backup Manager.

* Added the capability of migrating and changing the domain name at the same time.


**1.0**

* Initial release.

Upgrade Notice
------------

**1.3.2**

The bug that was causing problems with other plugins has been fixed. Previous releases were omitting NULL fields and causing data loss as a result.

**1.3.1**

Performance has been improved and several bugs have been fixed. Also, if the Safe Mode is disabled, operations will not be interrupted by the maximum execution time error anymore.

**1.3**

Simple and Advanced Migration methods are merged and a database backup converter is integrated into the Backup Manager.

**1.2**

FTP Passwords are no longer stored in the database, for security reasons. It is now possible to create either a full backup or a database backup, using Backup Manager. Explanations on the Migration Assistant page are replaced with more clear ones.

**1.1.1**

Database backup files will be smaller now as transients will not be included in database backup files. Backup files created before changing the domain name are now being stored under the old backup directory for a possible future need. A small bug is fixed and meta boxes are added to the settings page of the plugin.

**1.1**

You can now create a full backup of your installation using the Backup Now button on the Backup Manager page. Also, migrating and changing the domain name at the same time is now supported.

**1.0**

Initial release.
