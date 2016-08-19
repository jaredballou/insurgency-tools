# -*- coding: utf-8 -*-
"""Game data extraction library

This library has tools to extract game data files from an installed game to the
format needed for inclusion in insurgency-data mods format. It supports version
separation, extracting files from VPK or filesystem, and is managed via the
config.yaml file.
"""
import configparser
from fnmatch import fnmatch
from glob import glob
import os
from shutil import copyfile
import subprocess
import sys
import vpk
import vdf
import yaml

class GameData(object):
	"""Main GameData object to wrap all other types. This will eventually be a singleton, for now only create one object.
		Attributes:
			config_file (str): File to load settings from.
			extract_root (str): Default root path for game files to extract to, should be the local path to insurgency-data/mods. Default is to get it from config.yaml loading. Can be set at each game definition, otherwise the path will be %(extract_root)/%(game_dir)/%(version)
			config (dict): Config file loaded into dict
			games (dict): List of Game objects, keyed by name.
	"""
	def __init__(self, config_file = None, extract_root=None):
		"""Initialize the GameData object"""
		if config_file is None:
			config_file = self.find_config_file()
		self.games = {}
		self.load_config_file(config_file)
		if extract_root is None:
			extract_root = self.config['extract_root']
		self.set_extract_root(extract_root)
		self.load_games()

	def find_config_file(self, config_file="config.yaml"):
		"""Find the config file in each subdir until found or failed."""
		
		check_dir = os.path.dirname(__file__)
		while len(check_dir) > 1:
			check_file = os.path.join(check_dir,config_file)
			if os.path.exists(check_file):
				return check_file
			check_dir = os.path.dirname(check_dir)
		return False

	def set_extract_root(self,extract_root,update_games=False):
		"""Set the default root path for extraction.
			Args:
				extract_root (str): Default path for extraction
				update_games (bool): Change the path of all child Game objects as well
		"""
		self.extract_root = extract_root
		if update_games:
			for name in self.games.keys():
				self.games[name].extract_root = extract_root

	def load_config_file(self,config_file):
		"""Load the config.yaml file
			Args:
				config_file (str): Full path to YAML file
		"""
		if os.path.isfile(config_file):
			with open(config_file, 'r') as ymlfile:
				self.config = yaml.load(ymlfile)
				self.config_file = config_file
		else:
			print("ERROR: Cannot find '%s'!" % config_file)

	def load_games(self):
		"""Load all games from the config as Game objects and process
		them. This also handles a lot of default value management for
		Game objects, and likely needs a better implementation.
		"""
		for name,data in self.config['games'].iteritems():
			if not 'game_dir' in data.keys():
				data['game_dir'] = name
			if not 'game_name' in data.keys():
				data['game_name'] = name
			if not 'vpk_files' in data.keys():
				data['vpk_files'] = self.config['vpk_files']
			if not 'extract_root' in data.keys():
				data['extract_root'] = os.path.join(self.extract_root,data['game_dir'])
			data['parent'] = self
			self.games[name] = Game(**data)

class Game(object):
	"""Individual Game object. Handles game directories, VPK lists, map processing, and more via child objects.
		Attributes:
			extract_paths (dict): Paths to extract, and glob to match. To match multiple globs, use a list of globs for a single path.
			extract_root (str): Root where game files will be extracted.
			game_dir (str): Game directory, what would be sent as "+game" to srcds to load this game
			game_name (str): Name of game, should match game install dir under Steam/steamapps/common - will be located via hierarchial checks if not specified.
			game_root (str): Root directory of game, i.e. "insurgency2" for Insurgency
			parent (GameData): Parent object
			vpk_files (list): VPK files to check, "misc" will evaluate to "%(game_dir)_misc_dir.vpk"
			vpks (dict): VPKFile objects that this Game loads
	"""
	def __init__(self, game_name, extract_root, vpk_files, parent, game_dir=None, game_root=None, extract_paths=None):
		"""
			Args:
		"""
		self.parent = parent
		self.game_name = game_name
		self.vpks = {}
		self.maps = {}
		self.mterials = {}
		if extract_paths is None:
			extract_paths = self.parent.config['extract_paths']
		self.extract_paths = extract_paths
		if game_dir is None:
			game_dir = game_name
		self.game_dir = game_dir
		if game_root is None:
			if not self.find_game_root():
				print "ERROR: Cannot find game root for %s" % game_name
				return

		self.steam_inf = self.load_steam_inf()
		self.load_metadata(os.path.join(extract_root,"metadata.yaml"))
		self.extract_root = os.path.join(extract_root,self.steam_inf['patchversion'])

		if self.parent.config['do_vpks']:
			self.vpk_files = vpk_files
			self.load_vpks()
			self.extract_vpk_files(force=False)
		if self.parent.config['do_maps']:
			#self.find_maps()
			self.load_maps()
		if self.parent.config['do_materials']:
			self.find_materials()

	def load_metadata(self, metadata_file):
		if os.path.isfile(metadata_file):
			with open(metadata_file, 'r') as ymlfile:
				self.metadata = yaml.load(ymlfile)
				self.metadata_file = metadata_file
		else:
			print("ERROR: Cannot find '%s'!" % metadata_file)

	def find_file(self, file, recurse=False, default=""):
		"""Find a file in game directory or data directory.
			Returns: Full path to file if found, default if not.
		"""
		# TODO: Support recursion to find file in deeper paths
		for test_path in [self.extract_root, self.game_root]:
			test_file = os.path.join(test_path, file)
			if os.path.exists(test_file):
				return test_file
		return default

	def load_maps(self):
		"""Load maps listed in metadata file"""
		if not "maps" in self.metadata or not self.metadata["maps"]:
			return
		for map in self.metadata['maps']:
			self.maps[map] = Map(name=map, parent=self)

	def find_maps(self):
		"""Locate all Map related files"""
		map_path = os.path.join(self.game_root,"maps")
		if not os.path.exists(map_path):
			return
		self.add_map_dir(map_path)

	def add_map_dir(self,map_path,force=False):
		"""Add maps in a directory
			Todo:
				* Find all files via filesystem and VPK, and process all of them once list is complete.
			Args:
		"""
		for root, dirs, files in os.walk(map_path):
			map_names = sorted(list(set([os.path.splitext(file)[0].strip(".") for file in files if os.path.splitext(file)[1].strip(".") in ["bsp", "nav", "txt"]])))
			for map in map_names:
				self.maps[map] = Map(name=map, parent=self)

	def find_materials(self, export_format="png"):
		"""Locate all Material related files"""
		for root, dirs, files in os.walk(os.path.join(self.extract_root, "materials")):
			for file in files:
				ext = os.path.splitext(file)[1].strip(".")
				if not ext in ["vmt", "vtf", export_format]:
					continue
				if ext == "vmt":
					# TODO: Parse VMT as KeyValues
					pass
				elif ext == "vtf":
					# TODO: Export VTF to PNG
					pass
				elif ext == export_format:
					# TODO: Verify exported image matches source file
					pass

	def find_game_root(self):
		"""Locate the root install directory of this game"""
		for root in self.parent.config['game_roots']:
			for testpath in [os.path.join(self.game_name,self.game_dir),self.game_dir,self.game_name]:
				game_root = os.path.expanduser(os.path.join(root,testpath))
				if os.path.exists(game_root):
					self.game_root = game_root
					return game_root
		return False

	def load_steam_inf(self, game_root=None, inf_file="steam.inf"):
		"""Load steam.inf into dict
			Args:
		"""
		if game_root is None:
			game_root = self.game_root
		self.inf_file = os.path.join(game_root,inf_file)
		configp = configparser.RawConfigParser(comment_prefixes=('#', ';', '//'))
		inf_str = u'[root]\n' + open(self.inf_file, 'r').read()
		configp.read_string(inf_str)
		config = {}
		for key in configp['root'].keys():
			config[key] = configp['root'][key]
		return config

	def load_vpks(self, game_dir=None, vpk_files=None, game_root=None, force=False):
		"""Load VPK files
			Args:
		"""
		if vpk_files is None:
			vpk_files = self.vpk_files
		if game_dir is None:
			game_dir = self.game_dir
		if game_root is None:
			game_root = self.game_root
		for vpk_file in vpk_files:
			if force or vpk_file not in self.vpks:
				self.vpks[vpk_file] = VPKFile(vpk_file = os.path.join(game_root,"%s_%s_dir.vpk" % (game_dir, vpk_file)), parent=self)

	def extract_vpk_files(self, extract_root = None, force=False, vpk_files=None):
		"""Extract game files from VPKs
			Args:
		"""
		if vpk_files is None:
			vpk_files = self.vpk_files
		if extract_root is None:
			extract_root = self.extract_root
		for vpk_file in vpk_files:
			self.vpks[vpk_file].extract_files(extract_root=extract_root, force=force)

class Map(object):
	"""Object that handles BSP, NAV, and TXT (CPSetup) files in /maps/ directory. Also manages /resource/overviews/%(map).txt overview settings.
		Attributes:
			parent (Game): 
			 (): 
			 (): 
			 (): 
			 (): 
	"""
	def __init__(self, parent, name, bsp=None, nav=None, cpsetup_txt=None, overview_txt=None, overview_vtf=None, parsed_file=None, vmf_file=None, do_parse=True, decompile=True, unpack_files=True):
		"""
			Args:
		"""
		self.parent = parent
		self.name = name
		self.files = {}
		self.map_files = {}
		self.do_parse = do_parse
		self.decompile = decompile
		self.unpack_files = unpack_files
		self.find_map_files()
		if do_parse:
			self.parse_map_files()

	def find_map_files(self):
		for file_type,file_path in self.parent.parent.config['map_files'].iteritems():
			self.map_files[file_type] = os.path.join(self.parent.extract_root,file_path % vars(self))
			map_file = self.parent.find_file(file=file_path % vars(self))
			if map_file:
				self.files[file_type] = map_file

	def parse_map_files(self):
		for type,file in self.files.iteritems():
			if type == "bsp" and self.decompile:
				self.parse_bsp()
			if type == "nav":
				self.parse_nav()
			if type == "cpsetup_txt":
				self.parse_cpsetup_txt()
			if type == "overview_txt":
				self.parse_overview_txt()

	def parse_bsp(self, force=False):
		"""Decompile BSP file to VMF"""
		if os.path.exists(self.map_files["vmf"]) and not force:
			return
		try:
			print("Decompiling %s..." % self.name)
			vmf_dir = os.path.dirname(self.map_files["vmf"])
			if not os.path.exists(vmf_dir):
				os.makedirs(vmf_dir)
			bspsrc_args = ['-no_areaportals', '-no_cubemaps', '-no_details', '-no_occluders', '-no_overlays', '-no_rotfix', '-no_sprp', '-no_brushes', '-no_cams', '-no_lumpfiles', '-no_prot', '-no_visgroups']
			bspsrc_cmd = ['java', '-cp', self.parent.parent.config['tools']['bspsrc'], 'info.ata4.bspsrc.cli.BspSourceCli'] + bspsrc_args + [self.files["bsp"], '-o', self.map_files["vmf"]]
			process = subprocess.Popen(bspsrc_cmd, stdout=subprocess.PIPE)
			out, err = process.communicate()
			print("Done")
		except:
			print("Failed")
		pass

	def parse_nav(self):
		"""Parse NAV file and export JSON object with relevant data"""
		pass

	def parse_cpsetup_txt(self):
		"""Process cpsetup.txt file"""
		try:
			data = vdf.load(open(self.files["cpsetup_txt"]))
			#print(data)
		except:
			print(self.files["cpsetup_txt"])

	def parse_overview_txt(self):
		"""Process Overview text file"""
		try:
			data = vdf.load(open(self.files["overview_txt"]))
			print(data)
		except:
			print(self.files["overview_txt"])

	def export_parsed(self):
		"""Export all parsed data to single JSON file for all map objects."""
		pass

"""
		BASENAME=$(basename "${MAP}" .bsp)
		SRCFILE="${DATADIR}/maps/src/${BASENAME}_d.vmf"
		ZIPFILE="${MAP}.zip"
		if [ "${SRCFILE}" -ot "${MAP}" ]; then
			echo ">> Decompile ${MAP} to ${SRCFILE}"
			add_manifest_md5 "${SRCFILE}"
		fi
		# Check if the map even needs to be unzipped/extracted
BSPSRC="java -cp ${REPODIR}/thirdparty/bspsrc/bspsrc.jar
# Pakrat command
PAKRAT="java -jar ${REPODIR}/thirdparty/pakrat/pakrat.jar"
			$BSPSRC "${MAP}" -o "${SRCFILE}"

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
"""

class Material(object):
	"""This class manages VMT/VTF materials. It creates PNG files
	for the Web UI."""

	def __init__(self, parent):
		""" """
		self.parent = parent

	def export_png(self, material, png):
		""" """
		#subprocess.call(['java', '-jar', self.parent.config['tools']['vtf2tga'])
		pass

	def parse_vmt(self):
		"""Process a VMT file as KeyValues"""
		pass

	def parse_vtf(self):
		"""Process a VTF file into an image object"""
		pass


class VPKFile(object):
	"""VPK File. Supports loading, getting list of files, and extracting files.
		Attributes:
			parent (Game): 
			vpk_file (str): 
			vpk_obj (VPK): 
			 (): 
			 (): 
			 (): 
			 (): 
	"""
	def __init__(self, vpk_file, parent):
		"""
			Args:
		"""
		self.parent = parent
		self.vpk_file = vpk_file
		self.vpk_obj = vpk.open(vpk_file)

	def get_file_list(self):
		"""Get a list of all files inside the VPK."""
		return [file for file in self.vpk_obj]

	def extract_files(self, extract_root=None, force=False, extract_paths=None):
		"""Extract all files that match extract_paths patterns
			Args:
		"""
		if extract_paths is None:
			extract_paths = self.parent.extract_paths
		if extract_root is None:
			extract_root = self.parent.extract_root
		for file in self.get_file_list():
			if self.match_vpk_path(file=file, extract_paths=extract_paths):
				extract_path = os.path.join(extract_root,file)
				self.extract_file(file=file, extract_path=extract_path, force=force)

	def match_vpk_path(self,file,extract_paths=None):
		"""
			Args:
		"""
		if extract_paths is None:
			extract_paths = self.parent.extract_paths
		for path, matches in extract_paths.iteritems():
			if not file.startswith(str(path)):
				continue
			for match in matches.split(" "):
				if fnmatch(file,match):
					return True
		return False

	def extract_file(self, file, extract_path, force=False):
		"""
			Args:
		"""
		if os.path.exists(extract_path) and not force:
			return
		extract_dir = os.path.dirname(extract_path)
		if not os.path.exists(extract_dir):
			print "Creating %s" % extract_dir
			os.makedirs(extract_dir)
		# TODO: Compare file contents and force together
		file = self.vpk_obj.get_file(file)
		file.save(extract_path)
		print "Extracting %s to %s" % (os.path.basename(extract_path),extract_dir)
