#!/bin/bash
rundir=$(dirname "$(readlink -e "$0")")
pidfile=$rundir/bot.pid
pid=`cat $pidfile`
if [ -e /proc/$pid ]; then
  kill -15 $pid
  rm $pidfile
fi
