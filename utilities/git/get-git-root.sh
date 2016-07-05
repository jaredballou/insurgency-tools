#!/bin/bash
# Get the root directory of the Git repo containing this script
REPO_ROOT=$(cd $(dirname $(readlink -f "${BASH_SOURCE[0]}")) && git rev-parse --show-toplevel)
echo $REPO_ROOT
