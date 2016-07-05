#!/bin/bash
URL="http://savannahgroup.site.nfoservers.com/dnld/"
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


# Maps blacklist (will skip downloads and clean up date based upon these items)
BLACKLIST="${DATADIR}/thirdparty/maps-blacklist.txt"

BLACKLIST_EGREP="^($(echo $(cat "${BLACKLIST}" | sed -e 's/\./\\\./g' -e 's/\*/\.\*/') | sed -e 's/[ \t\n\r]\+/\|/g'))"

for MAP in $(curl -s "${URL}" | grep -o '<a href=\([^>]\+\)>' | cut -d'"' -f2 | grep -i '\.zip$' | cut -d'.' -f1 | sort -u); do
	# Don't do blacklisted maps
	if [[ "${MAP}.bsp" =~ ${BLACKLIST_EGREP} ]]; then
#		echo ">> Skipping $(basename "${MAP}") for blacklist"
		continue
	fi
	if [ ! -e "${MAPSDIR}/${MAP}.bsp" ] && [ ! -e "${GAMEDIR}/maps/${MAP}.bsp" ]; then
		SRC="${URL}${MAP}.zip"
		DST="${MAPSDIR}/${MAP}.zip"
		wget "${SRC}" -O "${DST}"
		unzip "${DST}" -d "${MAPSDIR}"
		rm "${DST}"
	fi
done
