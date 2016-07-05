#!/bin/bash
# Get the current Git repository of the script
REPO_NAME=$(cd $(dirname $(readlink -f "${BASH_SOURCE[0]}")) && git remote -v | head -n1 | awk '{print $2}' | sed -e 's,.*:\(.*/\)\?,,' -e 's/\.git$//')
echo $REPO_NAME
