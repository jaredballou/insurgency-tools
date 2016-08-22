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
