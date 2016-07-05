#!/bin/bash
CMD="/home/insserver/insurgency-tools/utilities/vtablecheck/vtablecheck /home/insserver/serverfiles/insurgency/bin/server_srv.so "
export LD_LIBRARY_PATH=~/serverfiles/bin:~/serverfiles/insurgency/bin
$CMD _ZN10CINSPlayer RemovePlayerItem
$CMD CINSPlayer ForceRespawn
$CMD _ZN10CINSPlayer Weapon_GetSlot
$CMD _ZN10CINSPlayer Ignite
$CMD _ZN10CINSPlayer Extinguish
$CMD _ZN10CINSPlayer Teleport
$CMD _ZN10CINSPlayer CommitSuicide "(bool, bool)"
$CMD _ZNK10CINSWeapon GetPrintName
$CMD _ZNK17CBaseCombatWeapon GetRumbleEffect
