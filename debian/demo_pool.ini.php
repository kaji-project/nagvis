; <?php return 1; ?>
; the line above is to prevent
; viewing this file from web.
; DON'T REMOVE IT!

; in this example the browser switches between the maps demo and demo2 every 15
; seconds, the rotation is enabled by url: index.php?rotation=demo
[rotation_demo]
; These steps are rotated. The single steps may have optional prefixes like "Demo2:"
; which are used as display text on the index pages rotation list.
; You may also add external URLs as steps. Simply enclose the url using []
; instead of the map name. It is also possible to add automaps to rotations,
; add an @ sign before the automap name to add an automap to the rotation.
maps="demo-germany,demo-ham-racks,demo-load,demo-muc-srv1,demo-geomap,demo-automap"
; rotation interval (seconds)
interval=5

; This file defines a backend instance of the Test backend
; which is used by some demo maps. This can be removed when
; you are not interested in the demo maps.

[backend_demo]
backendtype=Test

