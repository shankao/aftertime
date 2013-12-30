#!/bin/sh
#
# This script allows you to deploy the site and control it after that
#
# XXX How to deal with the first install? Just a simple unzip should be enough, but...
# TODO Check if needs to create a writable logs folder
# TODO Use a better command name (deploy?)
# TODO Call the site-specific install script
# TODO Define how to deal with whole machine vs. shared hosting installs. One of the objectives that I wanted to pursue is scalability... maybe plan for the former and then try a best-effort for the later
# TODO Think about user permissions and security for machine-wide installs
# XXX Shared hostings that cannot create new DB's directly don't work with the current db-init stage
# XXX The schema definition contains the name of the site DB in an "USE" clause. This could not match the name in the config file
#
set -o nounset

usage() {
	echo Usage:
	echo $0
}

if [ $# -ne 2 ]; then
	usage;
	exit 1
fi

ACTION=$1
TARGET=$2

get_siterev() {
	SITE=$(echo "$TARGET" | cut -d "_" -f1)
	REVISION=$(echo "$TARGET" | cut -d "_" -f2 | cut -d "." -f1)
	SITEREV=$SITE"_"$REVISION
}

config_check() {
	check_first_install
	if [ $? != 0 ]; then
		echo "This is the first install of $SITE";
		return 0;
	fi

	get_siterev
	echo "Showing differences between $SITE and $SITEREV"

	CONFFILES="config/aftertime.json `(cd $SITE && ls sites/$SITE/config/*.json)` `(cd $SITEREV && ls sites/$SITE/config/*.json)`"
	CONFFILES="`echo $CONFFILES | tr \" \" \"\\n\" | sort -u`"
	for filename in $CONFFILES; do
		if [ ! -f $SITE/$filename ]; then
			echo "New: $SITEREV/$filename"
		elif [ ! -f $SITEREV/$filename ]; then
			echo "Deleted: $SITE/$filename"
		else
			DIFF=`diff $SITE/$filename $SITEREV/$filename`
			if [ "$DIFF" ]; then
				echo "Modified: (vimdiff $SITE/$filename $SITEREV/$filename)"
			else
				echo "Equals: $filename"
			fi
		fi
		# Write-protect the file. Only if hasn't being deleted
		# chmod uog-w $TARGET/config/$SITE.json
	done
}

check_first_install() {
	get_siterev
	if [ -d $SITE ]; then
		return 0
	else
		return 1
	fi
}

switch_to_target() {
	get_siterev
	echo "Switching $SITE to revision $REVISION"
	chmod uog-w $SITEREV
	ln -s $SITEREV "new_"$SITE
	mv -T "new_"$SITE $SITE
}

case $ACTION in
	install)	# TODO test me
		echo "Uncompressing $TARGET"
		unzip -uq -o $TARGET
                if [ $? != 0 ]; then
	                exit $?
                fi
		get_siterev
		chmod uog-w $SITE"_"$REVISION
		config_check
		if [ $? = 0 ]; then
			switch_to_target
			if [ $? != 0 ]; then
				echo "ERROR: cannot switch site";
				exit $?
			fi
		fi
		;;
	config-check)
		config_check
		;;
	switch)
		if [ ! -d $TARGET ]; then
			echo "ERROR: $TARGET is not installed"
                        exit 1
		fi
		switch_to_target
		if [ $? != 0 ]; then
			echo "ERROR: cannot switch site";
			exit $?
		fi
		;;
	db-init)
		echo "Initialize the database schema"
                (cd $SITE
                        echo "CREATE SCHEMA \"$SITE\" DEFAULT CHARACTER SET utf8;" | ./scripts/runmysql.sh -n
			if [ $? != 0 ]; then
                                echo ERROR creating the initial DB. Already exists?
                                exit $?
                        fi
                        if [ -f $SITE/db/schema.sql ]; then
                                echo "Adding initial DDLs";
                                ./scripts/runmysql.sh -f "$SITE/db/schema.sql"
                                if [ $? != 0 ]; then
                                        echo ERROR adding initial DDLs
                                        exit $?
                                fi
                        fi;
                )
                echo All done.
		;;
	db-snap)
		get_siterev
		echo "Dumping database from $SITE"
		(cd $SITE;
			./scripts/runmysqldump.sh -o ../$SITE.sql
		)
		;;
	*)
		echo "Wrong params"
esac
echo Done
