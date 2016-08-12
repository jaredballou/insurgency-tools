import vpk
import os
import sys
import configparser
from pprint import pprint
from glob import glob
from fnmatch import fnmatch

extract_paths = {
	"maps/": "*.txt",
	"materials/overviews/": "*",
	"materials/vgui/gameui/": "*",
	"materials/vgui/hud/": "*",
	"materials/vgui/hud_doi/": "*",
	"materials/vgui/inventory/": "*",
	"materials/vgui/logos/": "*",
	"materials/vgui/maps/": "*",
	"resource/": "*.txt",
	"resource/": "*.res",
	"scripts/playlists/": "*.txt",
	"scripts/theaters/": "*.theater",
}

class GameData(object):
	def __init__(self, game_name, game_root, extract_root, vpk_files=["misc","materials"]):
		self.extract_root = extract_root
		self.game_name = game_name
		self.vpk_files = vpk_files
		self.game_root = game_root
		self.vpks = {}
		self.steam_inf = self.load_steam_inf()
		self.load_vpks()
		self.extract_vpk_files(force=False)

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
			self.vpks[vpk_file] = VPKFile(vpk_file = os.path.join(game_root,"%s_%s_dir.vpk" % (game_name, vpk_file)))
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
	def __init__(self,vpk_file):
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
	gd = GameData(game_name="doi", vpk_files=["misc","materials"], game_root="/home/doiserver/serverfiles/doi", extract_root = "/home/insserver/insurgency-tools/data/mods")
