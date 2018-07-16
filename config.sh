#!/bin/bash

# global defines
BACKUP_DEST="/backup"
LIFETIME=31 # lifetime of backups in days

# backup data
DATA="/var/www /root /home"
CONFIG="/etc /var/spool/cron/crontabs"
EXCLUDE_LIST="$BASEDIR/exclude_files.list"

# mirror to remote ftp
FTP_URI=ftp://host/directory
FTP_USERPASS=ftplogin,ftppass
FTP_PORT=21

# rsync data to remote server
rsync_destination="user@host::remote/folder"
rsync_passfile="$BASEDIR/rsync_passfile"


backup_list_file="/tmp/backlist_$$.txt"