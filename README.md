# tasklist_caldavsso
CalDAV driver for kolab/tasklist with SSO option 

You need to manually install this. Copy the files to <roundcube_install>/plugins/tasklist/drivers/caldavsso.

Configure the default tasklist in config_inc.php.

Configure the tasklist to use the driver by setting: $config['tasklist_driver'] = "caldavsso";

Apply the taskedit.html.patch to remove the properties in the UI that are not supported by ActiveSync.

Enable the tasklist plugin.

TODO:
 - Alarms
