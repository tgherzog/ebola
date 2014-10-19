#!/bin/bash

# This script builds the cases file, and if the date is different than previously run, mails the file to the destination address
DEST="therzog1@worldbank.org"
BASE=`dirname $0`

LAST_RUN=`cat "$BASE/last_run"`
THIS_RUN=`php "$BASE/cases.php" --cache=0 --status=date`


if [ "$LAST_RUN" != "$THIS_RUN" ]; then
echo $THIS_RUN > "$BASE/last_run"
mail -r tim@zognet.net -s "New ebola case data: $THIS_RUN" -a "$BASE/data/ebola-cases.xlsx" $DEST << END_OF_MAIL
New case data posted: $THIS_RUN

END_OF_MAIL
fi
