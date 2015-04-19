webmonitor
==========

Web Monitor

Version 2.00



PURPOSE

The purpose of this document is to describe the web site monitoring software used by Ramblers-Webs. 
The software is used to try and spot if a web site has been hacked. It does this my checking and reporting what files have been changed. 
The owner of the site and the Ramblers-Webs administrators can then review these changes to see if any of them are suspect.

HACKING

Unfortunately there are people who will try and hack a web site. Some of these people just do it as a challenge and will not cause any damage. 
Others however will cause damage and will affect how a web site works, these often will redirect users to another site. 

There are also cases where people high-jack sites and use them to spread viruses or send emails. These often leave the site looking and working as normal.

WEBMONITOR

This software consists of one Mysql database and some php files 

The hashscan.php file contains the code that is run to check the site. 

INSALLATION

To install the software do the following

1. Set up a new mysql database

2. Create a new folder called monitor (normally at the same level as the public_html folder)

3. Copy the php files into the new monitor folder

4. Copy the config_master.php and save it as config.php. Edit the config.php file to have the correct web site details

5. Try running the hashscan.php (this will normally require using the ip address of your site)

6. If the above is successful then 
      1. Move the monitor folder to the same level as the public html folder so it is not accesable to the public
	  2. set up a cron/scheduled task to run ever day in the early hours of the morning. E;g;
           /usr/bin/php5  /home/sites/ramblers-webs.org.uk/monitor/hashscan.php or
           /usr/bin/php55  /home/sites/ramblers-webs.org.uk/monitor/hashscan.php

EMAILS

the system will send an email to the specified email address if it detects any changes.
If there have been no changes and the time limit set in the config file by $emailinterval has elapsed then it will also send an email. This allows as to know that it is still running each night but not detecting any changes.

Description of config.php values

a description of these fields is contained in the config.php file.
