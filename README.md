# static-ldp
A simple way to expose static HTTP assets as an LDP server.

Clone this repository and setup your Apache to use it as 
document root. Then configure the `sourceDirectory` to point
to the root directory of your static resources. 

Individual files are served as `ldp:NonRDFSource` resources,
and directories are served as `ldp:BasicContainer` resources.
