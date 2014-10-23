# Requires php-pear.deb package 
# TODO: Get the dependencies per-project

MDB2:=MDB2-2.5.0b5
MDB2_MYSQLI:=MDB2_Driver_mysqli-1.5.0b4
DATAOBJECT:=DB_DataObject-1.10.0
JQUERY_VERSION=1.9.1
SYSTEM_DAEMON=System_Daemon-1.0.0

all: install

show:
	pear -c .pearrc list

clean:
	-rm -rf pear .pearrc

pear-config:
	# TODO check that the pear binary is installed in the system
	pear config-create `pwd` .pearrc

pear: pear-config
	pear -c .pearrc install $(DATAOBJECT) $(MDB2) $(MDB2_MYSQLI) $(SYSTEM_DAEMON)

jquery: jquery-$(JQUERY_VERSION).min.js

jquery-$(JQUERY_VERSION).min.js:
	wget http://code.jquery.com/jquery-$(JQUERY_VERSION).min.js -N

install: clean
	make pear

.PHONY: all show jquery
