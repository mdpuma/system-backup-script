#!/bin/bash
# load configs
BASEDIR=$(dirname $0)

. $BASEDIR/config.sh
. $BASEDIR/functions.sh

mkdir_force $BACKUP_DEST

backup_files
# backup_kernel_config
# backup_package_list
# /usr/bin/php -q $BASEDIR/backup_dbs.php
# rsync_files
# mirror_to_remoteftp