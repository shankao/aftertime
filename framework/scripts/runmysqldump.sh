#!/bin/sh
#
# Wrapper for the mysqldump command line program, connected to the current aftertime DB instance
#
# WARNING: this script adds the DB password to the mysql command. This is considered an insecure practice. See http://dev.mysql.com/doc/refman/5.1/en/password-security-user.html

set -o nounset

while getopts "o:i:" OPT; do
	case $OPT in
		o) OUTPUT_FILE=$OPTARG;;
		i) IGNORE_TABLES=$OPTARG;;
	esac
done

if [ ! ${OUTPUT_FILE:-""} ]; then
        echo "Please, indicate the output file with the -o option"
        return 255
fi

CONFIG=$(php scripts/getconfig.php)
if [ $? != 0 ]; then
	echo $CONFIG
	return $?
fi

get_key() {
	echo "$CONFIG" | grep "$1=" | cut -f2 -d"="
}

DB_HOST=$(get_key "database.host")
DB_NAME=$(get_key "database.dbname")
DB_USER=$(get_key "database.user")
DB_PASSWD=$(get_key "database.password")
if [ ! ${DB_HOST:-""} ]; then
	echo "No DB_HOST in config. Something went wrong"
	return 255
fi

DUMP_COMMAND="mysqldump -h $DB_HOST -u $DB_USER -p$DB_PASSWD --databases $DB_NAME --result-file=$OUTPUT_FILE"
DUMP_OPTS="--compress --default-character-set=utf8 --complete-insert --skip-comments --skip-add-drop-table --single-transaction --no-create-db --no-create-info --skip-dump-date"
if [ ${IGNORE_TABLES:-""} ]; then
	echo "Ignoring tables: $IGNORE_TABLES"
	for table in $IGNORE_TABLES; do
		DUMP_OPTS="$DUMP_OPTS --ignore-table=$DB_NAME.$table"
	done
fi

DUMP_COMMAND="$DUMP_COMMAND $DUMP_OPTS"
$DUMP_COMMAND
return $?
