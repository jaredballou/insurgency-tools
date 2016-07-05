#!/bin/bash

# Path to DepotDownloader
DD_PATH=/home/insserver/serverfiles

# Path for DepotDownloader to download to, relative to DD_PATH
DD_DIR=insurgency

# App ID
DD_APP=237410
# Beta name
DD_BETA=public

# Beta passowrd, if applicable
#DD_BETA_PASSWORD=passwordhere

# Relative path to game's engine directory from DD
ENGINE_PATH_FROM_DD=${DD_DIR}
# Game's directory name
GAME_DIR=insurgency

BIN_EXT="_srv"

# SM gamedata engine name
ENGINE_NAME=insurgency

# List of gamedata files to run checks on
gamedata_files=(
	"insurgency.games.txt"
#	"core.games/engine.insurgency.txt"
#	"sdkhooks.games/game.insurgency.txt"
#	"sdkhooks.games/engine.insurgency.txt"
#	"sdktools.games/game.insurgency.txt"
#	"sdktools.games/engine.insurgency.txt"
)

# Is game a 2006/2007 "mod" ?
# If so, bin names are adjusted with _i486 suffix and no update check will be done
MOD=0

# DO NOT EDIT BELOW THIS LINE

source ./gdc_core.sh $1 $2 $3 $4

exit $?
