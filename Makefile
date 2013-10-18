CODE_REVNO:=$(shell git log | grep "commit " | wc -l)
BUILDPATH=build
PACKAGESPATH=packages

# Figure out the site we're working on
AVAILABLE_SITES:=$(shell ls sites)
SITE_FILE:=config/.current.site
ifeq ($(wildcard $(SITE_FILE)),)
	CURRENT_SITE := $(firstword $(AVAILABLE_SITES))
else
  ifeq ($(wildcard $(addprefix "sites/", $(shell cat $(SITE_FILE)))),)
	CURRENT_SITE := $(firstword $(AVAILABLE_SITES))
  else
	CURRENT_SITE := $(shell cat $(SITE_FILE))
  endif
endif

ifndef VERBOSE
        MAKEFLAGS += --no-print-directory
endif

ifneq ($(shell php scripts/getconfig.php | grep "database.host" | cut -f2 -d"="),)
	DB_DEFINED := yes
endif

all:
	$(MAKE) db-drop
	$(MAKE) build
	$(MAKE) db-restore FILE=sites/${CURRENT_SITE}/db/example_data.sql; \

checkenv:
	@if [ ! "${AVAILABLE_SITES}" ]; then \
		echo "Error no available sites"; \
		return 255; \
	fi;
	echo $(CURRENT_SITE) > $(SITE_FILE)
	$(MAKE) clean-config config

config: config/aftertime.json.in
	(cd config; \
		cp aftertime.json.in aftertime.json; \
		sed -i "s/__REVNO__/$(CODE_REVNO)/g" aftertime.json; \
		sed -i "s/__SITE__/`cat .current.site`/g" aftertime.json; \
	)

clean-config:
	-rm config/aftertime.json

build:
	$(MAKE) clean-build
	$(MAKE) checkenv
	git add --all
	git checkout-index -a -f --prefix=${BUILDPATH}/
	git reset
	# Remove the rest of the sites from the build folder
	if [ "${CURRENT_SITE}" != "${AVAILABLE_SITES}" ]; then \
		rm -r $(addprefix ${BUILDPATH}/sites/,$(filter-out ${CURRENT_SITE}, $(AVAILABLE_SITES))); \
	fi;
	# Copy aftertime's build config
	cp config/aftertime.json ${BUILDPATH}/config
	$(MAKE) root-content 
	$(MAKE) .htaccess
	$(MAKE) logs-folder

logs-folder:
	mkdir ${BUILDPATH}/logs
	chmod uog+w ${BUILDPATH}/logs

info:
	@echo "Available sites: ${AVAILABLE_SITES}"
	@echo "Current site: ${CURRENT_SITE}"
	@echo "Site has DB defined: ${DB_DEFINED}"

$(AVAILABLE_SITES):
	@echo "Setting site to $@"
	echo $@ > $(SITE_FILE)
	$(MAKE) all

clean-build:
	if [ -d "${BUILDPATH}" ]; then \
		chmod -R u+rxw "${BUILDPATH}"; \
		rm -rf "${BUILDPATH}"; \
	fi;

clean-packages:
	if [ -d "${PACKAGESPATH}" ]; then \
		chmod -R u+rxw "${PACKAGESPATH}"; \
		rm -rf "${PACKAGESPATH}"; \
	fi;

clean:
	$(MAKE) db-drop
	$(MAKE) clean-build
	$(MAKE) clean-packages
	$(MAKE) clean-config

	
package: 
	$(MAKE) build BUILDPATH=${PACKAGESPATH}/${CURRENT_SITE}_$(CODE_REVNO)
	cp scripts/upgrade.sh ${PACKAGESPATH}
	(cd ${PACKAGESPATH} && zip -rq ${CURRENT_SITE}_$(CODE_REVNO).zip ${CURRENT_SITE}_$(CODE_REVNO) upgrade.sh)
	rm -rf ${PACKAGESPATH}/${CURRENT_SITE}_$(CODE_REVNO)

.PHONY: info config clean-packages clean-config clean-build clean all $(AVAILABLE_SITES) package build

db-drop:
	if [ "$(DB_DEFINED)" ]; then \
		echo "DROP SCHEMA IF EXISTS ${CURRENT_SITE}" | ./scripts/runmysql.sh -n; \
	fi; 

db-create: 
	if [ "$(DB_DEFINED)" ]; then \
		echo "CREATE SCHEMA IF NOT EXISTS ${CURRENT_SITE} DEFAULT CHARACTER SET utf8" | ./scripts/runmysql.sh -n; \
	fi;

db-restore: 
	@if [ "$(DB_DEFINED)" ]; then \
		if [ ! "${FILE}" ]; then \
			echo "Please, indicate the file to load in the FILE variable"; \
		else \
			if [ -f "${FILE}" ]; then \
				$(MAKE) db-drop; \
				$(MAKE) db-create; \
				if [ -f sites/${CURRENT_SITE}/db/schema.sql ]; then \
					echo "Adding DDLs"; \
					$(MAKE) db-load FILE=sites/${CURRENT_SITE}/db/schema.sql; \
					echo "Adding example data"; \
					$(MAKE) db-load FILE=${FILE}; \
				fi; \
			else \
				echo "File does not exists: ${FILE}"; \
			fi; \
		fi; \
	fi;

# TODO Add support to ignore some tables. I.e.: spidey.page_status could duplicate values and they are UNIQUE. Also, is part of the DDL somehow
db-snap: 
	@echo Snap DB
	./scripts/runmysqldump.sh -o sites/${CURRENT_SITE}/db/snap.sql

db-load:
	@if [ ! "${FILE}" ]; then \
		echo "Please, indicate the file to load in the FILE variable"; \
	else \
		if [ -f "${FILE}" ]; then \
			echo "Loading file ${FILE}"; \
			./scripts/runmysql.sh -f ${FILE}; \
		else \
			echo "File does not exists: ${FILE}"; \
		fi; \
	fi;

# TODO Backup. Does an snap, compress it and sends it somewhere. Check what we want, this does not differ from a snap so much

.PHONY: db-create db-restore db-drop db-snap db-load

# TODO Symlinks method should only be used if mod_rewrite is not around
ROOT_CONTENT:=$(shell php scripts/getconfig.php | grep "root-content" | cut -f2 -d"=")
root-content: ${ROOT_CONTENT}

${ROOT_CONTENT}: 
	@(cd ${BUILDPATH}; \
	if [ ! -h $(notdir $@) ]; then \
		if [ -e $(notdir $@) ]; then \
			echo "ERROR: $(notdir $@) cannot be overwritten"; \
			exit 1; \
		fi; \
		if [ ! -e sites/${CURRENT_SITE}/$@ ]; then \
			echo "ERROR: root-content sites/${CURRENT_SITE}/$@ does not exist"; \
			exit 1; \
		fi; \
		ln -v -s sites/${CURRENT_SITE}/$@ ./$(notdir $@); \
        fi; \
	)

.PHONY: root-content 

.htaccess: templates/htaccess.php
	php scripts/ctemplate.php -t $^ > ${BUILDPATH}/$@
