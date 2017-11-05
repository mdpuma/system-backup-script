<?php

######################################################################
## Usage Instructions
######################################################################
## This script requires two files to run:
##     backup_dbs.php        - Main script file
##     backup_dbs_config.php - Configuration file
## Be sure they are in the same directory.
## -------------------------------------------------------------------
## Do not edit the variables in the main file. Use the configuration
## file to change your settings. The settings are explained there.
## -------------------------------------------------------------------
## A few methods to run this script:
## - php /PATH/backup_dbs.php
## - BROWSER: http://domain/PATH/backup_dbs.php
## - ApacheBench: ab "http://domain/PATH/backup_dbs.php"
## - lynx http://domain/PATH/backup_dbs.php
## - wget http://domain/PATH/backup_dbs.php
## - crontab: 0 3  * * *     root  php /PATH/backup_dbs.php
## -------------------------------------------------------------------
## For more information, visit the website given above.
######################################################################

error_reporting(E_ALL);
ini_set('display_errors', 'On');

// Initialize default settings
$MYSQL_PATH         = '/usr/bin';
$MYSQL_TYPE         = 'socket'; // can be socket or tcp
$MYSQL_HOST         = 'localhost';
$MYSQL_PORT         = '3306';
$MYSQL_SOCKET       = '/tmp/mysql.sock';
$MYSQL_USER         = 'root';
$MYSQL_PASSWD       = 'password';
$BACKUP_DEST        = '/db_backups';
$BACKUP_TEMP        = '/tmp/backup_temp';
$VERBOSE            = true;
$BACKUP_NAME        = 'mysql_backup_' . date('Ymd-Hi');
$LOG_FILE           = $BACKUP_NAME . '.log';
$ERR_FILE           = $BACKUP_NAME . '.err';
$COMPRESSOR         = 'bzip2';
$EMAIL_BACKUP       = false;
$DEL_AFTER          = false;
$EMAIL_SUBJECT      = 'SQL Backup for ' . date('Y-m-d') . ' at ' . date('H:i');
$EMAIL_FROM         = 'root@domain.com';
$EMAIL_ADDR         = 'user@domain.com';
$ERROR_EMAIL        = 'user@domain.com';
$ERROR_SUBJECT      = 'ERROR: ' . $EMAIL_SUBJECT;
$EXCLUDE_DB         = 'information_schema,performance_schema,test';
$MAX_EXECUTION_TIME = 18000;
$USE_NICE           = 'nice -n 19';
$FLUSH              = false;
$OPTIMIZE           = false;
$OLDERTHAN          = 90;
$HOSTNAME           = php_uname('n');

// Load configuration file
$current_path = dirname(__FILE__);
if (@file_exists($current_path . '/backup_dbs_config.php')) {
	@require($current_path . '/backup_dbs_config.php');
} else {
	echo 'No configuration file [backup_dbs_config.php] found. Please check your installation.';
	exit;
}

################################
# functions
################################
function writeLog($msg, $newline = false, $error = false) {
	global $f_log, $f_err, $VERBOSE;
	
	// add current time and linebreak to message
	if ($newline) {
		$msg = date('Y-m-d H:i:s: [') . memory_get_usage(true) . '] ' . $msg;
	}
	
	// switch between normal or error log
	if ($error == false) {
		@fwrite($f_log, $msg);
	} else {
		@fwrite($f_err, $msg);
	}
	
	if ($VERBOSE == true) {
		echo $msg;
		flush();
	}
}
function error($is_error, $msg, $critical = false) {
	global $error;
	if ($is_error) {
		// write error to both log files
		writeLog($msg, true);
		writeLog($msg, true, true);
		
		// terminate script if this error is critical
		if ($critical) {
			die($msg);
		}
		
		$error = true;
	}
}

################################
# main
################################
header('Content-Type: text/plain; charset="UTF-8"');
header('Content-disposition: inline');

set_time_limit($MAX_EXECUTION_TIME);

// initialize error control
$error = false;

// guess and set host operating system
if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
	$os          = 'unix';
	$backup_mime = 'application/x-tar';
	$BACKUP_NAME .= '.tar';
} else {
	$os          = 'windows';
	$backup_mime = 'application/zip';
	$BACKUP_NAME .= '.zip';
}

// create directories if they do not exist
if (!@is_dir($BACKUP_DEST)) {
	$success = @mkdir($BACKUP_DEST);
	error(!$success, 'Backup directory could not be created in ' . $BACKUP_DEST, true);
}
if (!@is_dir($BACKUP_TEMP)) {
	$success = @mkdir($BACKUP_TEMP);
	error(!$success, 'Backup temp directory could not be created in ' . $BACKUP_TEMP, true);
} else {
	exec("rm -Rfv " . $BACKUP_TEMP . "/*");
}

// prepare standard log file
$log_path = $BACKUP_DEST . '/' . $LOG_FILE;
($f_log = fopen($log_path, 'w')) || error(true, 'Cannot create log file: ' . $log_path, true);

// prepare error log file
$err_path = $BACKUP_DEST . '/' . $ERR_FILE;
($f_err = fopen($err_path, 'w')) || error(true, 'Cannot create error log file: ' . $err_path, true);

// Start logging
writeLog("Executing MySQL Backup Script v1.6\n", true);
writeLog("Processing Databases..\n", true);

################################
# DB dumps
################################
$excludes = array();
if (trim($EXCLUDE_DB) != '') {
	$excludes = array_map('trim', explode(',', $EXCLUDE_DB));
}

// connect to mysql server
if ($MYSQL_TYPE == 'tcp') {
	$db_auth = ' --host="' . $MYSQL_HOST . '" --port="' . $MYSQL_PORT . '" --user="' . $MYSQL_USER . '"';
} else {
	$db_auth = ' --socket="' . $MYSQL_SOCKET . '" --user="' . $MYSQL_USER . '"';
}
if (!empty($MYSQL_PASSWD))
	$db_auth .= ' --password="' . $MYSQL_PASSWD . '"';

exec("echo 'show databases;' | mysql " . $db_auth . " 2>&1", $output, $res);
if ($res > 0) {
	error(true, 'Can\'t connect to mysql. Error: ' . implode("\n", $output) . "\n");
	goto send_error;
}

// create list of dbs
$db_array = $output;
$included = '';
$excluded = '';
foreach ($db_array as $id => $db) {
	if ($db === 'Database') {
		unset($db_array[$id]);
		continue;
	}
	if (in_array($db, $excludes)) {
		$excluded .= ' ' . $db;
		unset($db_array[$id]);
		continue;
	}
	$included .= ' ' . $db;
}
writeLog("Databases to be created backup:" . $included . "\n", true);
writeLog("Databases excluded from backup:" . $excluded . "\n", true);

// dump each db
foreach ($db_array as $db) {
	unset($output);
	writeLog("Process: " . $db, true);
	exec("$MYSQL_PATH/mysqldump $db_auth --opt $db 2>&1 >$BACKUP_TEMP/$db.sql", $output, $res);
	if ($res > 0) {
		error(true, "DUMP FAILED\n" . implode("\n", $output));
	} else {
		writeLog(" ..dumped");
		if ($OPTIMIZE) {
			unset($output);
			exec("$MYSQL_PATH/mysqlcheck $db_auth --optimize $db 2>&1", $output, $res);
			if ($res > 0) {
				error(true, "OPTIMIZATION FAILED\n" . implode("\n", $output));
			} else {
				writeLog(" ..optimized");
			}
		} // if
	} // if
	
	// compress db
	unset($output);
	if ($os == 'unix') {
		exec("$USE_NICE $COMPRESSOR $BACKUP_TEMP/$db.sql 2>&1", $output, $res);
	} else {
		exec("zip -mj $BACKUP_TEMP/$db.sql.zip $BACKUP_TEMP/$db.sql 2>&1", $output, $res);
	}
	if ($res > 0) {
		error(true, "COMPRESSION FAILED\n" . implode("\n", $output));
	} else {
		writeLog(" ..compressed");
	}
	writeLog("\n");
	
	if ($FLUSH) {
		unset($output);
		exec("$MYSQL_PATH/mysqladmin $db_auth flush-tables 2>&1", $output, $res);
		if ($res > 0) {
			error(true, "Flushing tables failed\n" . implode("\n", $output));
		} else {
			writeLog("Flushed Tables\n", true);
		}
	} // if
} // while

################################
# Archiving
################################
writeLog("Archiving files.. \n", true);
unset($output);
if ($os == 'unix') {
	chdir($BACKUP_TEMP);
	exec("$USE_NICE tar cf $BACKUP_DEST/$BACKUP_NAME *.bz2 2>&1", $output, $res);
} else {
	chdir($BACKUP_TEMP);
	exec("zip -j -0 $BACKUP_DEST/$BACKUP_NAME $BACKUP_TEMP/* 2>&1", $output, $res);
}
if ($res > 0) {
	error(true, "FAILED\n" . implode("\n", $output));
} else {
	writeLog("Backup complete!\n", true);
}

// first error check, so we can add a message to the backup email in case of error
if ($error) {
	$msg = "\n*** ERRORS DETECTED! ***";
	if ($ERROR_EMAIL) {
		$msg .= "\nCheck your email account $ERROR_EMAIL for more information!\n\n";
	} else {
		$msg .= "\nCheck the error log {$err_path} for more information!\n\n";
	}
	writeLog($msg, true);
}

################################
# post processing
################################
if ($EMAIL_BACKUP) {
	writeLog("Emailing backup to " . $EMAIL_ADDR . " .. \n", true);
	
	// if mail is bigger than 5MB (5242880) send with mutt
	if (filesize($BACKUP_DEST . '/' . $BACKUP_NAME) > 5242880) {
		exec("mutt -v 2>&1 >/dev/null", $output, $res);
		if ($res > 0) {
			error(true, "FAILED: no mutt on system");
		}
		
		$body = file_get_contents($log_path);
		exec('echo -e "' . $body . '" | mutt -s "' . $EMAIL_SUBJECT . '" -a ' . $BACKUP_DEST . '/' . $BACKUP_NAME . ' -e "my_hdr From:' . $EMAIL_FROM . '" -- ' . $EMAIL_ADDR);
	} else {
		//set memory_limit to 500MB
		ini_set('memory_limit', '500M');
		$rnd_str = md5(time());
		
		$headers = <<<EOF
From: $EMAIL_FROM
Content-Type: multipart/mixed; boundary="{$rnd_str}"
EOF;
		
		$data = file_get_contents($log_path);
		$body = <<<EOF
--{$rnd_str}
Content-Type: text/plain; charset="utf-8"
Content-Transfer-Encoding: 7bit

{$data}


EOF;
		
		$data = chunk_split(base64_encode(file_get_contents($BACKUP_DEST . '/' . $BACKUP_NAME)));
		$body .= <<<EOF
--{$rnd_str}
Content-Type: {$backup_mime}; name="{$BACKUP_NAME}"
Content-Disposition: attachment; filename="{$BACKUP_NAME}"
Content-Transfer-Encoding: base64

{$data}

--{$rnd_str}--
EOF;
		
		if (!mail($EMAIL_ADDR, $EMAIL_SUBJECT, $body, $headers, "-f $EMAIL_FROM $EMAIL_ADDR")) {
			error(true, 'FAILED to email mysql dumps.');
			die();
		}
	}
}


// do we delete the backup file?
if ($DEL_AFTER && $EMAIL_BACKUP) {
	writeLog("Deleting file.. \n", true);
	if (file_exists($BACKUP_DEST . '/' . $BACKUP_NAME)) {
		$success = unlink($BACKUP_DEST . '/' . $BACKUP_NAME);
		error(!$success, "FAILED\nUnable to delete backup file");
		die();
	}
}

send_error:
// see if there were any errors to email
if (($ERROR_EMAIL) && ($error)) {
	writeLog("\nThere were errors!\n", true);
	writeLog("Emailing error log to " . $ERROR_EMAIL . " .. \n", true);
	
	$headers = <<<EOF
From: $EMAIL_FROM
MIME-Version: 1.0
Content-Type: text/plain; charset="utf-8"
EOF;
	
	$body = "FAILED:\n\n" . file_get_contents($err_path) . "\n";
	
	if (!mail($ERROR_EMAIL, $ERROR_SUBJECT, $body, $headers, "-f $EMAIL_FROM $EMAIL_ADDR")) {
		error(true, 'FAILED to email error log.');
	}
}

// remove old db backups
exec("find " . $BACKUP_DEST . " -maxdepth 1 -name mysql_backup_\*.tar -type f -mtime +" . $OLDERTHAN . " -exec rm -v {} \;", $output, $res);
if ($res > 0) {
	error(true, "FAILED\n" . implode("\n", $output));
} else {
	$output = trim(implode("\n", $output));
	if (strlen($output) > 0) {
		writeLog($output . "\n", true);
	}
}

################################
# cleanup
################################
// if error log is empty, delete it
if (!$error)
	@unlink($err_path);

// delete the log files if they have been emailed (and del_after is on)
if ($DEL_AFTER && $EMAIL_BACKUP) {
	if (file_exists($log_path))
		@unlink($log_path);
	if (file_exists($err_path))
		@unlink($err_path);
}

// remove files in temp dir
exec("find " . $BACKUP_TEMP . " -maxdepth 1 -type f -exec rm -v {} \;", $output, $res);
rmdir($BACKUP_TEMP);
?>