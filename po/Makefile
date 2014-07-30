all: mo

.PHONY: messages.pot po mo

mo: po
	for name in ??.po; do \
	cat=`basename $$name .po`; \
	mkdir -p $$cat/LC_MESSAGES/; \
	msgfmt -o $$cat/LC_MESSAGES/adei.mo $$name extra-$$name;\
	done

po: message.pot
	for name in ??.po; do \
	cat=`basename $$name .po`; \
	msgmerge -o $$cat.po $$name messages.pot;\
	touch extra-$$cat.po;\
	done

message.pot:
	phpfiles=`find ../ -name "*.php"`; \
	xgettext -klgs $$phpfiles; \
	mv messages.po messages.pot

