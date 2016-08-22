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
from game import *

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
