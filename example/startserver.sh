#!/bin/sh

set -e

TMPFILE=$(tempfile)
SCRIPTPATH=$(realpath $(dirname $0))

(
cat <<EOF
service echoserver
{
	type            = UNLISTED
	port            = 9988
	socket_type     = stream
	wait            = no
	user            = seb
	server          = $SCRIPTPATH/echoserver.php
	disable         = no
}
EOF
) > $TMPFILE

/usr/sbin/xinetd -d -f $TMPFILE

rm -f $TMPFILE

