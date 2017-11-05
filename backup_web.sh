#!/bin/sh
##################################################
BASEDIR=$(dirname $0)
. $BASEDIR/global_defines.sh
##################################################
RSYNC_USE=0
RSYNC_ADDR="ip"
RSYNC_USER="testbackup"
RSYNC_PSEUDOPATH="backup"
RSYNC_PASSF="$BASEDIR/rsync_backups-passfile.rsync"

BACKUP_DEST="/backup/websites"
OLDER=31  # last time accessed / days

# run backup
set $(date)
if [ "$1" = "Sun" ]; then
	find "${BACKUP_DEST}" \( -name 'srv-*-full*' \) -mtime +$OLDER -exec rm -v {} \;
	find "${BACKUP_DEST}" \( -name 'srv-*-diff*' \) -mtime +$OLDER -exec rm -v {} \;
fi

for DIR in `find /home -maxdepth 1 -type d ! -name .\* | sort`; do
	NAME=`echo "$DIR" | awk -F/ '{ print $3}'`
	if [ "$NAME" = "virtfs" ]; then
		continue;
	fi
	if [ -d "$DIR" ] && [ -n "$NAME" ]; then
		cd ${DIR}
		if [ "$1" = "Sun" ]; then
			TMP_BACKUPNAME="$BACKUP_DEST/$NAME-full-$6-$2-$3.tgz"
			TMP_BACKUP_LIST="/tmp/backlist-$NAME-$$.txt"
			printf "[$0] Backup of userdata $DIR to $TMP_BACKUPNAME started ... "
			find -depth -print > ${TMP_BACKUP_LIST}
			tar cpzfT ${TMP_BACKUPNAME} ${TMP_BACKUP_LIST} 2>/dev/null
		else
			TMP_BACKUPNAME="$BACKUP_DEST/$NAME-diff-$6-$2-$3.tgz"
			TMP_BACKUP_LIST="/tmp/backlist-$NAME-$$.txt"
			printf "[$0] Backup of userdata $DIR to $TMP_BACKUPNAME started ... "
			find -depth -type f -mtime -1 -print > ${TMP_BACKUP_LIST}
			tar cpzfT ${TMP_BACKUPNAME} ${TMP_BACKUP_LIST} 2>/dev/null
		fi
		if [ ${RSYNC_USE} -eq 1 ]; then 
			rsync --password-file ${RSYNC_PASSF} -rup ${TMP_BACKUPNAME} ${RSYNC_USER}@${RSYNC_ADDR}::${RSYNC_PSEUDOPATH}
		fi
		printf "done\n"
		rm -f "${TMP_BACKUP_LIST}"
	fi
done

