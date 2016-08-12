import vpk
import os
import sys
import yaml
import configparser
from pprint import pprint
from glob import glob
from fnmatch import fnmatch
from shutil import copyfile

class GameData(object):
	def __init__(self, config_file = None, extract_root=None):
		self.games = {}
		self.load_config_file(config_file)
		pprint(self.config)
		self.set_extract_root(extract_root)
		self.load_games()

	def set_extract_root(self,extract_root=None,update_games=False):
		if extract_root is None:
			extract_root = self.config['extract_root']
		self.extract_root = extract_root
		if update_games:
			for name in self.games.keys():
				self.games[name].extract_root = extract_root

	def load_config_file(self,config_file=None):
		if config_file is None:
			config_file = os.path.join(os.path.dirname(__file__),"config.yaml")
		if os.path.isfile(config_file):
			with open(config_file, 'r') as ymlfile:
				self.config = yaml.load(ymlfile)
				self.config_file = config_file
				self.config['bspsrc_path'] = os.path.realpath(os.path.join(os.path.dirname(__file__),self.config['bspsrc_path']))
				self.config['vtf2tga_path'] = os.path.realpath(os.path.join(os.path.dirname(__file__),self.config['vtf2tga_path']))

	def load_games(self):
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
	def __init__(self, game_name, extract_root, vpk_files, parent, game_dir=None, game_root=None, extract_paths=None):
		self.parent = parent
		self.game_name = game_name
		self.vpks = {}
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

		self.extract_root = os.path.join(extract_root,self.steam_inf['patchversion'])

		if self.parent.config['do_vpks']:
			self.vpk_files = vpk_files
			self.load_vpks()
			self.extract_vpk_files(force=False)
		if self.parent.config['do_maps']:
			self.find_maps()

	def find_maps(self):
		map_path = os.path.join(self.game_root,"maps")
		if not os.path.exists(map_path):
			return
		self.add_map_dir(map_path)

	def add_map_dir(self,map_path,force=False):
		for root, dirs, files in os.walk(map_path):
			for file in files:
				ext = os.path.splitext(file)[1].strip(".")
				if not ext in ["bsp", "nav", "txt"]:
					continue
				file_path = os.path.join(root,file)
				data_file = file_path.replace(self.game_root,"").strip("/\\")
				data_path = os.path.join(self.extract_root,data_file)
				if ext == "bsp":
					# TODO: Decompile map with BSPSRC
					#java -jar 
					pass
				elif ext == "nav":
					# TODO: Process navmesh
					pass
				elif ext == "txt":
					if os.path.exists(data_path) and not force:
						return
					data_dir = os.path.dirname(data_path)
					if not os.path.exists(data_dir):
						print "Creating %s" % data_dir
						os.makedirs(data_dir)
					# TODO: Compare file contents and force together
					print "Copying %s to %s" % (data_file, data_path)
					#copyfile(file_path,data_path)

	def find_game_root(self):
		for root in self.parent.config['game_roots']:
			for testpath in [os.path.join(self.game_name,self.game_dir),self.game_dir,self.game_name]:
				game_root = os.path.expanduser(os.path.join(root,testpath))
				if os.path.exists(game_root):
					self.game_root = game_root
					return game_root
		return False

	def load_steam_inf(self, game_root=None, inf_file="steam.inf"):
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

	def load_vpks(self, game_dir=None, vpk_files=None, game_root=None):
		if vpk_files is None:
			vpk_files = self.vpk_files
		if game_dir is None:
			game_dir = self.game_dir
		if game_root is None:
			game_root = self.game_root
		for vpk_file in vpk_files:
			self.vpks[vpk_file] = VPKFile(vpk_file = os.path.join(game_root,"%s_%s_dir.vpk" % (game_dir, vpk_file)), parent=self)

	def extract_vpk_files(self, extract_root = None, force=False, vpk_files=None):
		if vpk_files is None:
			vpk_files = self.vpk_files
		if extract_root is None:
			extract_root = self.extract_root
		for vpk_file in vpk_files:
			self.vpks[vpk_file].extract_files(extract_root=extract_root, force=force)

class Map(object):
	def __init__(self):
		pass

class VPKFile(object):
	def __init__(self,vpk_file,parent):
		self.parent = parent
		self.vpk_file = vpk_file
		self.vpk_obj = vpk.open(vpk_file)

	def get_file_list(self):
		return [file for file in self.vpk_obj]

	def extract_files(self, extract_root=None, force=False, extract_paths=None):
		if extract_paths is None:
			extract_paths = self.parent.extract_paths
		if extract_root is None:
			extract_root = self.parent.extract_root
		for file in self.get_file_list():
			if self.match_vpk_path(file=file, extract_paths=extract_paths):
				extract_path = os.path.join(extract_root,file)
				self.extract_file(file=file, extract_path=extract_path, force=force)

	def match_vpk_path(self,file,extract_paths=None):
		if extract_paths is None:
			extract_paths = self.parent.extract_paths
		for path, matches in extract_paths.iteritems():
			if not file.startswith(str(path)):
				continue
			if not fnmatch(file,matches):
				continue
			return True
		return False

	def extract_file(self, file, extract_path, force=False):
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

if __name__ == "__main__":
	gd = GameData()
#extract_root = "/home/insserver/insurgency-tools/data/mods")

#game_name="doi", vpk_files=["misc","materials"], game_root="/home/doiserver/serverfiles/doi", 
