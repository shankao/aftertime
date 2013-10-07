AVAILABLE_SITES:=$(shell ls sites)
SITE_FILE:=config/.current.site
CURRENT_SITE := $(shell cat $(SITE_FILE))
CODE_REVNO:=$(shell git log | grep "commit " | wc -l)
BUILDPATH=build
PACKAGESPATH=packages

ifndef VERBOSE
        MAKEFLAGS += --no-print-directory
endif

all:
	$(MAKE) clean-build
	$(MAKE) build
	$(MAKE) db-restore FILE=sites/${CURRENT_SITE}/db/example_data.sql

build: logs
	if [ -d ${BUILDPATH} ]; then \
		chmod -R u+rxw ${BUILDPATH}; \
	        rm -rf ${BUILDPATH}; \
	fi;
	$(MAKE) -C config all
	git checkout-index -a -f --prefix=${BUILDPATH}/
	# Remove the rest of the sites from the build folder
	rm -r $(addprefix ${BUILDPATH}/sites/,$(filter-out ${CURRENT_SITE}, $(AVAILABLE_SITES)))
	# Copy aftertime's build config
	cp config/aftertime.json ${BUILDPATH}/config
	$(MAKE) root-content 
	$(MAKE) .htaccess

$(SITE_FILE):
	$(MAKE) -C config .current.site

info:
	@echo "Available sites: ${AVAILABLE_SITES}"
	@echo "Current site: ${CURRENT_SITE}"

$(AVAILABLE_SITES):
	@echo Setting site to $@
	echo $@ > $(SITE_FILE)
	$(MAKE) all

clean-build: 
	$(MAKE) db-drop
	-chmod -R u+rxw build;
	-rm -rf build

clean:
	$(MAKE) clean-build
	-chmod -R u+rxw packages;
	-rm -rf packages

logs:
	mkdir $@
	chmod uog+w $@

package: 
	$(MAKE) build BUILDPATH=${PACKAGESPATH}/${CURRENT_SITE}_$(CODE_REVNO)
	cp scripts/upgrade.sh ${PACKAGESPATH}
	(cd ${PACKAGESPATH} && zip -rq ${CURRENT_SITE}_$(CODE_REVNO).zip ${CURRENT_SITE}_$(CODE_REVNO) upgrade.sh)
	rm -rf ${PACKAGESPATH}/${CURRENT_SITE}_$(CODE_REVNO)

.PHONY: get-site clean-build clean all $(AVAILABLE_SITES) package build

db-drop: 
	echo "DROP SCHEMA IF EXISTS ${CURRENT_SITE}" | ./scripts/runmysql.sh -n

db-create: 
	echo "CREATE SCHEMA IF NOT EXISTS ${CURRENT_SITE} DEFAULT CHARACTER SET utf8" | ./scripts/runmysql.sh -n;

db-restore: 
	@if [ -f "${FILE}" ]; then \
		$(MAKE) db-drop; \
		$(MAKE) db-create; \
		if [ -f sites/${CURRENT_SITE}/db/schema.sql ]; then \
			echo "Adding DDLs"; \
			$(MAKE) db-load FILE=sites/${CURRENT_SITE}/db/schema.sql; \
			echo "Adding example data"; \
			$(MAKE) db-load FILE=${FILE}; \
		fi; \
	else \
		echo "Please, indicate the file to load in the FILE variable"; \
	fi;

# TODO Add support to ignore some tables. I.e.: spidey.page_status could duplicate values and they are UNIQUE. Also, is part of the DDL somehow
db-snap: 
	@echo Snap DB
	./scripts/runmysqldump.sh -o sites/${CURRENT_SITE}/db/snap.sql

db-load: 
	@if [ -f "${FILE}" ]; then \
		echo "Loading file ${FILE}"; \
		./scripts/runmysql.sh -f ${FILE}; \
	else \
		echo "Please, indicate the file to load in the FILE variable"; \
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
