#!/bin/bash
# Get the list of submodules for this Git repo

rootdir="$(dirname $(readlink -f "${BASH_SOURCE[0]}"))"

#File fetching settings
githubrepo="${1:-$($rootdir/get-git-repo.sh)}"
githubuser="${2:-jaredballou}"
githubbranch="${3:-master}"

git_submodules=$(curl -sL "https://raw.githubusercontent.com/${githubuser}/${githubrepo}/${githubbranch}/.gitmodules" | egrep '(path|url) =' | sed -e 's/^[^=]\+[= ]*//g' | awk 'NR%2{printf $0"|";next;}1')
for submodule in $git_submodules; do
	echo "submodule is ${submodule}"
done
