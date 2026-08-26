# Tumba 3D model — corrected reconstruction v2

This is a separate, corrected 1:1 reconstruction based on the supplied product photo, line drawing, and the latest approved three-view orthographic sheet.

## Files

- `tumba_3d_v2.glb` — preferred viewing/exchange model with named parts and PBR materials.
- `tumba_3d_v2.obj` + `tumba_3d_v2.mtl` — broadly compatible mesh exchange pair.
- `tumba_3d_v2.scad` — editable OpenSCAD geometry in millimetres.
- `tumba_3d_v2_preview.png` — verification render.
- `tumba_3d_v2_manifest.json` — dimensions, assumptions, and bounds for every part.

## Scale and axes

Overall envelope: **1800 × 600 × 800 mm** (width × depth × height).

OBJ and GLB use metres with X = width, Y = height, and Z = depth. The front plane is Z = 0. OpenSCAD uses millimetres with X = width, Y = depth, and Z = height.

## Accuracy note

Visible proportions, module division, front composition, shelf, plinth, and corrected top recess are modeled from the references. Hidden hardware, drawer runners, hinge geometry, bevel radii, and manufacturing clearances were not measurable and remain inferred or omitted.
