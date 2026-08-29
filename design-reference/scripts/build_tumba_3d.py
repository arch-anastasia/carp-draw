#!/usr/bin/env python3
"""Build a dimensionally consistent 3D cabinet model from the project drawings.

The script uses only the Python standard library and writes:
  * glTF Binary (.glb), with one named node per modeled part
  * Wavefront OBJ + MTL, with one named object per modeled part
  * OpenSCAD source, parameterized in millimetres
  * a shaded PNG verification preview
  * a JSON manifest with dimensions and assumptions

Model convention before export:
  X = width, Y = depth (front at 0), Z = height, dimensions in millimetres.
OBJ/GLB export convention:
  X = width, Y = height, Z = depth (front at 0), dimensions in metres.
"""

from __future__ import annotations

import json
import math
import struct
import zlib
from array import array
from dataclasses import dataclass
from pathlib import Path
from typing import Iterable, Sequence


ROOT = Path(__file__).resolve().parents[1]
OUTPUT_DIR = ROOT / "models" / "tumba_3d"


@dataclass(frozen=True)
class Material:
    name: str
    color: tuple[float, float, float]
    metallic: float
    roughness: float
    emissive: tuple[float, float, float] = (0.0, 0.0, 0.0)


@dataclass(frozen=True)
class Part:
    name: str
    minimum_mm: tuple[float, float, float]
    maximum_mm: tuple[float, float, float]
    material: str

    @property
    def size_mm(self) -> tuple[float, float, float]:
        return tuple(b - a for a, b in zip(self.minimum_mm, self.maximum_mm))


MATERIALS: dict[str, Material] = {
    "BrownLeather": Material("BrownLeather", (0.40, 0.235, 0.145), 0.08, 0.78),
    "BrownEdge": Material("BrownEdge", (0.30, 0.155, 0.090), 0.10, 0.72),
    "InteriorBronze": Material("InteriorBronze", (0.36, 0.205, 0.125), 0.25, 0.55),
    "DarkInterior": Material("DarkInterior", (0.18, 0.085, 0.045), 0.10, 0.82),
    "BronzeMetal": Material("BronzeMetal", (0.59, 0.355, 0.205), 0.72, 0.30),
    "WarmLight": Material(
        "WarmLight",
        (1.0, 0.80, 0.48),
        0.0,
        0.35,
        emissive=(1.0, 0.54, 0.20),
    ),
}


def add_box(
    parts: list[Part],
    name: str,
    x0: float,
    y0: float,
    z0: float,
    x1: float,
    y1: float,
    z1: float,
    material: str,
) -> None:
    part = Part(name, (x0, y0, z0), (x1, y1, z1), material)
    if any(size <= 0 for size in part.size_mm):
        raise ValueError(f"Invalid dimensions for {name}: {part.size_mm}")
    if material not in MATERIALS:
        raise KeyError(f"Unknown material {material!r}")
    parts.append(part)


def build_parts() -> list[Part]:
    """Build the 1:1 model using dimensions documented in the project drawings."""
    p: list[Part] = []

    # Recessed 35 mm plinth, inset 60 mm at both ends and 50 mm from the front.
    add_box(p, "plinth", 60, 50, 0, 1740, 560, 35, "BrownEdge")

    # Primary carcass: 20 mm side, divider, rear and bottom panels.
    add_box(p, "left_side_panel", 0, 0, 35, 20, 600, 800, "BrownLeather")
    add_box(p, "right_side_panel", 1780, 0, 35, 1800, 600, 800, "BrownLeather")
    add_box(p, "rear_panel", 20, 580, 35, 1780, 600, 780, "DarkInterior")
    add_box(p, "module_divider", 1280, 20, 35, 1300, 580, 780, "BrownEdge")
    add_box(p, "main_bottom_panel", 20, 20, 35, 1280, 580, 55, "BrownEdge")
    add_box(p, "right_bottom_panel", 1300, 20, 35, 1780, 580, 55, "BrownEdge")

    # Front elevation: 1300 mm main module plus 500 mm full-height right door.
    add_box(p, "upper_front_panel", 20, 0, 640, 1280, 20, 800, "BrownLeather")
    add_box(p, "upper_drawer_front", 20, 0, 177, 1280, 20, 323, "BrownLeather")
    add_box(p, "lower_drawer_front", 20, 0, 37, 1280, 20, 173, "BrownLeather")
    add_box(p, "right_full_height_door", 1304, 0, 39, 1778, 20, 796, "BrownLeather")

    # Closed drawer volumes keep the model physically legible when viewed from the rear.
    add_box(p, "upper_drawer_box", 40, 22, 185, 1260, 550, 315, "DarkInterior")
    add_box(p, "lower_drawer_box", 40, 22, 45, 1260, 550, 165, "DarkInterior")

    # Open 315 mm display niche, with a 10 mm shelf located 155 mm below its top.
    add_box(p, "niche_ceiling", 20, 20, 620, 1280, 580, 640, "InteriorBronze")
    add_box(p, "niche_floor", 20, 20, 325, 1280, 580, 345, "InteriorBronze")
    add_box(p, "niche_back_lining", 20, 570, 325, 1280, 580, 640, "InteriorBronze")
    add_box(p, "niche_shelf", 30, 18, 475, 1270, 570, 485, "InteriorBronze")
    add_box(p, "niche_shelf_front_edge", 30, 12, 475, 1270, 22, 485, "BronzeMetal")
    add_box(p, "niche_upper_trim", 30, 12, 622, 1270, 24, 632, "BronzeMetal")
    add_box(p, "niche_light_strip", 40, 20, 624, 1260, 26, 629, "WarmLight")

    # Top: 20 mm perimeter rim, a broad left work surface, and the right 440 x 540 cavity.
    add_box(p, "top_front_rim", 0, 0, 780, 1800, 20, 800, "BrownEdge")
    add_box(p, "top_back_rim", 0, 580, 780, 1800, 600, 800, "BrownEdge")
    add_box(p, "top_left_rim", 0, 20, 780, 20, 580, 800, "BrownEdge")
    add_box(p, "top_right_rim", 1780, 20, 780, 1800, 580, 800, "BrownEdge")
    add_box(p, "main_top_surface", 20, 20, 780, 1300, 580, 798, "BrownLeather")

    # Cavity datum: X 1330..1770, Y 30..570, matching 440 x 540 mm.
    add_box(p, "cavity_left_rim", 1300, 20, 780, 1330, 580, 798, "BrownLeather")
    add_box(p, "cavity_right_rim", 1770, 20, 780, 1780, 580, 798, "BrownLeather")
    add_box(p, "cavity_front_rim", 1330, 20, 780, 1770, 30, 798, "BrownLeather")
    add_box(p, "cavity_back_rim", 1330, 570, 780, 1770, 580, 798, "BrownLeather")
    add_box(p, "cavity_floor", 1330, 30, 690, 1770, 570, 710, "InteriorBronze")
    add_box(p, "cavity_left_wall", 1330, 30, 710, 1350, 570, 780, "InteriorBronze")
    add_box(p, "cavity_right_wall", 1750, 30, 710, 1770, 570, 780, "InteriorBronze")
    add_box(p, "cavity_front_wall", 1350, 30, 710, 1750, 50, 780, "InteriorBronze")
    add_box(p, "cavity_back_wall", 1350, 550, 710, 1750, 570, 780, "InteriorBronze")

    # Rear cross-rail positions: 50 mm and a further 25 mm from the cavity's rear datum.
    add_box(p, "cavity_rear_rail", 1350, 515, 752, 1750, 525, 766, "BronzeMetal")
    add_box(p, "cavity_inner_rail", 1350, 490, 738, 1750, 500, 752, "BronzeMetal")
    add_box(p, "cavity_light_bar", 1360, 503, 754, 1740, 511, 762, "WarmLight")

    return p


def box_faces_mm(part: Part) -> list[tuple[list[tuple[float, float, float]], tuple[float, float, float]]]:
    x0, y0, z0 = part.minimum_mm
    x1, y1, z1 = part.maximum_mm
    p000 = (x0, y0, z0)
    p001 = (x0, y0, z1)
    p010 = (x0, y1, z0)
    p011 = (x0, y1, z1)
    p100 = (x1, y0, z0)
    p101 = (x1, y0, z1)
    p110 = (x1, y1, z0)
    p111 = (x1, y1, z1)
    return [
        ([p000, p001, p011, p010], (-1.0, 0.0, 0.0)),
        ([p100, p110, p111, p101], (1.0, 0.0, 0.0)),
        ([p000, p100, p101, p001], (0.0, -1.0, 0.0)),
        ([p010, p011, p111, p110], (0.0, 1.0, 0.0)),
        ([p000, p010, p110, p100], (0.0, 0.0, -1.0)),
        ([p001, p101, p111, p011], (0.0, 0.0, 1.0)),
    ]


def to_y_up_metres(point_mm: Sequence[float]) -> tuple[float, float, float]:
    x, depth, height = point_mm
    return (x / 1000.0, height / 1000.0, -depth / 1000.0)


def normal_to_y_up(normal: Sequence[float]) -> tuple[float, float, float]:
    x, depth, height = normal
    return (x, height, -depth)


def write_mtl(path: Path) -> None:
    lines = ["# Materials for the 1:1 cabinet model", ""]
    for material in MATERIALS.values():
        r, g, b = material.color
        lines.extend(
            [
                f"newmtl {material.name}",
                f"Ka {r * 0.25:.6f} {g * 0.25:.6f} {b * 0.25:.6f}",
                f"Kd {r:.6f} {g:.6f} {b:.6f}",
                f"Ks {material.metallic * 0.7:.6f} {material.metallic * 0.7:.6f} {material.metallic * 0.7:.6f}",
                f"Ns {max(2.0, (1.0 - material.roughness) * 300.0):.3f}",
                "d 1.0",
                "illum 2",
                "",
            ]
        )
    path.write_text("\n".join(lines), encoding="utf-8")


def write_obj(path: Path, mtl_name: str, parts: Sequence[Part]) -> tuple[int, int]:
    lines = [
        "# Cabinet 3D model generated from project orthographic drawings",
        "# Units: metres; axes: X width, Y up, Z depth; front plane is Z=0",
        f"mtllib {mtl_name}",
        "s off",
        "",
    ]
    vertex_index = 1
    normal_index = 1
    face_count = 0
    for part in parts:
        lines.extend([f"o {part.name}", f"usemtl {part.material}"])
        for quad, normal in box_faces_mm(part):
            for point in quad:
                x, y, z = to_y_up_metres(point)
                lines.append(f"v {x:.6f} {y:.6f} {z:.6f}")
            nx, ny, nz = normal_to_y_up(normal)
            lines.append(f"vn {nx:.6f} {ny:.6f} {nz:.6f}")
            indices = [vertex_index + i for i in range(4)]
            lines.append("f " + " ".join(f"{i}//{normal_index}" for i in indices))
            vertex_index += 4
            normal_index += 1
            face_count += 1
        lines.append("")
    path.write_text("\n".join(lines), encoding="utf-8")
    return vertex_index - 1, face_count


def align4(buffer: bytearray, pad_byte: int = 0) -> None:
    while len(buffer) % 4:
        buffer.append(pad_byte)


def write_glb(path: Path, parts: Sequence[Part]) -> None:
    binary = bytearray()
    buffer_views: list[dict[str, object]] = []
    accessors: list[dict[str, object]] = []
    meshes: list[dict[str, object]] = []
    nodes: list[dict[str, object]] = []
    material_names = list(MATERIALS)
    material_index = {name: index for index, name in enumerate(material_names)}

    def add_buffer_view(data: bytes, target: int) -> int:
        align4(binary)
        offset = len(binary)
        binary.extend(data)
        index = len(buffer_views)
        buffer_views.append(
            {
                "buffer": 0,
                "byteOffset": offset,
                "byteLength": len(data),
                "target": target,
            }
        )
        return index

    for part in parts:
        positions: list[float] = []
        normals: list[float] = []
        indices: list[int] = []
        for quad, normal in box_faces_mm(part):
            face_start = len(positions) // 3
            export_normal = normal_to_y_up(normal)
            for point in quad:
                positions.extend(to_y_up_metres(point))
                normals.extend(export_normal)
            indices.extend(
                [
                    face_start,
                    face_start + 1,
                    face_start + 2,
                    face_start,
                    face_start + 2,
                    face_start + 3,
                ]
            )

        position_bytes = struct.pack(f"<{len(positions)}f", *positions)
        normal_bytes = struct.pack(f"<{len(normals)}f", *normals)
        index_bytes = struct.pack(f"<{len(indices)}H", *indices)
        position_view = add_buffer_view(position_bytes, 34962)
        normal_view = add_buffer_view(normal_bytes, 34962)
        index_view = add_buffer_view(index_bytes, 34963)

        xs = positions[0::3]
        ys = positions[1::3]
        zs = positions[2::3]
        position_accessor = len(accessors)
        accessors.append(
            {
                "bufferView": position_view,
                "componentType": 5126,
                "count": len(positions) // 3,
                "type": "VEC3",
                "min": [min(xs), min(ys), min(zs)],
                "max": [max(xs), max(ys), max(zs)],
            }
        )
        normal_accessor = len(accessors)
        accessors.append(
            {
                "bufferView": normal_view,
                "componentType": 5126,
                "count": len(normals) // 3,
                "type": "VEC3",
            }
        )
        index_accessor = len(accessors)
        accessors.append(
            {
                "bufferView": index_view,
                "componentType": 5123,
                "count": len(indices),
                "type": "SCALAR",
                "min": [min(indices)],
                "max": [max(indices)],
            }
        )
        mesh_index = len(meshes)
        meshes.append(
            {
                "name": part.name,
                "primitives": [
                    {
                        "attributes": {"POSITION": position_accessor, "NORMAL": normal_accessor},
                        "indices": index_accessor,
                        "material": material_index[part.material],
                        "mode": 4,
                    }
                ],
            }
        )
        nodes.append({"name": part.name, "mesh": mesh_index})

    gltf_materials = []
    for name in material_names:
        material = MATERIALS[name]
        entry: dict[str, object] = {
            "name": material.name,
            "pbrMetallicRoughness": {
                "baseColorFactor": [*material.color, 1.0],
                "metallicFactor": material.metallic,
                "roughnessFactor": material.roughness,
            },
        }
        if any(value > 0 for value in material.emissive):
            entry["emissiveFactor"] = list(material.emissive)
        gltf_materials.append(entry)

    gltf = {
        "asset": {
            "version": "2.0",
            "generator": "Codex parametric cabinet builder",
            "extras": {
                "units": "metres",
                "sourceDimensionsMm": [1800, 600, 800],
            },
        },
        "scene": 0,
        "scenes": [{"name": "Cabinet", "nodes": list(range(len(nodes)))}],
        "nodes": nodes,
        "meshes": meshes,
        "materials": gltf_materials,
        "buffers": [{"byteLength": len(binary)}],
        "bufferViews": buffer_views,
        "accessors": accessors,
    }

    json_chunk = bytearray(json.dumps(gltf, separators=(",", ":"), ensure_ascii=False).encode("utf-8"))
    align4(json_chunk, 0x20)
    align4(binary, 0x00)
    total_length = 12 + 8 + len(json_chunk) + 8 + len(binary)
    with path.open("wb") as handle:
        handle.write(struct.pack("<4sII", b"glTF", 2, total_length))
        handle.write(struct.pack("<I4s", len(json_chunk), b"JSON"))
        handle.write(json_chunk)
        handle.write(struct.pack("<I4s", len(binary), b"BIN\x00"))
        handle.write(binary)


def write_scad(path: Path, parts: Sequence[Part]) -> None:
    lines = [
        "// Parametric 1:1 cabinet model. All dimensions are millimetres.",
        "// X = width, Y = depth (front at 0), Z = height.",
        "$fn = 32;",
        "W = 1800; D = 600; H = 800;",
        "MAIN_W = 1300; RIGHT_W = 500; PANEL = 20; PLINTH_H = 35;",
        "CAVITY_W = 440; CAVITY_D = 540; CAVITY_INSET = 30;",
        "",
        "module cabinet_part(pos, size, rgba) {",
        "    color(rgba) translate(pos) cube(size, center=false);",
        "}",
        "",
        "union() {",
    ]
    for part in parts:
        material = MATERIALS[part.material]
        x, y, z = part.minimum_mm
        sx, sy, sz = part.size_mm
        color = ", ".join(f"{value:.4f}" for value in (*material.color, 1.0))
        lines.append(f"    // {part.name}")
        lines.append(
            f"    cabinet_part([{x:.3f}, {y:.3f}, {z:.3f}], "
            f"[{sx:.3f}, {sy:.3f}, {sz:.3f}], [{color}]);"
        )
    lines.append("}")
    path.write_text("\n".join(lines) + "\n", encoding="utf-8")


def vector_sub(a: Sequence[float], b: Sequence[float]) -> tuple[float, float, float]:
    return (a[0] - b[0], a[1] - b[1], a[2] - b[2])


def vector_dot(a: Sequence[float], b: Sequence[float]) -> float:
    return a[0] * b[0] + a[1] * b[1] + a[2] * b[2]


def vector_cross(a: Sequence[float], b: Sequence[float]) -> tuple[float, float, float]:
    return (
        a[1] * b[2] - a[2] * b[1],
        a[2] * b[0] - a[0] * b[2],
        a[0] * b[1] - a[1] * b[0],
    )


def vector_normalize(v: Sequence[float]) -> tuple[float, float, float]:
    length = math.sqrt(vector_dot(v, v))
    return (v[0] / length, v[1] / length, v[2] / length)


def fill_polygon(
    pixels: bytearray,
    width: int,
    height: int,
    points: Sequence[tuple[float, float]],
    color: tuple[int, int, int],
) -> None:
    min_y = max(0, int(math.floor(min(point[1] for point in points))))
    max_y = min(height - 1, int(math.ceil(max(point[1] for point in points))))
    pixel = bytes(color)
    for row in range(min_y, max_y + 1):
        scan_y = row + 0.5
        intersections: list[float] = []
        for index, first in enumerate(points):
            second = points[(index + 1) % len(points)]
            x1, y1 = first
            x2, y2 = second
            if y1 == y2:
                continue
            if min(y1, y2) <= scan_y < max(y1, y2):
                t = (scan_y - y1) / (y2 - y1)
                intersections.append(x1 + t * (x2 - x1))
        intersections.sort()
        for start, end in zip(intersections[0::2], intersections[1::2]):
            x0 = max(0, int(math.ceil(min(start, end))))
            x1 = min(width - 1, int(math.floor(max(start, end))))
            if x1 < x0:
                continue
            offset = (row * width + x0) * 3
            pixels[offset : offset + (x1 - x0 + 1) * 3] = pixel * (x1 - x0 + 1)


def draw_line(
    pixels: bytearray,
    width: int,
    height: int,
    first: tuple[float, float],
    second: tuple[float, float],
    color: tuple[int, int, int],
    thickness: int,
) -> None:
    x0, y0 = (int(round(first[0])), int(round(first[1])))
    x1, y1 = (int(round(second[0])), int(round(second[1])))
    dx = abs(x1 - x0)
    sx = 1 if x0 < x1 else -1
    dy = -abs(y1 - y0)
    sy = 1 if y0 < y1 else -1
    error = dx + dy
    radius = max(0, thickness // 2)
    while True:
        for yy in range(y0 - radius, y0 + radius + 1):
            if yy < 0 or yy >= height:
                continue
            for xx in range(x0 - radius, x0 + radius + 1):
                if xx < 0 or xx >= width:
                    continue
                offset = (yy * width + xx) * 3
                pixels[offset : offset + 3] = bytes(color)
        if x0 == x1 and y0 == y1:
            break
        twice = 2 * error
        if twice >= dy:
            error += dy
            x0 += sx
        if twice <= dx:
            error += dx
            y0 += sy


def png_chunk(kind: bytes, data: bytes) -> bytes:
    return struct.pack(">I", len(data)) + kind + data + struct.pack(">I", zlib.crc32(kind + data) & 0xFFFFFFFF)


def write_png(path: Path, width: int, height: int, pixels: bytes) -> None:
    raw = bytearray()
    stride = width * 3
    for row in range(height):
        raw.append(0)
        raw.extend(pixels[row * stride : (row + 1) * stride])
    payload = bytearray(b"\x89PNG\r\n\x1a\n")
    payload.extend(png_chunk(b"IHDR", struct.pack(">IIBBBBB", width, height, 8, 2, 0, 0, 0)))
    payload.extend(png_chunk(b"IDAT", zlib.compress(bytes(raw), level=9)))
    payload.extend(png_chunk(b"IEND", b""))
    path.write_bytes(payload)


def render_preview(path: Path, parts: Sequence[Part]) -> None:
    output_width, output_height = 1600, 1000
    width, height = output_width, output_height
    background = (247, 245, 241)
    pixels = bytearray(bytes(background) * (width * height))
    z_buffer = array("f", [float("inf")]) * (width * height)

    eye = (-2.55, -3.25, 2.05)
    target = (0.90, 0.30, 0.40)
    forward = vector_normalize(vector_sub(target, eye))
    right = vector_normalize(vector_cross(forward, (0.0, 0.0, 1.0)))
    camera_up = vector_cross(right, forward)
    light = vector_normalize((-0.35, -0.55, 0.76))

    visible_faces: list[dict[str, object]] = []
    raw_points: list[tuple[float, float]] = []
    for part in parts:
        for quad_mm, normal in box_faces_mm(part):
            quad = [tuple(value / 1000.0 for value in point) for point in quad_mm]
            center = tuple(sum(point[axis] for point in quad) / 4.0 for axis in range(3))
            if vector_dot(normal, vector_sub(eye, center)) <= 1e-8:
                continue
            projected: list[tuple[float, float]] = []
            depths: list[float] = []
            for point in quad:
                relative = vector_sub(point, eye)
                depth = vector_dot(relative, forward)
                if depth <= 0.01:
                    continue
                projected.append((vector_dot(relative, right) / depth, vector_dot(relative, camera_up) / depth))
                depths.append(depth)
            if len(projected) != 4:
                continue
            raw_points.extend(projected)
            diffuse = max(0.0, vector_dot(normal, light))
            material = MATERIALS[part.material]
            shade = 1.0 if any(material.emissive) else 0.54 + 0.46 * diffuse
            color = tuple(max(0, min(255, int(round(channel * shade * 255)))) for channel in material.color)
            visible_faces.append(
                {
                    "points": projected,
                    "depths": depths,
                    "depth": sum(depths) / len(depths),
                    "color": color,
                }
            )

    min_x = min(point[0] for point in raw_points)
    max_x = max(point[0] for point in raw_points)
    min_y = min(point[1] for point in raw_points)
    max_y = max(point[1] for point in raw_points)
    margin = 95
    scale = min((width - 2 * margin) / (max_x - min_x), (height - 2 * margin) / (max_y - min_y))

    def screen(point: tuple[float, float]) -> tuple[float, float]:
        used_width = (max_x - min_x) * scale
        used_height = (max_y - min_y) * scale
        left = (width - used_width) / 2.0
        top = (height - used_height) / 2.0
        return (left + (point[0] - min_x) * scale, top + (max_y - point[1]) * scale)

    def edge(first: tuple[float, float], second: tuple[float, float], point: tuple[float, float]) -> float:
        return (point[0] - first[0]) * (second[1] - first[1]) - (point[1] - first[1]) * (
            second[0] - first[0]
        )

    def rasterize_triangle(
        points: Sequence[tuple[float, float]],
        depths: Sequence[float],
        color: tuple[int, int, int],
    ) -> None:
        first, second, third = points
        area = edge(first, second, third)
        if abs(area) < 1e-9:
            return
        min_screen_x = max(0, int(math.floor(min(point[0] for point in points))))
        max_screen_x = min(width - 1, int(math.ceil(max(point[0] for point in points))))
        min_screen_y = max(0, int(math.floor(min(point[1] for point in points))))
        max_screen_y = min(height - 1, int(math.ceil(max(point[1] for point in points))))
        inverse_depths = [1.0 / value for value in depths]
        color_bytes = bytes(color)
        for screen_y in range(min_screen_y, max_screen_y + 1):
            sample_y = screen_y + 0.5
            for screen_x in range(min_screen_x, max_screen_x + 1):
                sample = (screen_x + 0.5, sample_y)
                w0 = edge(second, third, sample) / area
                w1 = edge(third, first, sample) / area
                w2 = 1.0 - w0 - w1
                if w0 < -1e-7 or w1 < -1e-7 or w2 < -1e-7:
                    continue
                inverse_depth = w0 * inverse_depths[0] + w1 * inverse_depths[1] + w2 * inverse_depths[2]
                if inverse_depth <= 0:
                    continue
                depth = 1.0 / inverse_depth
                pixel_index = screen_y * width + screen_x
                if depth >= z_buffer[pixel_index]:
                    continue
                z_buffer[pixel_index] = depth
                offset = pixel_index * 3
                pixels[offset : offset + 3] = color_bytes

    screen_faces: list[tuple[list[tuple[float, float]], list[float], tuple[int, int, int]]] = []
    for face in visible_faces:
        polygon = [screen(point) for point in face["points"]]  # type: ignore[arg-type]
        depths = list(face["depths"])  # type: ignore[arg-type]
        color = face["color"]  # type: ignore[assignment]
        screen_faces.append((polygon, depths, color))
        for indices in ((0, 1, 2), (0, 2, 3)):
            rasterize_triangle(
                [polygon[index] for index in indices],
                [depths[index] for index in indices],
                color,
            )

    def draw_visible_edge(
        first: tuple[float, float],
        second: tuple[float, float],
        first_depth: float,
        second_depth: float,
        color: tuple[int, int, int],
    ) -> None:
        delta_x = second[0] - first[0]
        delta_y = second[1] - first[1]
        steps = max(1, int(math.ceil(max(abs(delta_x), abs(delta_y)))))
        inverse_first = 1.0 / first_depth
        inverse_second = 1.0 / second_depth
        color_bytes = bytes(color)
        for step in range(steps + 1):
            t = step / steps
            screen_x = int(round(first[0] + delta_x * t))
            screen_y = int(round(first[1] + delta_y * t))
            inverse_depth = inverse_first * (1.0 - t) + inverse_second * t
            depth = 1.0 / inverse_depth
            for yy in range(screen_y - 1, screen_y + 2):
                if yy < 0 or yy >= height:
                    continue
                for xx in range(screen_x - 1, screen_x + 2):
                    if xx < 0 or xx >= width:
                        continue
                    pixel_index = yy * width + xx
                    if depth > z_buffer[pixel_index] + 0.012:
                        continue
                    offset = pixel_index * 3
                    pixels[offset : offset + 3] = color_bytes

    if "LineDark" in MATERIALS:
        edge_color = tuple(int(round(channel * 255)) for channel in MATERIALS["LineDark"].color)
    else:
        edge_color = (62, 38, 26)
    for polygon, depths, _ in screen_faces:
        for index, first in enumerate(polygon):
            next_index = (index + 1) % 4
            draw_visible_edge(first, polygon[next_index], depths[index], depths[next_index], edge_color)

    write_png(path, output_width, output_height, bytes(pixels))


def write_manifest(path: Path, parts: Sequence[Part]) -> None:
    manifest = {
        "model": "Tumba cabinet",
        "units": "millimetres",
        "overallEnvelope": {"width": 1800, "depth": 600, "height": 800},
        "documentedDimensions": {
            "mainModuleWidth": 1300,
            "rightModuleWidth": 500,
            "panelThickness": 20,
            "upperPanelHeight": 160,
            "openNicheHeight": 315,
            "upperDrawerFrontHeight": 150,
            "lowerDrawerFrontHeight": 140,
            "plinthHeight": 35,
            "plinthSideInset": 60,
            "shelfThickness": 10,
            "topCavityWidth": 440,
            "topCavityDepth": 540,
            "topCavityInset": 30,
        },
        "sources": [
            "tumba-line-drawing-furniture-only.png",
            "cabinet-orthographic-views-detailed-dimensions.svg",
            "cabinet-dimensioned-gost.svg",
            "tumba-orthographic-left-side.png",
            "tumba-orthographic-right-side.png",
        ],
        "assumptions": [
            "Overall envelope and annotated front/top dimensions are treated as authoritative.",
            "Hidden drawer boxes, cavity depth below the rim, rear panel placement, and hardware are inferred.",
            "The source provides no measured radii, bevels, hinges, runners, or manufacturing clearances.",
            "The model is suitable for visualization and dimensional development, not fabrication without field verification.",
        ],
        "coordinateSystem": {
            "sourceAndOpenSCAD": "X width, Y depth with front at 0, Z height",
            "OBJAndGLB": "X width, Y height, Z depth with front at 0; units metres",
        },
        "parts": [
            {
                "name": part.name,
                "material": part.material,
                "minimumMm": list(part.minimum_mm),
                "maximumMm": list(part.maximum_mm),
                "sizeMm": list(part.size_mm),
            }
            for part in parts
        ],
    }
    path.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def write_readme(path: Path) -> None:
    path.write_text(
        """# Tumba 3D model

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
""",
        encoding="utf-8",
    )


def validate_glb(path: Path, expected_nodes: int) -> dict[str, int]:
    data = path.read_bytes()
    magic, version, total_length = struct.unpack_from("<4sII", data, 0)
    if magic != b"glTF" or version != 2 or total_length != len(data):
        raise ValueError("Invalid GLB header")
    json_length, json_type = struct.unpack_from("<I4s", data, 12)
    if json_type != b"JSON":
        raise ValueError("Missing GLB JSON chunk")
    document = json.loads(data[20 : 20 + json_length].decode("utf-8").rstrip(" "))
    if len(document.get("nodes", [])) != expected_nodes:
        raise ValueError("Unexpected GLB node count")
    if len(document.get("meshes", [])) != expected_nodes:
        raise ValueError("Unexpected GLB mesh count")
    return {
        "bytes": len(data),
        "nodes": len(document["nodes"]),
        "meshes": len(document["meshes"]),
        "materials": len(document["materials"]),
    }


def main() -> None:
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    parts = build_parts()
    obj_path = OUTPUT_DIR / "tumba_3d_model.obj"
    mtl_path = OUTPUT_DIR / "tumba_3d_model.mtl"
    glb_path = OUTPUT_DIR / "tumba_3d_model.glb"
    scad_path = OUTPUT_DIR / "tumba_3d_model.scad"
    preview_path = OUTPUT_DIR / "tumba_3d_preview.png"
    manifest_path = OUTPUT_DIR / "tumba_3d_manifest.json"
    readme_path = OUTPUT_DIR / "README.md"

    write_mtl(mtl_path)
    vertices, faces = write_obj(obj_path, mtl_path.name, parts)
    write_glb(glb_path, parts)
    write_scad(scad_path, parts)
    render_preview(preview_path, parts)
    write_manifest(manifest_path, parts)
    write_readme(readme_path)
    glb_validation = validate_glb(glb_path, len(parts))

    summary = {
        "parts": len(parts),
        "objVertices": vertices,
        "objFaces": faces,
        "glb": glb_validation,
        "outputDirectory": str(OUTPUT_DIR),
    }
    print(json.dumps(summary, indent=2))


if __name__ == "__main__":
    main()
