import vpk
import os
import sys
import yaml
import configparser
from pprint import pprint
from glob import glob
from fnmatch import fnmatch

class GameData(object):
	def __init__(self, config_file = None, extract_root=None):
		self.games = {}
		self.load_config_file(config_file)
		self.set_extract_root(extract_root)
		self.load_games()

	def set_extract_root(self,extract_root=None,update_games=False):
		if extract_root is None:
			extract_root = self.config['extract_root']
		self.extract_root = extract_root
		if update_games:
			for name in self.games.keys():
				self.games[name].extract_root = extract_root

	def load_games(self):
		for name,data in self.config['games'].iteritems():
			if not 'game_name' in data.keys():
				data['game_name'] = name
			if not 'vpk_files' in data.keys():
				data['vpk_files'] = self.config['vpk_files']
			if not 'extract_root' in data.keys():
				data['extract_root'] = self.extract_root
			data['parent'] = self
			self.games[name] = Game(**data)

	def load_config_file(self,config_file=None):
		if config_file is None:
			config_file = os.path.join(os.path.dirname(__file__),"config.yaml")
		if os.path.isfile(config_file):
			with open(config_file, 'r') as ymlfile:
				self.config = yaml.load(ymlfile)
				self.config_file = config_file

class Game(object):
	def __init__(self, game_name, game_root, extract_root, vpk_files, parent):
		self.parent = parent
		self.extract_root = extract_root
		self.game_name = game_name
		self.vpk_files = vpk_files
		self.game_root = game_root
		self.vpks = {}
		self.steam_inf = self.load_steam_inf()
		self.load_vpks()
		self.extract_vpk_files(force=False)
		pprint(self.config)

	def load_steam_inf(self, game_name=None, game_root=None, inf_file="steam.inf"):
		if game_name is None:
			game_name = self.game_name
		if game_root is None:
			game_root = self.game_root
		self.inf_file = os.path.join(game_root,inf_file)
		configp = configparser.RawConfigParser(comment_prefixes=('#', ';', '//'))
		inf_str = '[root]\n' + open(self.inf_file, 'r').read()
		configp.read_string(inf_str)
		config = {}
		for key in configp['root'].keys():
			config[key] = configp['root'][key]
		return config

	def load_vpks(self, game_name=None, vpk_files=None, game_root=None):
		if vpk_files is None:
			vpk_files = self.vpk_files
		if game_name is None:
			game_name = self.game_name
		if game_root is None:
			game_root = self.game_root
		for vpk_file in vpk_files:
			self.vpks[vpk_file] = VPKFile(vpk_file = os.path.join(game_root,"%s_%s_dir.vpk" % (game_name, vpk_file)), parent=self)
			#self.vpks[vpk_file].get_file_list()

	def extract_vpk_files(self, extract_root = None, game_name=None, force=False):
		if extract_root is None:
			extract_root = self.extract_root
		if game_name is None:
			game_name = self.game_name
		for vpk_file in self.vpks.keys():
			list = self.vpks[vpk_file].get_file_list()
			for file in list:
				if self.match_vpk_path(file):
					extract_path = os.path.join(extract_root,game_name,self.steam_inf['patchversion'],file)
					self.vpks[vpk_file].extract_file(file=file, extract_path=extract_path, force=force)

	def match_vpk_path(self,file):
		for path, matches in extract_paths.iteritems():
			if not file.startswith(str(path)):
				continue
			if not fnmatch(file,matches):
				continue
			return True
		return False

class VPKFile(object):
	def __init__(self,vpk_file,parent):
		self.parent = parent
		self.vpk_obj = vpk.open(vpk_file)

	def get_file_list(self):
		return [file for file in self.vpk_obj]

	def extract_file(self, file, extract_path, force=False):
		if os.path.exists(extract_path) and not force:
			return
		extract_dir = os.path.dirname(extract_path)
		if not os.path.exists(extract_dir):
			print "Creating %s" % extract_dir
			os.makedirs(extract_dir)
		file = self.vpk_obj.get_file(file)	
		file.save(extract_path)
		print "Extracting %s to %s" % (os.path.basename(extract_path),extract_dir)

if __name__ == "__main__":
	gd = GameData(extract_root = "/home/insserver/insurgency-tools/data/mods")

#game_name="doi", vpk_files=["misc","materials"], game_root="/home/doiserver/serverfiles/doi", 
