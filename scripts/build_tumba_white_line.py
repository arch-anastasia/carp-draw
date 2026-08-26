#!/usr/bin/env python3
"""Create a non-destructive white technical-line variant of the cabinet model."""

from __future__ import annotations

import json
import sys
import zipfile
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "scripts"))

import build_tumba_3d as base  # noqa: E402


OUTPUT_DIR = ROOT / "models" / "tumba_3d_white_line"
LINE_THICKNESS_MM = 1.4


STYLE_MATERIALS = {
    "WhiteMatte": base.Material(
        "WhiteMatte",
        (0.985, 0.985, 0.980),
        0.0,
        0.92,
        emissive=(0.16, 0.16, 0.16),
    ),
    "LineDark": base.Material("LineDark", (0.035, 0.035, 0.035), 0.0, 1.0),
}


def recolor_parts(parts: list[base.Part]) -> list[base.Part]:
    return [
        base.Part(
            name=part.name,
            minimum_mm=part.minimum_mm,
            maximum_mm=part.maximum_mm,
            material="WhiteMatte",
        )
        for part in parts
    ]


def cuboid_edges(part: base.Part) -> list[tuple[tuple[float, float, float], tuple[float, float, float]]]:
    x0, y0, z0 = part.minimum_mm
    x1, y1, z1 = part.maximum_mm
    return [
        ((x0, y0, z0), (x1, y0, z0)),
        ((x0, y0, z1), (x1, y0, z1)),
        ((x0, y1, z0), (x1, y1, z0)),
        ((x0, y1, z1), (x1, y1, z1)),
        ((x0, y0, z0), (x0, y1, z0)),
        ((x0, y0, z1), (x0, y1, z1)),
        ((x1, y0, z0), (x1, y1, z0)),
        ((x1, y0, z1), (x1, y1, z1)),
        ((x0, y0, z0), (x0, y0, z1)),
        ((x0, y1, z0), (x0, y1, z1)),
        ((x1, y0, z0), (x1, y0, z1)),
        ((x1, y1, z0), (x1, y1, z1)),
    ]


def canonical_edge(
    first: tuple[float, float, float], second: tuple[float, float, float]
) -> tuple[tuple[float, float, float], tuple[float, float, float]]:
    return (first, second) if first <= second else (second, first)


def line_box(
    name: str,
    first: tuple[float, float, float],
    second: tuple[float, float, float],
    thickness: float,
) -> base.Part:
    radius = thickness / 2.0
    x0, y0, z0 = first
    x1, y1, z1 = second
    if x0 != x1:
        minimum = (min(x0, x1), y0 - radius, z0 - radius)
        maximum = (max(x0, x1), y0 + radius, z0 + radius)
    elif y0 != y1:
        minimum = (x0 - radius, min(y0, y1), z0 - radius)
        maximum = (x0 + radius, max(y0, y1), z0 + radius)
    elif z0 != z1:
        minimum = (x0 - radius, y0 - radius, min(z0, z1))
        maximum = (x0 + radius, y0 + radius, max(z0, z1))
    else:
        raise ValueError("Zero-length edge")
    return base.Part(name, minimum, maximum, "LineDark")


def build_linework(parts: list[base.Part]) -> list[base.Part]:
    # Internal closed volumes do not contribute useful technical contours.
    omit = {
        "rear_panel",
        "main_bottom_panel",
        "right_bottom_panel",
        "upper_drawer_box",
        "lower_drawer_box",
    }
    edges: set[tuple[tuple[float, float, float], tuple[float, float, float]]] = set()
    for part in parts:
        if part.name in omit:
            continue
        for first, second in cuboid_edges(part):
            edges.add(canonical_edge(first, second))
    ordered = sorted(edges)
    return [
        line_box(f"line_{index:04d}", first, second, LINE_THICKNESS_MM)
        for index, (first, second) in enumerate(ordered, start=1)
    ]


def write_manifest(
    path: Path,
    white_parts: list[base.Part],
    line_parts: list[base.Part],
) -> None:
    document = {
        "model": "Tumba cabinet — white technical line style",
        "units": "millimetres",
        "overallFurnitureEnvelope": {"width": 1800, "depth": 600, "height": 800},
        "style": {
            "surface": "matte warm white",
            "line": "dark technical contour geometry",
            "lineThicknessMm": LINE_THICKNESS_MM,
            "note": "Line geometry projects 0.7 mm beyond some nominal faces for reliable visibility in 3D viewers.",
        },
        "basePartCount": len(white_parts),
        "lineElementCount": len(line_parts),
        "sourceModel": "../tumba_3d/tumba_3d_model.glb",
        "parts": [
            {
                "name": part.name,
                "type": "surface" if part.material == "WhiteMatte" else "line",
                "material": part.material,
                "minimumMm": list(part.minimum_mm),
                "maximumMm": list(part.maximum_mm),
            }
            for part in [*white_parts, *line_parts]
        ],
    }
    path.write_text(json.dumps(document, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def write_readme(path: Path, line_count: int) -> None:
    path.write_text(
        f"""# Tumba — white technical line style

This is a non-destructive visual-style variant of the dimensionally accurate 1:1 cabinet model.

- Furniture envelope: **1800 × 600 × 800 mm**.
- Base model parts: **37**.
- Dark contour elements: **{line_count}**.
- Contour thickness: **{LINE_THICKNESS_MM} mm**.
- White surfaces and contour lines are separate named objects/materials.

## Recommended file

Use `tumba_3d_white_line.glb` for web, presentation, and general 3D viewing. OBJ/MTL and OpenSCAD versions are also included.

The contour geometry protrudes by up to half its thickness from selected faces so that the line style remains visible across common 3D viewers. The underlying furniture geometry remains unchanged.
""",
        encoding="utf-8",
    )


def main() -> None:
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    base_parts = base.build_parts()
    white_parts = recolor_parts(base_parts)
    line_parts = build_linework(base_parts)
    styled_parts = [*white_parts, *line_parts]

    # Export functions read materials from the base module at call time.
    base.MATERIALS = STYLE_MATERIALS

    mtl_path = OUTPUT_DIR / "tumba_3d_white_line.mtl"
    obj_path = OUTPUT_DIR / "tumba_3d_white_line.obj"
    glb_path = OUTPUT_DIR / "tumba_3d_white_line.glb"
    scad_path = OUTPUT_DIR / "tumba_3d_white_line.scad"
    preview_path = OUTPUT_DIR / "tumba_3d_white_line_preview.png"
    manifest_path = OUTPUT_DIR / "tumba_3d_white_line_manifest.json"
    readme_path = OUTPUT_DIR / "README.md"

    base.write_mtl(mtl_path)
    vertices, faces = base.write_obj(obj_path, mtl_path.name, styled_parts)
    base.write_glb(glb_path, styled_parts)
    base.write_scad(scad_path, styled_parts)
    # The verification preview draws technical edges directly; line rods are omitted
    # here to keep the rendered contour at one visual stroke width.
    base.render_preview(preview_path, white_parts)
    write_manifest(manifest_path, white_parts, line_parts)
    write_readme(readme_path, len(line_parts))
    validation = base.validate_glb(glb_path, len(styled_parts))

    archive_path = ROOT / "models" / "tumba_3d_white_line_bundle.zip"
    if archive_path.exists():
        archive_path.unlink()
    with zipfile.ZipFile(archive_path, "w", compression=zipfile.ZIP_DEFLATED) as archive:
        for file_path in sorted(OUTPUT_DIR.iterdir()):
            if file_path.is_file():
                archive.write(file_path, arcname=file_path.name)

    print(
        json.dumps(
            {
                "baseParts": len(white_parts),
                "lineElements": len(line_parts),
                "totalNodes": len(styled_parts),
                "objVertices": vertices,
                "objFaces": faces,
                "glb": validation,
                "archive": str(archive_path),
            },
            indent=2,
        )
    )


if __name__ == "__main__":
    main()
