#!/bin/bash
# This tool generates bzip and md5 files for all maps
# It will also create symlinks named "$map $mode.bsp" for all maps based upon
# game modes to help with servers finding maps and modes in menus.

MAPSDIR=/opt/fastdl/maps
STARTDIR=$(pwd)

cd ${MAPSDIR}

for file in *.bsp *.nav
do
	# Don't process symlinks
	if [ -L "$file" ]; then continue; fi
	# Create bz2 file if needed
	if [ "$file.bz2" -ot "$file" ]; then
		echo "Creating bzip for $file"
		bzip2 -k -f "$file"
	fi
	# Create MD5 if needed
	if [ ! -e "$file.md5" ] || [ "$file.md5" -ot "$file" ]; then
		echo "Creating MD5 for $file"
		md5sum "${file}" > "${file}.md5"
	fi
	basename=$(basename "$file" | sed -e 's/\.\(bsp\|nav\)$//g')
	if [ "${2}" == "ln" ]; then
		for mode in $(grep -P '"(ambush|battle|checkpoint|firefight|flashpoint|hunt|infiltrate|occupy|push|skirmish|strike)"' $basename.txt 2>/dev/null | cut -d'"' -f2); do
			link="${basename} ${mode}.bsp"
			if [ ! -e "${link}" ]; then
				echo Creating symlink for "${link}"
				ln -sf "${file}" "${link}"
			fi
		done
	fi
done

# Clean up old BZIP and MD5 files. This would delete symlinks as well.
for file in *.md5 *.bz2; do
	basename=$(basename $file | sed -e 's/\.\(md5\|bz2\)$//g')
	if [ ! -e "${basename}" ]; then
		rm -rvf "${file}"
	fi
done

cd "${STARTDIR}"
