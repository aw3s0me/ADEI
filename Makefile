JAVA = java
YUI = includes/yuicompressor-2.4.jar

.PHONY: all error compress-js compress-css

all: compress-js

compress-js: js/*.js
	cat js/*.js js/xmlmodule/*.js > adei.uncompressed.js; \
	${JAVA} -jar ${YUI} -o adei.js adei.uncompressed.js; \
	rm -f adei.uncompressed.js; \
	for setup in setups/*; do \
	    if [ -d $$setup/js ]; then \
	        name=`basename $$setup`; \
	        cat $$setup/js/*.js $$setup/js/xmlmodule/*.js > $$setup/$$name.uncompressed.js 2>/dev/null; \
		${JAVA} -jar ${YUI} -o $$setup/$$name.js $$setup//$$name.uncompressed.js; \
		rm -f $$setup/$$name.uncompressed.js; \
	    fi; \
	done


error:
	for script in js/*.js; do \
	    echo Checking $$script; \
	    ${JAVA} -jar ${YUI} $$script > /dev/null || \
	    ( ${JAVA} -jar ${YUI} ${script}; exit 1; ); \
	done
	    

