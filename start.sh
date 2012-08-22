#!/bin/bash
rundir=$(dirname "$(readlink -e "$0")")
pidfile=$rundir/bot.pid
(nohup php -q -f bot.php > /dev/null) & echo $! > $pidfile &
