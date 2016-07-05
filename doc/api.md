# Data API

This document is for planning the new version of the tools, based off a proper separation of model/view/controller, and an API to sit between the processing tools and the display tools. The goal is to segment the work for ease of extension, support third-party developers with access to the APIs, and increase the performance and usefulness of the entire suite.

## Requirements

* Excellent documentation and defined schemas for all data types.
* Cache system that uses checksums of source files to process only when needed, and return cached results as much as possible.
* Theater, CVAR, Map, Translation as the primary processed data types.
* Allow users to submit data via HTTP request to be processed, such as theaters.
