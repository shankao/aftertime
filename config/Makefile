all: .current.site aftertime.json

CODE_REVNO:=$(shell git log | grep "commit " | wc -l)

.current.site:
	echo "example_site" > .current.site

aftertime.json: aftertime.json.in .current.site
	cp aftertime.json.in aftertime.json; 
	sed -i "s/__REVNO__/$(CODE_REVNO)/g" aftertime.json; 
	sed -i "s/__SITE__/`cat .current.site`/g" aftertime.json;

clean:
	-rm aftertime.json

.PHONY: clean all
