webmonitor
==========

Web Monitor

Version 1.00



Purpose
The purpose of this document is to describe the web site monitoring software used by Ramblers-Webs. The software is used to try and spot if a web site has been hacked. It does this my checking and reporting what files have been changed. The owner of the site and the Ramblers-Webs administrators can then review these changes to see if any of them are suspect.
Hacking

Unfortunately there are people who will try and hack a web site. Some of these people just do it as a challenge and will not cause any damage. Others however will cause damage and will affect how a web site works, these often will redirect users to another site. 

There are also cases where people high-jack sites and use them to spread viruses or send emails. These often leave the site looking and working as normal.

WebMonitor

This software consists of one Mysql database and two files config.php and hashscan.php

The config.php file contains the setting for the particular web site account.

The hashscal.php file contains the code that is run to check the site. 

Installation

To install the software do the following

1. Set up a new mysql database

2. Create a new folder called monitor (normally at the same level as the public_html folder)

3. Copy the config.php and hashscan.php files into the new monitor folder

4. Edit the config.php file to have the correct web site details

5. Try running the hashscan.php (this will normally require using the ip address of your site or the setting up of a subdomain pointing at the monitor folder)

6. If the above is successful then set up a cron/scheduled task to run ever day in the early hours of the morning. E;g;
/usr/bin/php5  /home/sites/ramblers-webs.org.uk/monitor/hashscan.php or
/usr/bin/php53  /home/sites/ramblers-webs.org.uk/monitor/hashscan.php

Emails

the system will send an email to the specified email address if it detects any changes.
If there have been no changes and the time limit set in the config file by $emailinterval has elapsed then it will also send an email. This allows as to know that it is still running each night but not detecting any changes.

Description of config.php values

a description of these fields is contained in the config.php file.
