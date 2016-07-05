# Next Release Plans
I am planning on a complete refactor of this entire project, designed to bring this beast into a maintainable and useful codebase for other projects. To drive this, I am looking towards splitting out all the tools and core utilities as Composer packages.

## Proposed Breakout of Packages
The proposed way I am looking to break out functionality is as follows:
 - **keyvalues**
   - Parser that allows processing of Valve standards compliant VDF files into a data structure.
   - Support for "#base", "#include", and "[conditional]" and "?conditional" logic functions will be included.
 - **theater**
   - Inherit keyvalues and add needed support for processing theater files.
   - Support "import" directive and "IsBase" setting
   - Merging behavior of ordered lists and complex items
   - Creating JSON dumps usable by the stats tool.
 - **gamedata**
   - Find files inside the data/mods directory, based on hierarchy.
   - Load string lookup tables from translation files.
   - Process events files to produce SourceMod-compatible function stubs.
 - **config**
   - Include cvarlist functions
   - Allow creating config files based upon defaults and merging to generate usable and well-formatted configs for server admins.
 - **maps**
   - Process map source files, overviews, cpsetup and overlay data into coherent data structure.
