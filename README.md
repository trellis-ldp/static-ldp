# static-ldp
A simple way to expose static HTTP assets as an LDP server.

By placing this `index.php` file in your server's directory root,
a server of static resources becomes a conforming LDP server.

Individual files are served as `ldp:NonRDFSource` resources,
and directories are served as `ldp:BasicContainer` resources.
