# -*- coding: utf-8 -*-
"""Gamedata Configuration
"""
import argparse
import collections
import gamedata
import itertools
import json
import os
import yaml

from pprint import pprint

class ConfigAttribute(object):
	"""A single attribute of a Config object
		Attributes:
			default (any):
			help (str):
			metavar (str):
			name (str):
			type (type):
	"""
	def __init__(self, name, parent, default=None, type=str, metavar=None, help=None, value=None, maxitems=0):
		self.parent = parent
		if help is None:
			help = name
		if metavar is None:
			metavar = default
			if maxitems:
				if isinstance(default, dict):
					metavar = dict(itertools.islice(default.iteritems(), maxitems))
				elif isinstance(default, list):
					if len(default) < maxitems:
						maxitems = len(default)
					metavar = default[0:maxitems]
		if value is None:
			value = default
		self.name = name
		self.metavar = metavar
		self.default = default
		self.value = value
		self.type = type
		self.help = help
		self.add_parent_arg()

	@property
	def value(self):
		return self._value  # see below

	@value.setter
	def value(self, value):
		self._value = value
		self.parent.set_value(self.name, value)

	def add_parent_arg(self):
		self.parent.argparser.add_argument("--{}".format(self.name), default=self.default, type=self.type, help=self.help, metavar=self.metavar)

	def __repr__(self):
		return repr(self.value)

	#def __iter__(self):
	#	return self.value

class Config(object):
	"""GameData script configuration"""
	# Make attributes and config a class variable, since there should only ever be one Config object
	attributes = {}
	config = {}

	def __init__(self, parent, config_file="config.yaml"):
		self.parent = parent
		self.argparser = argparse.ArgumentParser(description="Extract files from installed games, and process files into format which is ussable by the web tools")
		self.load_attributes()
		self.load_config_file(config_file)

	def set_value(self, name, value):
		self.config[name] = value

	def load_config_file(self, config_file):
		config_file = self.find_config_file(config_file=config_file)
		if os.path.isfile(config_file):
			with open(config_file, "r") as ymlfile:
				self.config = yaml.load(ymlfile)
				self.config_file = config_file
				self.update_attributes()
		else:
			print("ERROR: Cannot find '%s'!" % config_file)

	def update_attributes(self):
		pass
		#for name,val in self.config.iteritems():
		#	self.attributes[name].value = value
		

	def add_attribute(self, name, metavar=None, default=None, type=str, help=None):
		self.attributes[name] = ConfigAttribute(parent=self, name=name, default=default, type=type, help=help, metavar=metavar)

	def load_attributes(self):
		"""Load list of attributes"""
		self.attributes = {}
		self.add_attribute(name="config_file", default="config.yaml", type=str, help="Config file to load")
		self.add_attribute(name="do_maps", default=False, type=bool, help="Process maps")
		self.add_attribute(name="do_materials", default=True, type=bool, help="Process materials")
		self.add_attribute(name="do_vpks", default=True, type=bool, help="Process VPKs")
		self.add_attribute(name="extract_paths", default={"maps/": "*.txt", "materials/overviews/": "*", "materials/vgui/gameui/": "*", "materials/vgui/hud/": "*", "materials/vgui/hud_doi/": "*", "materials/vgui/inventory/": "*", "materials/vgui/logos/": "*", "materials/vgui/maps/": "*", "resource/": "*.txt *.res", "scripts/playlists/": "*.playlist", "scripts/theaters/": "*.theater"}, type=dict, help="Paths to extract from game files")
		self.add_attribute(name="extract_root", default="../../data/mods", type=str, help="Root of gamedata directory into which we will extract files")
		self.add_attribute(name="game_roots", default=["/home/doiserver/serverfiles", "/home/insserver/serverfiles", "~/serverfiles", "~/Library/Application Support/Steam/steamapps/common"], type=list, help="Paths to search for installed games")
		self.add_attribute(name="games", default={"dayofinfamy": {"game_dir": "doi"}, "insurgency": {"game_name": "insurgency2", "game_dir": "insurgency"}}, type=dict, help="Games to process")
		self.add_attribute(name="map_files", default={"bsp":{"path": "maps", "match": "%(name)s.bsp"}, "cpsetup_txt":{"path": "maps", "match": "%(name)s.txt"}, "json":{"type": "output", "path": "maps/parsed", "match": "%(name)s.json"}, "nav":{"path": "maps", "match": "%(name)s.nav"}, "overlay":{"path": "maps/overlays", "match": "%(name)s.json"}, "overview_png":{"path": "materials/overviews", "match": "%(name)s.png"}, "overview_txt":{"path": "resource/overviews", "match": "%(name)s.txt"}, "overview_vmt":{"path": "materials/overviews", "match": "%(name)s.vmt"}, "overview_vtf":{"path": "materials/overviews", "match": "%(name)s.vtf"}, "vmf":{"path": "maps/src", "match": "%(name)s_d.vmf"}}, type=dict, help="Files used to process maps")
		self.add_attribute(name="map_entities", default={"ins_blockzone": {}, "ins_spawnzone": {}, "obj_weapon_cache": {}, "point_controlpoint": {}, "trigger_capture_zone": {}}, type=dict, help="Entities to process from maps")
		self.add_attribute(name="map_entities_props", default={"angles":{"type": "vertex"}, "blockzone":{"type": "entity"}, "classname": {}, "model": {}, "origin":{"type": "vertex"}, "printname":{"type": "translate"}, "skin": {}, "spawnflags": {}, "SquadID": {}, "StartDisabled": {}, "targetname":{"type": "name"}, "TeamNum": {}}, type=dict, help="Properties of map entities that we want to process")
		self.add_attribute(name="modules", default={}, type=dict, help="Modules")
		self.add_attribute(name="tools", default={"bspsrc": "../../thirdparty/bspsrc/bspsrc.jar", "pakrat": "../../thirdparty/pakrat/pakrat.jar", "vtf2tga": "../../utilities/vtf2tga/vtf2tga"}, type=dict, help="External tools used to process data")
		self.add_attribute(name="vpk_files", default=["misc", "materials"], type=list, help="List of VPK files to use for pulling files")
		self.args = vars(self.argparser.parse_args())

	def find_config_file(self, config_file=None):
		"""Find the config file in each subdir until found or failed."""
		if config_file is None:
			config_file = self.args["config_file"]
		if os.path.exists(config_file):
			return config_file
		check_dir = os.path.dirname(__file__)
		while len(check_dir) > 1:
			check_file = os.path.join(check_dir,config_file)
			if os.path.exists(check_file):
				return check_file
			check_dir = os.path.dirname(check_dir)
		return False

