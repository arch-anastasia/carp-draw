# Tumba 3D model

This folder contains a 1:1 cabinet model reconstructed from the supplied line drawing and the dimensioned orthographic drawings in the project.

## Files

- `tumba_3d_model.glb` — recommended exchange/viewing file; named parts and materials are preserved.
- `tumba_3d_model.obj` + `tumba_3d_model.mtl` — broadly compatible mesh exchange pair.
- `tumba_3d_model.scad` — editable parametric OpenSCAD source in millimetres.
- `tumba_3d_preview.png` — verification render.
- `tumba_3d_manifest.json` — dimensions, sources, assumptions, and per-part bounds.

## Scale and axes

Overall envelope: **1800 × 600 × 800 mm** (width × depth × height).

OBJ and GLB are exported in metres with X = width, Y = height, and Z = depth. The front plane is Z = 0. OpenSCAD uses millimetres with X = width, Y = depth, and Z = height.

## Accuracy note

The annotated overall dimensions and visible divisions are modeled directly. Hidden drawer construction, exact cavity depth, rear-panel build-up, hardware, bevel radii, and manufacturing clearances were not present in the source and remain inferred. Verify those items before fabrication.
