#!/bin/bash

# This script builds the cases file, and if the date is different than previously run, mails the file to the destination address
DEST=$@
BASE=`dirname $0`

# global configuration
SENDER="do-not-reply@zognet.net"
LOG="$BASE/send_log"
LOG_DATE="`date "+%D %T"`"
DATE_FILE="$BASE/last_run"
TMP="/tmp/send-$$"

# Truncated recipient list, used only in log messages
LOG_DEST=$DEST
if [ ${#LOG_DEST} -gt 60 ]; then
  LOG_DEST="${LOG_DEST:0:60}..."
fi


if [ $# -le 0 ]; then
  echo "Usage: $0 recipient@somewhere.com [more recipients]"
  exit -1
fi

# Do case data
OUTPUT="ebola-cases.xlsx"	# should match the output file from the build script

LAST_RUN=`grep '^cases' $DATE_FILE | cut -d ':' -f 2`
REPORT=`php "$BASE/cases.php" --cache=0 --status=1`
THIS_RUN=`echo "$REPORT" | grep "^Most recent date:" | sed -r 's/.+:\\s+//'`

if [ "$LAST_RUN" != "$THIS_RUN" ]; then
sed "s|^cases:.*|cases:$THIS_RUN|" $DATE_FILE > $TMP && mv $TMP $DATE_FILE
mail -r $SENDER -s "Ebola Case Data as of $THIS_RUN - $OUTPUT" -a "$BASE/data/$OUTPUT" $DEST << END_OF_MAIL
$REPORT
END_OF_MAIL
printf "%s - New case data ($THIS_RUN) sent to $LOG_DEST\n" "$LOG_DATE" >> "$LOG"

else

printf "%s - No new case data (most recent data: $THIS_RUN)\n" "$LOG_DATE" >> "$LOG"

fi

# Do price/consumption data
OUTPUT="ebola-economy.xlsx"	# should match the output file from the build script

LAST_RUN=`grep '^econ' $DATE_FILE | cut -d ':' -f 2`
THIS_RUN=`php "$BASE/econ.php" --cache=0 --status=date`


if [ "$LAST_RUN" != "$THIS_RUN" ]; then
sed "s|^econ:.*|econ:$THIS_RUN|" $DATE_FILE > $TMP && mv $TMP $DATE_FILE
mail -r $SENDER -s "Ebola Market Data as of $THIS_RUN - $OUTPUT" -a "$BASE/data/$OUTPUT" $DEST << END_OF_MAIL
New case data posted: $THIS_RUN

END_OF_MAIL
printf "%s - New market data ($THIS_RUN) sent to $LOG_DEST\n" "$LOG_DATE" >> "$LOG"

else

printf "%s - No new market data (most recent data: $THIS_RUN)\n" "$LOG_DATE" >> "$LOG"

fi

# WFP indicators
OUTPUT="ebola-wfp.xlsx"	# should match the output file from the build script
LAST_RUN=`grep '^wfp' $DATE_FILE | cut -d ':' -f 2`
REPORT=`php "$BASE/wfp.php" --cache=0 --status=1`
THIS_RUN=`echo "$REPORT" | grep "^Most recent date:" | sed -r 's/.+:\\s+//'`
if [ "$LAST_RUN" != "$THIS_RUN" ]; then
sed "s|^wfp:.*|wfp:$THIS_RUN|" $DATE_FILE > $TMP && mv $TMP $DATE_FILE
mail -r $SENDER -s "Ebola WFP Data as of $THIS_RUN - $OUTPUT" -a "$BASE/data/$OUTPUT" $DEST << END_OF_MAIL
$REPORT
END_OF_MAIL
printf "%s - New WFP data ($THIS_RUN) sent to $LOG_DEST\n" "$LOG_DATE" >> "$LOG"

else

printf "%s - No new WFP data (most recent data: $THIS_RUN)\n" "$LOG_DATE" >> "$LOG"

fi
