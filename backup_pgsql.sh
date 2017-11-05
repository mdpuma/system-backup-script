#!/bin/bash -e
BACKUPDIR=/var/lib/pgsql/backups/
DBS="db1 template1"
FTP_URI=ftp://backup/pgsql
FTP_USERPASS=ftplogin,ftppass
FTP_PORT=21

# try to create the lock and check the outcome
LOCKFILE=/var/run/pgsqlbackup.lock
if [ -e "$LOCKFILE" ]; then
        echo "Another instance already running. Aborting."
        exit 1
else
        touch "$LOCKFILE"
fi
trap "rm ${LOCKFILE}" EXIT

# check BACKUPDIR
[ ! -d $BACKUPDIR ] && mkdir -p $BACKUPDIR

# Backup Spacewalk Postgres DB
for i in $DBS; do
    su - postgres -c "pg_dump $i | gzip > $BACKUPDIR/postgres_backup-`hostname`-$i-`date +%Y%m%d`.sql.gz"
done

find $BACKUPDIR -maxdepth 1 -mtime +31 -exec rm -rv {} \;

# Rsync Backups
cd $BACKUPDIR
lftp -u $FTP_USERPASS -p $FTP_PORT -e 'mirror -R .;exit' $FTP_URI

DAY=`date +%u`
if [ "$DAY" = "7" ]; then
    echo "Backup list:" 2>&1
    lftp -u $FTP_USERPASS -p $FTP_PORT -e 'ls' $FTP_URI 2>&1
fi