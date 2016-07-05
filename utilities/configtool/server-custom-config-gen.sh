#!/bin/bash
################################################################################
# (c) 2015, Jared Ballou <insurgency@jballou.com>
# Released under GPLv2
#
# server-custom-config-gen.sh - Create copies of stock configs and create stubs
# to execute them for Insurgency. This script is designed to get around the fact
# that the base game will overwrite all configs on each update. This will try to
# mirror the old config to custom_$CFG and make the normal config just call the
# custom script which we have modified. If the game has updated the stock config
# it will print the differences between the new stock config and your custom
# config before modifying the stock file again.
#
################################################################################

#Default config path if not sent as a parameter
CFGPATH=${1:-/home/insserver/serverfiles/insurgency/cfg}

echo "Processing $CFGPATH"

for CFG in $(ls $CFGPATH | grep '^server_.*\.cfg$')
do
	OLDCFG=$(cat "$CFGPATH/$CFG")
	NEWCFG="exec custom_$CFG"
	if [ "$OLDCFG" != "$NEWCFG" ]
	then
		echo "Updating $CFG"
		if [ ! -e "$CFGPATH/custom_$CFG" ]
		then
			echo "Copying $CFG to custom_$CFG"
			rsync "$CFGPATH/$CFG" "$CFGPATH/custom_$CFG"
			sed -i -e "s/exec custom_$CFG//" "$CFGPATH/custom_$CFG"
		else
			echo "Stock config has changed! Displaying difference between new stock and your custom config"
			diff "$CFGPATH/$CFG" "$CFGPATH/custom_$CFG"
		fi
		echo "exec custom_$CFG" > "$CFGPATH/$CFG"
	fi
done

