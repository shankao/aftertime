#!/bin/sh
#
# Wrapper for the mysql command line program, connected to the current aftertime DB instance
# If input is done from a file, use the -f argument, as the read here truncates the file into independent lines.
# XXX check for "read" switches that would merge lines
#
# WARNING: this script adds the DB password to the mysql command. This is considered an insecure practice. See http://dev.mysql.com/doc/refman/5.1/en/password-security-user.html
#

set -o nounset

while getopts "f:n" OPT; do
	case $OPT in
		f) SQL_FILE=$OPTARG;;
		n) NO_DB="yes";;
	esac
done

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

MYSQL_OPTS="--batch --compress --default-character-set=utf8"
MYSQL_COMMAND="mysql -h $DB_HOST -u $DB_USER -p$DB_PASSWD"
if [ ! ${NO_DB:-""} ]; then
	MYSQL_COMMAND="$MYSQL_COMMAND -D $DB_NAME" 
fi
MYSQL_COMMAND="$MYSQL_COMMAND $MYSQL_OPTS"

if [ ${SQL_FILE:-""} ]; then
	echo "Running SQL input from $SQL_FILE"
	$MYSQL_COMMAND < $SQL_FILE
else
	while read line; do
		echo $line | $MYSQL_COMMAND
		if [ $? != 0 ]; then
			return $?
		fi
	done
fi

return $?
