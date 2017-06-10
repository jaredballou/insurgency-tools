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

from map import *
from material import *
from vpkfile import *

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
		self.materials = {}
		self.new_version = False
		self.vpk_files = vpk_files
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

		self.extract_root = extract_root
		self.load_steam_inf()
		self.set_version()
		self.load_metadata()

		if self.parent.config['modules']['vpks']['enabled']:
			self.load_vpks()
		if self.parent.config['modules']['maps']['enabled']:
			self.load_maps()
		if self.parent.config['modules']['materials']['enabled']:
			self.find_materials()
		if self.parent.config['modules']['cvarlist']['enabled']:
			self.load_cvarlist()
		if self.parent.config['modules']['sourcemod']['enabled']:
			self.load_sourcemod()

	def load_cvarlist(self, game_root=None):
		if game_root is None:
			game_root = self.game_root
		dstpath = os.path.join(self.version_root,self.parent.config['modules']['cvarlist']['files_path'])
		if not os.path.exists(dstpath):
			os.makedirs(dstpath)
		for file in self.parent.config['modules']['cvarlist']['files']:
			srcfile = os.path.join(game_root,file)
			if os.path.exists(srcfile):
				copyfile(srcfile, os.path.join(dstpath, file))

	def load_sourcemod(self, game_root=None):
		if game_root is None:
			game_root = self.game_root
		dstpath = os.path.join(self.version_root,self.parent.config['modules']['sourcemod']['files_path'])
		if not os.path.exists(dstpath):
			os.makedirs(dstpath)
		for file in self.parent.config['modules']['sourcemod']['files']:
			srcfile = os.path.join(game_root,file)
			if os.path.exists(srcfile):
				copyfile(srcfile, os.path.join(dstpath, file))

	def set_version(self, version = None):
		if version is None:
			version = self.steam_inf['patchversion']
		self.version = version
		self.version_root = os.path.join(self.extract_root,version)
		if not os.path.exists(self.version_root):
			self.new_version = True
			os.makedirs(self.version_root)

	def load_metadata(self, metadata_file = None):
		if metadata_file is None:
			metadata_file = os.path.join(self.extract_root, "metadata.yaml")

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
		for test_path in [self.version_root, self.game_root]:
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
		for root, dirs, files in os.walk(os.path.join(self.version_root, "materials")):
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
		self.steam_inf = {}
		for key in configp['root'].keys():
			self.steam_inf[key] = configp['root'][key]

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
		self.extract_vpk_files(force=False)


	def extract_vpk_files(self, extract_root = None, force=False, vpk_files=None):
		"""Extract game files from VPKs
			Args:
		"""
		if vpk_files is None:
			vpk_files = self.vpk_files
		if extract_root is None:
			extract_root = self.version_root
		for vpk_file in vpk_files:
			self.vpks[vpk_file].extract_files(extract_root=extract_root, force=force)
