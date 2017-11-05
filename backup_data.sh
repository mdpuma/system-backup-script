#!/bin/bash
# load configs
BASEDIR=$(dirname $0)
##################################################
# creates backups of essential files
##################################################
. $BASEDIR/global_defines.sh
LIST="/tmp/backlist_$$.txt"
BACKUP_DEST="$BACKUP_DEST/systemfiles"

if [ ! -d $BACKUP_DEST ];        then mkdir -p $BACKUP_DEST -v; fi
if [ ! -d $BACKUP_DEST/config ]; then mkdir -p $BACKUP_DEST/config -v; fi
if [ ! -d $BACKUP_DEST/data ];   then mkdir -p $BACKUP_DEST/data -v; fi

set $(date)
if test "$1" = "Sun" ; then
	rm -f $BACKUP_DEST/data/data_diff* $BACKUP_DEST/config/config_diff*
	tar cfpz "$BACKUP_DEST/data/data_full_$6-$2-$3.tar.gz" $DATA -X $EXCLUDE_LIST
	tar cfpz "$BACKUP_DEST/config/config_full_$6-$2-$3.tar.gz" $CONFIG -X $EXCLUDE_LIST
else
	find $DATA -depth -type f \( -ctime -1 -o -mtime -1 \) -print > $LIST
	tar cfzpT "$BACKUP_DEST/data/data_diff_$6-$2-$3.tar.gz" "$LIST" -X $EXCLUDE_LIST
	find $CONFIG -depth -type f  \( -ctime -1 -o -mtime -1 \) -print > $LIST
	tar cfzpT "$BACKUP_DEST/config/config_diff_$6-$2-$3.tar.gz" "$LIST" -X $EXCLUDE_LIST
	rm -f "$LIST"
fi

# remove backups older than $LIFETIME
find "$BACKUP_DEST/data" -depth -type f \( -mtime +$LIFETIME \) -exec rm -v {} \;
find "$BACKUP_DEST/config" -depth -type f \( -mtime +$LIFETIME \) -exec rm -v {} \;
##################################################
# creates backups of kernel files
##################################################
if [ $BACKUP_KERNEL -eq 1 ]; then
	. $BASEDIR/global_defines.sh
	FILE="/proc/config.gz"
	BACKUP_DEST="$BACKUP_DEST/kernel-configs"
	BACKUP="$BACKUP_DEST/config-`uname -r`-`date +%Y-%m-%d`.gz"
	MD5SUM="$BACKUP_DEST/config.gz.md5sum"
	
	if [ -f "/proc/config.gz" ]; then
		if [ ! -d $BACKUP_DEST ]; then mkdir -p $BACKUP_DEST -v; fi
		if [ -f $MD5SUM ];        then CURRENT_MD5="`cat $MD5SUM`"; fi
		if [ "md5sum /proc/config.gz | awk '{print $1}'" != "$CURRENT_MD5" ]; then
			echo $1 > $MD5SUM
			cp /proc/config.gz $BACKUP
			echo "[$0] Create backup $BACKUP"
		fi
	fi
	
	# remove backups older than $LIFETIME
	find "$BACKUP_DEST" -depth -type f \( -mtime +$LIFETIME \) -exec rm -v {} \;
fi
##################################################
# creates backups of packages files
##################################################
if [ $BACKUP_PKG -eq 1 ]; then
	. $BASEDIR/global_defines.sh
	USERPLIST="/tmp/userpackages.lst"
	ALLPLIST="/tmp/allpackages.lst"
	BACKUP_DEST="$BACKUP_DEST/packages"
	BACKUP="packages-`date +%Y-%m-%d`.tar.gz"
	
	if [ ! -d $BACKUP_DEST ]; then mkdir -p $BACKUP_DEST -v; fi
	
	# generate lists & backuping lists
	cp /var/lib/portage/world $USERPLIST
	ls -d /var/db/pkg/*/*| cut -f5- -d/ > $ALLPLIST
	tar cfpz "$BACKUP_DEST/$BACKUP" $USERPLIST $ALLPLIST
	
	# removing lists
	rm -v $USERPLIST $ALLPLIST
	
	# remove backups older than $LIFETIME
	find "$BACKUP_DEST" -depth -type f \( -mtime +$LIFETIME \) -exec rm -v {} \;
fi
##################################################
# creates backups of packages files
##################################################
if [ $BACKUP_MYSQL -eq 1 ]; then
	. $BASEDIR/global_defines.sh
	/usr/bin/php -q $BASEDIR/backup_dbs.php
	#find "$BACKUP_DEST" -depth -type f \( -mtime +$LIFETIME \) -exec rm -v {} \;
fi
##################################################
# rsync files to remote server
##################################################
if [ $BACKUP_RSYNC -eq 1 ]; then
	. $BASEDIR/global_defines.sh
	if [ "`stat -c%a $PASSFILE`" != "400" ]; then chmod 400 "$PASSFILE" -v; fi
	
	# run backup
	rsync --password-file "$PASSFILE" --exclude-from "$EXCLUDEFILE" -rupv --del $BACKUP_DEST/* $USER@$HOST::${DESTINATION}
fi
