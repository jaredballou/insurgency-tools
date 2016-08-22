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
