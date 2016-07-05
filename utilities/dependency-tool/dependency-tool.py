#!/usr/bin/env python

# Dependency Tool
# (c) Jared Balloy <insurgency@jballou.com>
# 
# This tool handles a number of dependency types, right now Git repos and
# rsync servers. The INI file needs to be in the same directory as the
# script. It has rudimentary dependency checking. it is VERY MUCH not
# production ready, I am just learning Python here!
#
# Requires GitPython and ConfigParser
#
# pip install gitpython
# pip install configparser

import glob
import os
import sys
from configparser import ConfigParser, ExtendedInterpolation
from pprint import pprint
import shutil
import git
from git import Repo

# Set initial variables
scriptpath = os.path.dirname(os.path.realpath(__file__))
scriptfile = os.path.basename(os.path.realpath(__file__))
scriptbase = os.path.splitext(scriptfile)[0]
configfile = "%s.ini" % (scriptbase)

# Load possible config file paths into array, from least to most important
configfiles = []
for spath in [scriptpath,"~"]:
  for prefix in ['','.']:
    configfiles.append("%s/%s%s" % (spath,prefix,configfile))

# Load config file
parser = ConfigParser(interpolation=ExtendedInterpolation())
parser.read(configfiles)
parser.optionxform = str
config = {}

# Main program logic
def main():
  LoadConfig()
  ProcessItems()
#  pprint(config)


# Load config file
def LoadConfig():
  # Create list of sections and types
  for section in parser.sections():
    # If there is an underscore, this is a subkey
    if section.find("_") == -1:
      type,name = section,""
    else:
      type,name = section.split("_",1)
    # If not defined, create empty dictionary for type
    if not type in config.keys():
      config[type] = {}
    # If there is a name, process the values
    if name != "":
      if not name in config[type].keys():
        # Default is an empty dict
        default = {}
        defsec = type+"_DEFAULT"
        # If there is a DEFAULT section for this type, use it as the default
        if parser.has_section(defsec):
          # If DEFAULT hasn't been processed, do so now. TODO: Move this to a function
          if not "DEFAULT" in config[type].keys():
            config[type]["DEFAULT"] = {}
            for option in parser[defsec]:
              config[type]["DEFAULT"][option] = parser.get(defsec,option)
          default = config[type]["DEFAULT"].copy()
        config[type][name] = default
      # Load values into dict
      for option in parser[section]:
        config[type][name][option] =  parser.get(section,option)

# Process items
def ProcessItems():
  for type in config.keys():
    for name in config[type].keys():
      ProcessItem(type,name)

# Process individual item
def ProcessItem(type,name=""):
  # If name isn't set, try to split type to figure it out
  if name == "":
    if type.find("_") != -1:
      type,name = type.split("_",1)

  # If this is the default item , do not run
  if name == "DEFAULT":
    return

  # Check to see if this item has been processed successfully
  if "_processed" in config[type][name].keys():
    if config[type][name]["_processed"]:
      return

  # If this item depends on another, make sure it gets processed first
  if "depends" in config[type][name].keys():
    for item in config[type][name]["depends"].split():
      ProcessItem(item)

  # Execute the function with the name of the type
  print "> Processing type %s name %s...." % (type,name)
  config[type][name]["_processed"] = globals()[type.lower()](name,config[type][name])

# Functions to handle types

# paths
def paths(name,obj):
  # So far, we do nothing here
#  pprint(obj)
  return True

# git
def git(name,obj):
  # Find checkout path
  if not "dest" in obj.keys():
    if name in config["paths"].keys():
      obj["dest"] = config["paths"][name]
    else:
      print ">> ERROR: Cannot parse \"%s\": Missing dest parameter and cannot find default in paths!"
      return False
  # Set repo name if unset
  if not "repo" in obj.keys():
    obj["repo"] = "%s.git" % (name)
  # Set source URL if not set
  if not "source" in obj.keys():
    if obj["method"] == "ssh":
      obj["source"] = "git@%s:%s/%s" % (obj["server"],obj["user"],obj["repo"])
    else:
      obj["source"] = "%s://%s/%s/%s" % (obj["method"],obj["server"],obj["user"],obj["repo"])

  # Create parent directory if needed
  parent = os.path.dirname(obj["dest"])
  if not os.path.exists(parent):
    os.makedirs(obj["dest"])

  # Create repo
  repo = Repo.init(obj["dest"])

  # Create origin
  if not "origin" in [ str(remote.name) for remote in repo.remotes]:
    print ">> Creating %s origin to %s" % (name,obj["source"])
    origin = repo.create_remote('origin',obj["source"])
  # Make sure it's pointed at the right URL
  else:
    if repo.remotes["origin"].url != obj["source"]:
      repo.delete_remote("origin")
      origin = repo.create_remote('origin',obj["source"])
      print ">> Updating %s origin to %s" % (name,obj["source"])
    else:
      origin = repo.remotes["origin"]

  # Fetch
  origin.fetch()

  # Pull latest
  origin.pull("master")
  return True

# rsync
def rsync(name,obj):
  # Locate destination
  if not "dest" in obj.keys():
    if name in config["paths"].keys():
      obj["dest"] = config["paths"][name]
    else:
      print ">>ERROR: Cannot parse \"%s\": Missing dest parameter and cannot find default in paths!"
      return False

  # Get listing of existing files
  if "symlink" in obj.keys():
    prefiles = {}
    for path in ["dest","symlink"]:
      if os.path.exists(obj[path]):
        prefiles[path] = os.listdir(obj[path])
      else:
        os.makedirs(obj[path])
        prefiles[path] = []
  # Append "/" to end of paths
  if obj["source"][-1] != "/":
    obj["source"] = obj["source"]+"/"
  if obj["dest"][-1] != "/":
    obj["dest"] = obj["dest"]+"/"

  # Rsync files
  command = "rsync %s %s %s/" % (obj["args"],obj["source"],os.path.abspath(obj["dest"]))
  print command
  os.system(command)

  # Update symlinks
  if "symlink" in obj.keys():
    postfiles = {}
    diff = {"new": {}, "old": {},}
    for path in ["dest","symlink"]:
      if os.path.exists(obj[path]):
        postfiles[path] = os.listdir(obj[path])
        diff["new"][path] = list(set(postfiles[path]) - set(prefiles[path]))
        diff["old"][path] = list(set(prefiles[path]) - set(postfiles[path]))
    # Create symlinks as needed
    for filename in postfiles["dest"]:
      link = os.path.join(obj["symlink"],filename)
      source = os.path.join(obj["dest"],filename)

      linkpath = os.path.realpath(link)
      linkdir = os.path.dirname(linkpath)
      # If forcelinks is true, delete any files not pointing at destination
      if (obj["forcelinks"] == "true" and linkpath != source):
        print ">> Forcing overwrite of %s" % (filename)
        os.unlink(link)
      # Create symlink
      if (not os.path.exists(link)):
        print ">> Creating symlink for %s" % (filename)
        os.symlink(source,link)
#  pprint(obj)
  return True

# This should be run after all other code
if __name__=="__main__":
   main()

