# tasklist_caldavsso
CalDAV driver for kolab/tasklist with SSO option 

You need to manually install this. Copy the files to <roundcube_install>/plugins/tasklist/drivers/caldavsso.

Configure the default tasklist in config_inc.php.

Configure the tasklist to use the driver by setting: $config['tasklist_driver'] = "caldavsso";

Enable the tasklist plugin.

This plugin has limit fields because it matches what can be synced with ActiveSync.

TODO:
 - Alarms
 - Make some changes in the UI (what fields to show and what to hide)
