#!/bin/bash -e
# Truncate Git history
# Lifted from: https://github.com/adrienthebo/git-tools/blob/master/git-truncate
# Note: seem to be finding the need to run this twice over to commit the truncate

if [[ (-z $1) || (-z $2) ]]; then
	echo "Usage: $(basename $0) <drop before SHA1 commit> <branch>"
	exit 1
fi

git filter-branch --parent-filter "sed -e 's/-p $1[0-9a-f]*//'" \
	--prune-empty \
	-- $2

git for-each-ref --format="%(refname)" refs/original | \
while read refName; do
	echo $refName
	git update-ref -d "$refName"
done

git reflog expire --expire=0 --all
git repack -ad
git prune

# success
exit 0
