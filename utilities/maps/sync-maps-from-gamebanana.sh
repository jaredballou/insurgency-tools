#!/bin/bash

LIST_URL="http://insurgency.gamebanana.com/maps"
DL_URL="http://files.gamebanana.com/maps/"
MAPSDIR=/opt/fastdl/maps
STARTDIR=$(pwd)

# Get OS
SYSTEM=$(uname -s)

# Script name and directory
SCRIPTNAME=$(basename $(readlink -f "${BASH_SOURCE[0]}"))
SCRIPTDIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# insurgency-tools dir
REPODIR="$(cd "${SCRIPTDIR}/../.." && pwd)"

# Data dir
DATADIR="${REPODIR}/public/data"

GAMEDIR="$(cd "${REPODIR}/../serverfiles/insurgency" && pwd)"

CACHEDIR="${REPODIR}/cache/maps"

# Maps blacklist (will skip downloads and clean up date based upon these items)
BLACKLIST="${DATADIR}/thirdparty/maps-blacklist.txt"

BLACKLIST_EGREP="^($(echo $(cat "${BLACKLIST}" | sed -e 's/\./\\\./g' -e 's/\*/\.\*/') | sed -e 's/[ \t\n\r]\+/\|/g'))"

if [ ! -e "${CACHEDIR}" ]; then
	mkdir -vp "${CACHEDIR}"
fi

fn_getmap() {
	MAP_URL="${LIST_URL}/${1}"
	MAP_DL_URL="${LIST_URL}/download/${1}"
	echo ">> Fetching ${MAP_DL_URL}"
	basename $(curl -s "${MAP_DL_URL}" | grep -o "href=\"${DL_URL}[^>]\+>" | cut -d'"' -f2) | sed -e 's/\.[^\.]\+$//g'
}
fn_getpage() {
	PAGE="${1:-$LIST_URL}"
	echo "> Processing ${PAGE}"
	CMD="curl -s \"${PAGE}\" | grep -o \"<a href=.\?${LIST_URL}[^>]\+>[^<]*\" | cut -d'\"' -f2 | sed -e \"s#^${LIST_URL}[/\?]*##g\" | egrep '^(vl\[page\].*|[0-9]*)\"' | sort -u"
echo $CMD
	for LINK in $(${CMD}); do
		if [ $(echo "${LINK}" | grep -c '\[page\]') -gt 0 ]; then
			if [ -z $2 ]; then
				fn_getpage "${LIST_URL}?$(echo "${LINK}" | sed -e 's/.page./\\\[page\\\]/g')" 1
			fi
		else
			fn_getmap "${LINK}"
		fi
	done
}
fn_getpage







fn_nope() {
for MAP in $(curl -s "${URL}" | grep -o '<a href=\([^>]\+\)>[^<]\+' | cut -d'"' -f2 | grep -i '\.zip$' | cut -d'.' -f1 | sort -u); do
	# Don't do blacklisted maps
	if [[ "${MAP}.bsp" =~ ${BLACKLIST_EGREP} ]]; then
#		echo ">> Skipping $(basename "${MAP}") for blacklist"
		continue
	fi
	if [ ! -e "${MAPSDIR}/${MAP}.bsp" ] && [ ! -e "${GAMEDIR}/maps/${MAP}.bsp" ]; then
		SRC="${URL}${MAP}.zip"
		DST="${MAP}.zip"
		CMD="cd \"${MAPSDIR}\" && wget \"${SRC}\" && unzip \"${DST}\" && rm \"${DST}\""
		echo $CMD
	fi
done
}
