#!/bin/bash

function backup_files() {
	. $BASEDIR/config.sh
	
	set $(date)
	weekday=$(date +%a)
	dateformat=$(date +%Y_%m_%d)
	BACKUP_DEST="$BACKUP_DEST/systemfiles"
	mkdir_force $BACKUP_DEST
	mkdir_force $BACKUP_DEST/config
	mkdir_force $BACKUP_DEST/data
	
	if [ "$weekday" = "Sun" ]; then
		DATA=$(echo $DATA | sed -E 's/(^| )\//\1/g')
		CONFIG=$(echo $CONFIG | sed -E 's/(^| )\//\1/g')
		
		rm -f $BACKUP_DEST/data/data_diff* $BACKUP_DEST/config/config_diff*
		tar -c -C / -f "$BACKUP_DEST/data/data_full_$dateformat.tar.gz" --preserve-permissions --gzip --exclude-from="$EXCLUDE_LIST" $DATA 
		tar -c -C / -f "$BACKUP_DEST/config/config_full_$dateformat.tar.gz" --preserve-permissions --gzip --exclude-from="$EXCLUDE_LIST" $CONFIG
	else
		find $DATA -type f \( -ctime -1 -o -mtime -1 \) -print | sed -e 's/^\///' > $backup_list_file
		tar -c -C / -f "$BACKUP_DEST/data/data_diff_$dateformat.tar.gz" --preserve-permissions --gzip --files-from="$backup_list_file" --exclude-from="$EXCLUDE_LIST"
		
		find $CONFIG -type f \( -ctime -1 -o -mtime -1 \) -print | sed -e 's/^\///' > $backup_list_file
		tar -c -C / -f "$BACKUP_DEST/config/config_diff_$dateformat.tar.gz" --preserve-permissions --gzip --files-from="$backup_list_file" --exclude-from="$EXCLUDE_LIST"
		
		rm -f "$backup_list_file"
	fi
	
	# remove backups older than $LIFETIME
	find "$BACKUP_DEST" -name \*.tar.gz -type f -mtime +$LIFETIME -delete
}

function backup_kernel_config() {
	. $BASEDIR/config.sh
	
	dateformat=$(date +%Y_%m_%d)
	source_config="/proc/config.gz"
	BACKUP_DEST="$BACKUP_DEST/kernel-configs"
	dest_config="$BACKUP_DEST/config_`uname -r`_$dateformat.gz"
	checksum_file="$BACKUP_DEST/config.gz.md5sum"
	
	if [ -f "$source_config" ]; then
		mkdir_force $BACKUP_DEST
		checksum_source="$(md5sum $source_config | cut -d ' ' -f1)"
		[ -f $checksum_file ] && CURRENT_MD5="$(cat $checksum_file)"
		if [ "$checksum_source" != "$CURRENT_MD5" ]; then
			echo $checksum_source > $checksum_file
			cp $source_config $dest_config
			echo "[$0] Create backup of $dest_config"
		fi
	fi
	
	# remove backups older than $LIFETIME
	find "$BACKUP_DEST" -name \*.gz -type f \( -mtime +$LIFETIME \) -delete
}

function backup_package_list() {
	. $BASEDIR/config.sh
	
	# do backup if OS is gentoo
	grep -i gentoo /etc/os-release
	if [ $? -ne 0 ]; then
		echo "Non gentoo os, skipping backup of package_list."
		return
	fi
	
	dateformat=$(date +%Y_%m_%d)
	USERPLIST="/tmp/userpackages.lst"
	ALLPLIST="/tmp/allpackages.lst"
	BACKUP_DEST="$BACKUP_DEST/packages"
	BACKUP="packages-$dateformat.tar.gz"
	
	mkdir_force $BACKUP_DEST
	
	# generate lists & backuping lists
	cp /var/lib/portage/world $USERPLIST
	ls -d /var/db/pkg/*/*| cut -f5- -d/ > $ALLPLIST
	tar cfpz "$BACKUP_DEST/$BACKUP" $USERPLIST $ALLPLIST
	
	# removing lists
	rm -v $USERPLIST $ALLPLIST
	
	# remove backups older than $LIFETIME
	find "$BACKUP_DEST" -name \*.tar.gz -type f -mtime +$LIFETIME -delete
}

function rsync_files() {
	. $BASEDIR/config.sh
	
	chmod="$(stat -c%a $rsync_passfile)"
	[ "$chmod" != "400" ] && chmod 400 "$rsync_passfile" -v
	
	# run backup
	rsync --password-file "$rsync_passfile" --exclude-from "$EXCLUDEFILE" -rupv --del $BACKUP_DEST/ $rsync_destination
}

function mirror_to_remoteftp() {
	. $BASEDIR/config.sh
	cd $BACKUP_DEST
	lftp -u $FTP_USERPASS -p $FTP_PORT -e 'mirror -R .;exit' $FTP_URI
}

function mkdir_force() {
	[ ! -d $1 ] && mkdir -p $1 -v
}