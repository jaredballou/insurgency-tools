#!/bin/bash
# betabranch.sh
# (c) 2016, Jared Ballou <insurgency@jballou.com>
#
# Install and maintain a Beta Branch installation on OSX or Linux.
# TODO:

export defaults_loaded=0
declare -a varlist
config=""

# The name of this script file, used to show the LGSM link properly
selfname=$(basename $(readlink -f "${BASH_SOURCE[0]}"))

# Script root
rootdir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# If a user wants a different defaults file, export it before running this tool
defaults_file="${defaults_file:-"${rootdir}/betabranch.conf"}"

# Main logic loop, not invoked until the end of the script
main() {
	load_script_config
	update_script_config
	install_steamcmd
	create_steamcmd_script
	run_steamcmd_script
}

# Add a variable to the configuration file
add_var()
{
	varname=$1
	desc=$2
	default=$3
	varlist=("${varlist[@]}" $varname)
	varprompt="${desc} [${default}]"
	# Set default if var is not yet set
	if [ -z ${!varname} ]
	then
		val=$default
		if [ "$defaults_loaded" != "1" ]
		then
			read -p "${varprompt}: " val
			if [ "$val" == "" ]
			then
				val=$default
			fi
		fi
		eval ${varname}=$val
	fi
	echo -ne "# ${varprompt}\nexport $varname=\"${!varname}\"\n" >> "${defaults_file}"
	source "${defaults_file}"
}

# Load existing config if available. Then blank it so we can rewrite with
# new settings.
load_script_config() {
	# Always replace "os" setting with current system
	if [ "$(uname -s)" == "Darwin" ]
	then
		export os=osx
	else
		export os=linux
	fi

	# Create script config directory if needed
	defaults_dir=$(dirname "${defaults_file}")
	if [ ! -e "${defaults_dir}" ]; then
		mkdir -p "${defaults_dir}"
	fi

	# Load file if available. Blank it regardless once we have loaded our data.
	if [ -e $defaults_file ]; then
		if [ "${defaults_loaded}" != "1" ]; then
			echo "Loading previous config file: ${defaults_file}"
			export defaults_loaded=1
		else
			echo "Loading config file: ${defaults_file}"
		fi
		source $defaults_file
	else
		export new_config=1
	fi
	echo > $defaults_file
}

# Create a config file (or load an existing one) of settings for the tool to use
update_script_config() {
	# Add variables to structure
	add_var "os" "Operating system we are running on. Valid values are 'osx' or 'linux'" "${os}"
	add_var "appid" "Steam Application ID to install (Insurgency)" "222880"
	add_var "beta_branch" "Beta branch to install" "beta"
	add_var "steamcmd_path" "Directory to use for installing SteamCMD" "~/steamcmd"
	add_var "steamcmd_url" "URL for SteamCMD Installer Package" "https://steamcdn-a.akamaihd.net/client/installer/steamcmd_\${os}.tar.gz"
	add_var "steamcmd_file" "SteamCMD Installer Download Target" "\${steamcmd_path}/steamcmd.zip"
	add_var "steamcmd_log" "SteamCMD Log File" "\${steamcmd_path}/steamcmd.log"
	add_var "steam_user" "Steam Username" "anonymous"
	add_var "steam_pass" "Steam Password" ""
	add_var "steam_guard_code" "Steam Guard Auth Code - you will need to retrieve this from your email" ""
	add_var "game_path" "Path for game installation" "\${steamcmd_path}/steamapps/common/insurgency-beta"
	add_var "steamcmd_script_file" "SteamCMD Game Update Script File" "\${steamcmd_path}/betabranch.txt"
}

# Download and install SteamCMD
install_steamcmd() {
	# Create SteamCMD directory
	if [ ! -e $steamcmd_path ]
	then
		mkdir -p $steamcmd_path
	fi

	# Download and Extract SteamCMD
	if [ ! -e ${steamcmd_file} ]
	then
		curl -s -o ${steamcmd_file} ${steamcmd_url}
		tar -xzvpf ${steamcmd_file} -C ${steamcmd_path}
	fi
}

# Create SteamCMD script
create_steamcmd_script() {
	# Create SteamCMD updater script
	if [ "${steam_guard_code}" != "" ]; then
		steamguard="set_steam_guard_code ${steam_guard_code}"
	fi
	echo -n "@ShutdownOnFailedCommand 1
		@NoPromptForPassword 1
		${steamguard}
		login ${steam_user} ${steam_pass}
		force_install_dir ${game_path}
		app_update ${appid} -beta ${beta_branch} validate
		quit
	" | sed -e 's/^[\t ]\+//g' > "${steamcmd_script_file}"
}

# Run SteamCMD Script
run_steamcmd_script() {
	(cd ${steamcmd_path} && ./steamcmd.sh +runscript ${steamcmd_script_file})
}

# Run main logic loop
main

