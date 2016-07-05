# Next-Generation KeyValues Parser

This document is a place to sort out the design of the next generation keyvalues parser. This will be a self-contained module that simply parses, allows manipulation needed by the theater creation tool, and supports exporting in several formats.

## Requirements
* Handle Theater, VDF, SourceMod, VTF and map overview KeyValues files.
* Split KeyValues and Theater into two classes, likely with Theater extending KeyValues. 
* Parse arbitrary key-values data into a relational structure.
* Support easy lookups by path.
* Support quoted and unquoted files.
* Retain comments as part of the data object for exporting.
* Validate and test tools to ensure that the theater will function properly.
* Support "#base" and "#include" directives.
* Optional "Finished Result" output that has all directives applied, and all items with import settings merged into one structure (for stats page and external API access).
