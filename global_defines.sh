#!/bin/bash

# global defines
BACKUP_KERNEL=1
BACKUP_PKG=1
BACKUP_MYSQL=1
BACKUP_RSYNC=0
BACKUP_DEST="/mnt/md1/backup/ns1"
LIFETIME=15 # lifetime of backups in days

# backup data
DATA="/home/host /home/http /gh_distr/*"
CONFIG="/etc /var/spool/cron/crontabs"
EXCLUDE_LIST="$BASEDIR/exclude_files.lst"

# rsync data to remote server
HOST=""
USER="backup"
DESTINATION="backup/$HOSTNAME"
PASSFILE="$BASEDIR/rsync_backups-passfile.rsync"
EXCLUDEFILE="$BASEDIR/rsync_backups-excludefile.rsync"

# run
if [ ! -d "$BACKUP_DEST" ]; then mkdir -p $BACKUP_DEST -v; fi
