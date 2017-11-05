<?php

######################################################################
## General Options
######################################################################

// Path to the mysql commands (mysqldump, mysqladmin, etc..)
$MYSQL_PATH = '/usr/bin';

// Mysql connection settings (must have root access to get all DBs)
$MYSQL_TYPE   = 'socket'; // can be socket or tcp
$MYSQL_HOST   = 'localhost';
$MYSQL_PORT   = '3306';
$MYSQL_SOCKET = '/var/run/mysqld/mysqld.sock';
$MYSQL_USER   = 'root';
$MYSQL_PASSWD = NULL;

// Backup destination (will be created if not already existing)
$BACKUP_DEST = '/var/backups/dest/db_backups';

// Temporary location (will be created if not already existing)
$BACKUP_TEMP = $BACKUP_DEST.'/tmp';

// Show script status on screen while processing
// (Does not effect log file creation)
$VERBOSE = true;

// Name of the created backup file (you can use PHP's date function)
// Omit file suffixes like .tar or .zip (will be set automatically)
$BACKUP_NAME = 'mysql_backup_' . date('Ymd-Hi');

// Name of the standard log file
$LOG      = true;
$LOG_FILE = $BACKUP_NAME . '.log';

// Name of the error log file
$ERR      = false;
$ERR_FILE = $BACKUP_NAME . '.err';

// Which compression program to use
// Only relevant on unix based systems. Windows system will use zip command.
$COMPRESSOR = 'bzip2';

######################################################################
## Email Options
######################################################################

// Email the backup file when finished?
$EMAIL_BACKUP = true;

// If using email backup, delete from server afterwards?
$DEL_AFTER = false;

// The backup email's 'FROM' field
$EMAIL_FROM = 'Backup Script <backup@'.$HOSTNAME.'>';

// The backup email's subject line
$EMAIL_SUBJECT = '[Backup] SQL Backup at ' . $HOSTNAME . ' for ' . date('Y/m/d') . ' at ' . date('H:i');

// The destination address for the backup email
$EMAIL_ADDR = 'dan@amplica.md';

######################################################################
## Error Options
######################################################################

// Email error log to specified email address
// (Will only send if an email address is given)
$ERROR_EMAIL = $EMAIL_ADDR;

// Subject line for error email
$ERROR_SUBJECT = 'ERROR: ' . $EMAIL_SUBJECT;

######################################################################
## Advanced Options
## Be sure you know what you are doing before making changes here!
######################################################################
// A comma separated list of databases, which should be excluded
// from backup
// information_schema is a default exclude, because it is a read-only DB anyway
$EXCLUDE_DB = 'information_schema,performance_schema,test';

// Defines the maximum number of seconds this script shall run before terminating
// This may need to be adjusted depending on how large your DBs are
// Default: 18000
$MAX_EXECUTION_TIME = 18000;

// Low CPU usage while compressing (recommended) (empty string to disable).
// Only relevant on unix based systems
// Default: 'nice -n 19'
$USE_NICE = 'nice -n 19';

// Flush tables between mysqldumps (recommended, if it runs during non-peak time)
// Default: false
$FLUSH = false;

// Optimize databases between mysqldumps.
// (For detailed information look at
// http://dev.mysql.com/doc/mysql/en/mysqlcheck.html)
// Default: false
$OPTIMIZE = false;

// Remove older db backups than x days.
// Default: 31 = 1 month
$OLDERTHAN = 60;
######################################################################
## End of Options
######################################################################
?>