#!/bin/bash
################################################################################
# Insurgency Data Extractor
# (C) 2014,2015,2016 Jared Ballou <instools@jballou.com>
# Extracts all game file information to the data repo
################################################################################

# Which commands to run
EXTRACTFILES=0
GETMAPS=0
REMOVEBLACKLISTMAPS=0
DECOMPILEMAPS=0
SYNC_MAPS_TO_DATA=0
COPY_MAP_FILES_TO_DATA=0
CONVERT_VTF=0
MAPDATA=0
FULL_MD5_MANIFEST=0
CLEAN_MANIFEST=0
SORT_MANIFEST=0
GITUPDATE=0


# Get OS
SYSTEM=$(uname -s)

if [ "${SYSTEM}" == "Linux" ]; then
	# Script name and directory
	SCRIPTNAME=$(basename $(readlink -f "${BASH_SOURCE[0]}"))
	SCRIPTDIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
	# insurgency-tools dir
	REPODIR="$(cd "${SCRIPTDIR}/../.." && pwd)"
	# Game dir
	GAMEDIR="$(cd "${REPODIR}/../serverfiles/insurgency" && pwd)"
	# VPK Converter
	VPK="$(readlink -f "${SCRIPTDIR}/../vpk/vpk.php")"
	# VTF2TGA Converter
	VTF2TGA="$(readlink -f "${SCRIPTDIR}/../vtf2tga/vtf2tga")"
else
	# Script name and directory
	SCRIPTNAME=$(basename "${BASH_SOURCE[0]}")
	SCRIPTDIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
	# insurgency-tools dir
	REPODIR="../.."
	# Game dir
	GAMEDIR="${REPODIR}/.."
	# VPK Converter
	VPK="${GAMEDIR}/../bin/vpk"
	# VTF2TGA Converter
	VTF2TGA="${SCRIPTDIR}/win/VTFCmd.exe"
fi
# Data dir
DATADIR="${REPODIR}/public/data"
# Maps source dir
MAPSDIR="${GAMEDIR}/maps"

# MD5 manifest file
MANIFEST_FILE="${DATADIR}/manifest.md5"

# Custom maps URL
MAPSRCURL="rsync://ins.jballou.com/fastdl/maps/"

# Get current version from steam.inf
VERSION="$(grep -i 'PatchVersion=[0-9\.]' "${GAMEDIR}/steam.inf" | cut -d'=' -f2)"

if [ "${VERSION}" == "" ]; then
	echo "Unable to determine game version!"
	exit
fi

# RSYNC command
RSYNC="rsync -av"
# BSPSRC command
BSPSRC="java -cp ${REPODIR}/thirdparty/bspsrc/bspsrc.jar info.ata4.bspsrc.cli.BspSourceCli -no_areaportals -no_cubemaps -no_details -no_occluders -no_overlays -no_rotfix -no_sprp -no_brushes -no_cams -no_lumpfiles -no_prot -no_visgroups"
# Pakrat command
PAKRAT="java -jar ${REPODIR}/thirdparty/pakrat/pakrat.jar"
# This version theater dir
VERDIR="${DATADIR}/mods/insurgency/${VERSION}"
TD="${VERDIR}/scripts/theaters"
# This version playlists dir
PD="${VERDIR}/scripts/playlists"
# Maps blacklist (will skip downloads and clean up date based upon these items)
BLACKLIST="${DATADIR}/thirdparty/maps-blacklist.txt"

# Directories to extract from bsp files using Pakrat
MAPSRCDIRS="materials/vgui/ materials/overviews/ resource/ maps/*.txt"

# List the paths to extract from the VPK files
VPKPATHS=(
	"insurgency_misc_dir scripts/theaters:${TD} scripts/playlists:${PD} resource:${DATADIR}/resource maps:${DATADIR}/maps"
	"insurgency_materials_dir materials/vgui:${DATADIR}/materials/vgui materials/overviews:${DATADIR}/materials/overviews"
)
# If the theater directory for this version is missing, extract files
if [ ! -d "${TD}" ]
then
	EXTRACTFILES=1
fi

# If theater files for this Steam version don't exist, unpack desired VPK files and copy theaters to data
# This is not the "best" way to track versions, but it works for now
function extractfiles()
{
	echo "> Extracting VPK files"
	for set in "${VPKPATHS[@]}"
	do
		VPKFILE=""
		for item in $set; do
			if [ "${VPKFILE}" == "" ]; then
				VPKFILE="${item}"
				continue
			fi
			IFS=':' read -r -a PATHS <<< "${item}"
			echo ">>> Extracting ${PATHS[0]} -> ${PATHS[1]}"
			$VPK "${GAMEDIR}/${VPKFILE}.vpk" "${PATHS[0]}" "${PATHS[1]}"
		done
	done
}

function getmaps()
{
	# Copy map source files
	echo "> Updating maps from repo"
	for EXT in bsp nav txt
	do
		$RSYNC -z --progress --ignore-existing --exclude='archive/' --exclude-from "${BLACKLIST}" "${MAPSRCURL}/*.${EXT}" "${GAMEDIR}/maps/"
	done
}

function removeblacklistmaps()
{
	echo "> Removing blacklisted map assets from data directory"
	for MAP in $(cut -d'.' -f1 "${BLACKLIST}")
	do
		for FILE in $(ls "${DATADIR}/maps/src/${MAP}_d.vmf" ${DATADIR}/maps/{parsed,navmesh,.}/${MAP}.* ${DATADIR}/resource/overviews/${MAP}.* "${GAMEDIR}/maps/${MAP}.bsp.zip" 2>/dev/null)
		do
			delete_datadir_file "${FILE}"
		done
	done
}

function decompilemaps()
{
	echo "> Updating decompiled maps as needed"
	MAPSRCDIRS_EGREP="^($(echo $(for SRCDIR in $(echo $MAPSRCDIRS | sed -e 's/\./\\\./g' -e 's/\([^\.]\)\*/\1\.\*/g'); do echo -ne "${SRCDIR} "; done) | sed -e 's/[ \t]\+/\|/g'))"
	BLACKLIST_EGREP="^($(echo $(cat "${BLACKLIST}" | sed -e 's/\./\\\./g' -e 's/\*/\.\*/') | sed -e 's/[ \t\n\r]\+/\|/g'))"
	for MAP in ${MAPSDIR}/*.bsp
	do
		# Don't do blacklisted maps
		if [ "$(basename "${MAP}")" =~ "${BLACKLIST_EGREP}" ]; then
#			echo ">> Skipping $(basename "${MAP}") for blacklist"
			continue
		fi

		# Don't do symlinks if they aren't the same name as the target
		if [ -L "${MAP}" ]; then
			# Actually, just skip all linked maps.
			continue
			if [ "$(basename $(readlink -f "${MAP}"))" != "$(basename "${MAP}")" ]; then
				continue
			fi
		fi
		if [ "$(echo "${MAP}" | sed -e 's/ //g')" != "${MAP}" ]
		then
			#echo "> SPACE"
			continue
		fi
		BASENAME=$(basename "${MAP}" .bsp)
		SRCFILE="${DATADIR}/maps/src/${BASENAME}_d.vmf"
		ZIPFILE="${MAP}.zip"
		if [ "${SRCFILE}" -ot "${MAP}" ]; then
			echo ">> Decompile ${MAP} to ${SRCFILE}"
			$BSPSRC "${MAP}" -o "${SRCFILE}"
			add_manifest_md5 "${SRCFILE}"
		fi
		# Check if the map even needs to be unzipped/extracted
		PAKLIST="${DATADIR}/maps/paklist/${BASENAME}.txt"
		if [ "${PAKLIST}" -ot "${MAP}" ]; then
			$PAKRAT -list "${MAP}" > "${PAKLIST}"
		fi
		MAPSRCLIST=$(egrep -i "${MAPSRCDIRS_EGREP}" "${PAKLIST}" | awk '{print $1}')
		if [ "${MAPSRCLIST}" == "" ]; then continue; fi
		DOUNZIP=0
		if [ "${ZIPFILE}" -ot "${MAP}" ]; then DOUNZIP=1; fi
		# If we are missing resources, unzip
		for SRCTEST in $MAPSRCLIST; do
			if [ ! -e "${DATADIR}/${SRCTEST}" ]; then DOUNZIP=1; fi
		done
		if [ "${DOUNZIP}" == "1" ]; then
			echo ">> Extract files from ${MAP} to ${ZIPFILE}"
			$PAKRAT -dump "${MAP}"
			echo ">> Extracting map files from ZIP"
			unzip -o "${ZIPFILE}" -d "${DATADIR}/" $MAPSRCLIST 2>/dev/null
		fi
	done
}

function sync_maps_to_data()
{
	echo "> Synchronizing extracted map files with data tree"
	for SRCDIR in $MAPSRCDIRS
	do
		if [ -e "${GAMEDIR}/maps/out/${SRCDIR}" ]
		then
			echo ">> Syncing ${GAMEDIR}/maps/out/${SRCDIR} to ${DATADIR}/${SRCDIR}"
			$RSYNC -c "${GAMEDIR}/maps/out/${SRCDIR}" "${DATADIR}/${SRCDIR}"
		fi
	done
}

function copy_map_files_to_data()
{
	echo "> Copying map text files"
	for TXT in ${GAMEDIR}/maps/*.txt ${GAMEDIR}/maps/out/maps/*.txt
	do
		BASENAME=$(basename "${TXT}" .txt)
		if [ $(grep -c "^${BASENAME}\..*\$" "${BLACKLIST}") -eq 0 ]
		then
			cp "${TXT}" "${DATADIR}/maps/"
			add_manifest_md5 "${DATADIR}/maps/${BASENAME}.txt"
		fi
	done
}

function convert_vtf()
{
	echo "> Create PNG files for VTF files"
	for VTF in $(find "${DATADIR}/materials/" -type f -name "*.vtf")
	do
		DIR=$(dirname "${VTF}")
		PNG="${DIR}/$(basename "${VTF}" .vtf).png"
		if [ ! -e ${PNG} ]
		then
			echo "${PNG} missing"
		fi
		if [ "$(get_manifest_md5 "${VTF}")" != "$(get_file_md5 "${VTF}")" ]
		then
			echo "> Processing ${VTF} to ${PNG}"
			if [ "${SYSTEM}" == "Linux" ]; then
				"${VTF2TGA}" "${VTF}" "${PNG}"
			else
				WINFILE=$(echo $VTF | sed -e 's/\//\\/g' -e 's/\\$//')
				WINPATH=$(echo $DIR | sed -e 's/\//\\/g' -e 's/\\$//')
				"${VTF2TGA}" -file "${WINFILE}" -output "${WINPATH}" -exportformat "png"
			fi
			add_manifest_md5 "${VTF}"
			add_manifest_md5 "${PNG}"
		fi
	done
}
# Get relative path inside data directory for a file
function get_datadir_path()
{
	if [ -f "${1}" ]
	then
		FILE="${1}"
	else
		if [ -f "${DATADIR}/${1}" ]
		then
			FILE="${DATADIR}/${1}"
		else
			return
		fi
	fi
	if [ "${SYSTEM}" == "Linux" ]; then
		echo $(readlink -f "${FILE}") | sed -e "s|^${DATADIR}/||"
	else
		echo "${FILE}" | sed -e "s|^${DATADIR}/||"
	fi
}
# Display MD5sum of a file
function get_file_md5()
{
	md5sum "${1}" | awk '{print $1}'
}
# Get existing MD5sum from manifest
function get_manifest_md5()
{
	FILE="$(get_datadir_path "${1}")"
	echo $(grep "^${FILE}:.*" "${MANIFEST_FILE}" | cut -d':' -f2)
}
# Add file to MD5 manifest
function add_manifest_md5()
{
	FILE="$(get_datadir_path "${1}")"
	OLDMD5="$(get_manifest_md5 "${1}")"
	if [ "${OLDMD5}" == "" ]
	then
		echo "> Adding ${FILE} to manifest.md5"
		cd "${DATADIR}" && md5sum "${FILE}" | sed -e 's/^\([^ \t]\+\)[ \t]\+\([^ \t]\+\)/\2:\1/' >> "${MANIFEST_FILE}"
		SORT_MANIFEST=1
	else
		NEWMD5="$(get_file_md5 "${DATADIR}/${FILE}")"
		if [ "${OLDMD5}" != "${NEWMD5}" ]
		then
			echo "> Updating ${FILE} in manifest.md5"
			sed -i -e "s|^\(${FILE}:\).*\$|\1${NEWMD5}|" "${MANIFEST_FILE}"
		fi
	fi
}
# Remove file from MD5 manifest
function remove_manifest_md5()
{
	FILE="$(get_datadir_path "${1}")"
	echo "> Removing ${FILE} from manifest.md5"
	sed -i "|${FILE}:.*|d" "${MANIFEST_FILE}"
}

# Delete file from datadir, will also update MD5 manifest
function delete_datadir_file()
{
	FILE="$(get_datadir_path "${1}")"
	if [ -f "${DATADIR}/${FILE}" ]
	then
		echo "> Deleting ${DATADIR}/${FILE}"
		remove_manifest_md5 "${FILE}"
		rm ${DATADIR}/${FILE}
	fi
}
# Rebuild entire MD5 manifest
function generate_manifest()
{
	echo "> Generating MD5 manifest"
	cd ${DATADIR}
	touch "${MANIFEST_FILE}"
	for FILE in $(find */ -type f | sort -u)
	do
		echo ">> Processing ${FILE}"
		add_manifest_md5 "${FILE}"
	done
	echo "> Generated MD5 manifest"
}

# Clean missing files from MD5 manifest
function clean_manifest()
{
	echo "> Cleaning MD5 manifest"
	for FILE in $(cut "${MANIFEST_FILE}" -d':' -f1); do
		if [ ! -e "${DATADIR}/${FILE}" ]; then
			echo ">> Removing ${FILE} from manifest"
			remove_manifest_md5 "${FILE}"
		fi
	done
	echo "> Cleaned MD5 manifest"
}
# Perform Git update
function gitupdate()
{
	echo "> Adding everything to Git and committing"
	OD=$(pwd)
	cd "${DATADIR}"
	git pull origin master
	git add "*"
	git commit -m "Updated game data files from script"
	git push origin master
	cd "${OD}"
}

# Do the execution

if [ $EXTRACTFILES == 1 ]
then
	extractfiles
fi

if [ $GETMAPS == 1 ]
then
	getmaps
fi

if [ $REMOVEBLACKLISTMAPS == 1 ]
then
	removeblacklistmaps
fi

if [ $DECOMPILEMAPS == 1 ]
then
	decompilemaps
fi

if [ $SYNC_MAPS_TO_DATA == 1 ]
then
	sync_maps_to_data
fi

if [ $COPY_MAP_FILES_TO_DATA == 1 ]
then
	copy_map_files_to_data
fi

if [ $CONVERT_VTF == 1 ]
then
	convert_vtf
fi

if [ $MAPDATA == 1 ]
then
	"${SCRIPTDIR}/../maps/mapdata.php"
fi

if [ $FULL_MD5_MANIFEST == 1 ]; then
	generate_manifest
fi
if [ $CLEAN_MANIFEST == 1 ]; then
	clean_manifest
fi
if [ $SORT_MANIFEST == 1 ]; then
	ex -s +'%!sort' -cxa "${MANIFEST_FILE}"
fi

if [ $GITUPDATE == 1 ]
then
	gitupdate
fi
