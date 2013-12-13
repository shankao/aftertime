CODE_REVNO:=$(shell git log | grep "commit " | wc -l)
BUILDPATH=build
PACKAGESPATH=packages

# Figure out the site we're working on
AVAILABLE_SITES:=$(shell ls sites)
SITE_FILE:=config/.current.site
ifeq ($(wildcard $(SITE_FILE)),)
	CURRENT_SITE := $(firstword $(AVAILABLE_SITES))
else
  ifeq ($(wildcard $(addprefix sites/, $(shell cat $(SITE_FILE)))),)
	CURRENT_SITE := $(firstword $(AVAILABLE_SITES))
  else
	CURRENT_SITE := $(shell cat $(SITE_FILE))
  endif
endif

ifndef VERBOSE
        MAKEFLAGS += --no-print-directory
endif

ifneq ($(shell (cd ${BUILDPATH} 2>&1 && php ./scripts/getconfig.php) | grep "database.host" | cut -f2 -d"="),)
	DB_DEFINED := yes
endif

all:
	$(MAKE) db-drop
	$(MAKE) build
	$(MAKE) db-restore FILE=sites/${CURRENT_SITE}/db/example_data.sql;
	if [ -f ${BUILDPATH}/sites/${CURRENT_SITE}/Makefile ]; then \
		$(MAKE) -C ${BUILDPATH}/sites/${CURRENT_SITE} all; \
	fi;

checkenv:
	@if [ ! "${AVAILABLE_SITES}" ]; then \
		echo "Error no available sites"; \
		return 255; \
	fi;
	echo $(CURRENT_SITE) > $(SITE_FILE)
	$(MAKE) config

config: config/aftertime.json.in
	mkdir ${BUILDPATH}/config
	cp config/aftertime.json.in ${BUILDPATH}/config/aftertime.json; \
	sed -i "s/__REVNO__/$(CODE_REVNO)/g" ${BUILDPATH}/config/aftertime.json; \
	sed -i "s/__SITE__/`cat ${SITE_FILE}`/g" ${BUILDPATH}/config/aftertime.json; \

build:
	$(MAKE) clean-build
	$(MAKE) build-folder
	$(MAKE) content-folder
	$(MAKE) checkenv
	git add --all
	git checkout-index -a -f --prefix=${BUILDPATH}/
	git reset
	# Remove the rest of the sites from the build folder
	if [ "${CURRENT_SITE}" != "${AVAILABLE_SITES}" ]; then \
		rm -r $(addprefix ${BUILDPATH}/sites/,$(filter-out ${CURRENT_SITE}, $(AVAILABLE_SITES))); \
	fi;
	$(MAKE) root-content 
	$(MAKE) ${BUILDPATH}/.htaccess

# Must take this folder name from the config...
content-folder:
	mkdir ${BUILDPATH}/$(subst -folder,,$@)
	chmod uog+w ${BUILDPATH}/$(subst -folder,,$@)

info:
	@echo "Available sites: ${AVAILABLE_SITES}"
	@echo "Current site: ${CURRENT_SITE}"
	@echo "Site has DB defined: ${DB_DEFINED}"

print-config:
	@(cd ${BUILDPATH} && php scripts/getconfig.php)	

$(AVAILABLE_SITES):
	@echo "Setting site to $@"
	echo $@ > $(SITE_FILE)
	$(MAKE) all

build-folder:
	mkdir -p ${BUILDPATH}

clean-build:
	if [ -d "${BUILDPATH}" ]; then \
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

package: 
	$(MAKE) build BUILDPATH=${PACKAGESPATH}/${CURRENT_SITE}_$(CODE_REVNO)
	cp ${BUILDPATH}/scripts/upgrade.sh ${PACKAGESPATH}
	(cd ${PACKAGESPATH} && zip -rq ${CURRENT_SITE}_$(CODE_REVNO).zip ${CURRENT_SITE}_$(CODE_REVNO) upgrade.sh)
	rm -rf ${PACKAGESPATH}/${CURRENT_SITE}_$(CODE_REVNO)

.PHONY: info print-config config clean-packages build-folder clean-build content-folder clean all $(AVAILABLE_SITES) package build

db-drop:
	if [ "$(DB_DEFINED)" ]; then \
		echo "DROP SCHEMA IF EXISTS ${CURRENT_SITE}" | (cd ${BUILDPATH}; ./scripts/runmysql.sh -n); \
	fi; 

db-create: 
	if [ "$(DB_DEFINED)" ]; then \
		echo "CREATE SCHEMA IF NOT EXISTS ${CURRENT_SITE} DEFAULT CHARACTER SET utf8" | (cd ${BUILDPATH}; ./scripts/runmysql.sh -n); \
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
	@if [ ! "${FILE}" ]; then \
		echo "Please, indicate the file to store the snap to, in the FILE variable"; \
	else \
		echo "Storing DB snap on ${FILE}"; \
		(cd ${BUILDPATH}; \
			./scripts/runmysqldump.sh -o ../${FILE}; \
		) \
	fi;

db-load:
	@(cd ${BUILDPATH}; \
		if [ ! "${FILE}" ]; then \
			echo "Please, indicate the file to load in the FILE variable"; \
		else \
			if [ -f "${FILE}" ]; then \
				echo "Loading file ${FILE}"; \
				./scripts/runmysql.sh -f ${FILE}; \
			else \
				echo "File does not exists: ${FILE}"; \
			fi; \
		fi; \
	)

# TODO Backup. Does an snap, compress it and sends it somewhere. Check what we want, this does not differ from a snap so much

.PHONY: db-create db-restore db-drop db-snap db-load

# TODO Symlinks method should only be used if mod_rewrite is not around
ROOT_CONTENT:=$(shell (cd ${BUILDPATH} 2>&1 && php scripts/getconfig.php) | grep "root-content" | cut -f2 -d"=")
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

%.htaccess: templates/htaccess.php
	@(cd ${BUILDPATH}; \
		php ./scripts/ctemplate.php -t $^ > .htaccess; \
	)
