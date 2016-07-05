#!/bin/bash
#
# sync-all-files.sh
# Sync all Git repos and maps from upstream sources

RSYNC_SERVER="rsync://ins.jballou.com/fastdl"
USER="insserver"
HOMEDIR="/home/${USER}"
INSDIR="${HOMEDIR}/serverfiles/insurgency"
MAPDIR="${INSDIR}/maps/"
BLACKLIST="${INSDIR}/insurgency-data/thirdparty/maps-blacklist.txt"

RSYNC_CMD="rsync -av"
RSYNC_LOCAL_ROOT="${HOMEDIR}/rsync"
# Rsync sources in format REMOTE_PATH|LOCAL_TARGET|OPTIONS...
RSYNC="/maps/||--exclude-from \"${BLACKLIST}\" --include '*.bsp' --include '*.nav' --include '*.txt'
/mapcycle_files/|/|"

# Git repos in format PATH|URL
REPOS="addons|https://github.com/jaredballou/insurgency-addons.git
addons/sourcemod|https://github.com/jaredballou/insurgency-sourcemod.git
insurgency-data|https://github.com/jaredballou/insurgency-data.git
scripts/theaters|https://github.com/jaredballou/insurgency-theaters.git"

function sync_git() {
	echo "Sync Git repos"
	printf '%s\n' "$REPOS" | while IFS= read -r REPO; do
		REPO_PATH="${INSDIR}/$(echo "${REPO}" | cut -d'|' -f1)"
		REPO_URL="$(echo "${REPO}" | cut -d'|' -f2-)"
		if [ ! -e "${REPO_PATH}/.git" ]
		then
			mkdir -p "${REPO_PATH}" 2>/dev/null
			(cd "${REPO_PATH}"; git init; git add remote origin "${REPO_URL}")
		fi
		(cd "${REPO_PATH}"; git pull origin master; git submodule init; git submodule update)
	done
}
function sync_rsync() {
	echo "Sync Rsync Sources"
	printf '%s\n' "$RSYNC" | while IFS= read -r REPO; do
		REMOTE_PATH="$(echo "${REPO}" | cut -d'|' -f1)"
		LOCAL_PATH="$(echo "${REPO}" | cut -d'|' -f2)"
		LOCAL_PATH="${LOCAL_PATH:-"${REMOTE_PATH}"}"

		RSYNC_OPTIONS="$(echo "${REPO}" | cut -d'|' -f3-)"

		REMOTE_URL="${RSYNC_SERVER}${REMOTE_PATH}"
		LOCAL_RSYNC_PATH="${RSYNC_LOCAL_ROOT}${REMOTE_PATH}"
		LOCAL_TARGET="${INSDIR}${LOCAL_PATH}"
		if [ ! -e "${LOCAL_RSYNC_PATH}" ]; then
			mkdir -p "${LOCAL_RSYNC_PATH}" 2>/dev/null
		fi
		$RSYNC_CMD $RSYNC_OPTIONS "${REMOTE_URL}" "${LOCAL_RSYNC_PATH}"

		# If the directories are different, update symlinks
		BROKEN_SYMLINKS=$(find "${LOCAL_TARGET}" -type l -maxdepth 0 -! -exec test -e {} \; -printf '%P\n')
		LOCAL_FILES=$(find "${LOCAL_RSYNC_PATH}" -type f -printf '%P\n')
		printf '%s\n' "${BROKEN_SYMLINKS}" | while IFS= read -r BROKEN; do
			rm -vf "${BROKEN}"
		done
		printf '%s\n' "${LOCAL_FILES}" | while IFS= read -r FILE; do
			TARGET="${LOCAL_RSYNC_PATH}${FILE}"
			LINK="${LOCAL_TARGET}${FILE}"
			FILEDIR="$(dirname "${LINK}")"
			if [ ! -e "${FILEDIR}" ]; then
				mkdir -p "${FILEDIR}" 2>/dev/null
			fi
			ln -sf "${TARGET}" "${LINK}"
		done
	done
}

sync_git
sync_rsync
