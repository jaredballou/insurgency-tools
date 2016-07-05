#!/bin/bash
APPID=$1
APPINFODIR=$(dirname $0)/../../public/data/appdata
STEAMCMD=~/steamcmd/steamcmd.sh

if [ ! -e "${APPINFODIR}" ]
then
    	mkdir -p "${APPINFODIR}"
fi
$STEAMCMD +app_info_print ${APPID} +quit | sed -n -e "/\"${APPID}\"/,\$p" > "${APPINFODIR}/${APPID}.txt"
