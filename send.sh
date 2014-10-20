#!/bin/bash

# This script builds the cases file, and if the date is different than previously run, mails the file to the destination address
DEST=$@
BASE=`dirname $0`

if [ $# -le 0 ]; then
  echo "Usage: $0 recipient@somewhere.com [more recipients]"
  exit -1
fi

LAST_RUN=`cat "$BASE/last_run-cases"`
THIS_RUN=`php "$BASE/cases.php" --cache=0 --status=date`


if [ "$LAST_RUN" != "$THIS_RUN" ]; then
echo $THIS_RUN > "$BASE/last_run-cases"
mail -r tim@zognet.net -s "New ebola case data: $THIS_RUN" -a "$BASE/data/ebola-cases.xlsx" $DEST << END_OF_MAIL
New case data posted: $THIS_RUN

END_OF_MAIL
fi
