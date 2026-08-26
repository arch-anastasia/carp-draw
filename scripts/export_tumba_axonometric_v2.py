#!/usr/bin/env python3
"""Export a dimensioned orthographic axonometry of the corrected cabinet."""

from __future__ import annotations

import math
import sys
import zipfile
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont


ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "scripts"))

import build_tumba_3d_v2 as model  # noqa: E402


OUTPUT_DIR = ROOT / "models" / "tumba_3d_v2" / "exports"
SVG_PATH = OUTPUT_DIR / "tumba-axonometric-dimensioned-hidden-edges.svg"
PNG_PATH = OUTPUT_DIR / "tumba-axonometric-dimensioned-hidden-edges-4k.png"
BUNDLE_PATH = OUTPUT_DIR / "tumba-axonometric-dimensioned-export.zip"
SHEET_WIDTH, SHEET_HEIGHT = 2400, 2100

FACES = [(0, 1, 3, 2), (4, 6, 7, 5), (0, 4, 5, 1), (2, 3, 7, 6), (0, 2, 6, 4), (1, 5, 7, 3)]
EDGES = [(0, 1), (0, 2), (0, 4), (1, 3), (1, 5), (2, 3), (2, 6), (3, 7), (4, 5), (4, 6), (5, 7), (6, 7)]


def normalize(vector: tuple[float, float, float]) -> tuple[float, float, float]:
    length = math.sqrt(sum(value * value for value in vector)) or 1.0
    return tuple(value / length for value in vector)


FORWARD = normalize((1.0, 1.0, -1.0))
RIGHT = normalize((1.0, -1.0, 0.0))
UP = normalize((1.0, 1.0, 2.0))


def dot(first: tuple[float, float, float], second: tuple[float, float, float]) -> float:
    return sum(a * b for a, b in zip(first, second))


def raw_project(point: tuple[float, float, float]) -> tuple[float, float, float]:
    return (dot(point, RIGHT), -dot(point, UP), dot(point, FORWARD))


def box_vertices(part: model.base.Part) -> list[tuple[float, float, float]]:
    x0, y0, z0 = part.minimum_mm
    x1, y1, z1 = part.maximum_mm
    return [(x0, y0, z0), (x0, y0, z1), (x0, y1, z0), (x0, y1, z1), (x1, y0, z0), (x1, y0, z1), (x1, y1, z0), (x1, y1, z1)]


def projection_transform(parts: list[model.base.Part]) -> tuple[float, float, float]:
    projected = [raw_project(vertex) for part in parts for vertex in box_vertices(part)]
    min_x = min(point[0] for point in projected)
    max_x = max(point[0] for point in projected)
    min_y = min(point[1] for point in projected)
    max_y = max(point[1] for point in projected)
    scale = min(1580 / (max_x - min_x), 1450 / (max_y - min_y))
    model_width = (max_x - min_x) * scale
    offset_x = (SHEET_WIDTH - model_width) / 2 - min_x * scale + 80
    offset_y = 480 - min_y * scale
    return scale, offset_x, offset_y


def project(point: tuple[float, float, float], transform: tuple[float, float, float]) -> tuple[float, float, float]:
    scale, offset_x, offset_y = transform
    x, y, depth = raw_project(point)
    return (x * scale + offset_x, y * scale + offset_y, depth)


def triangle_depth(px: float, py: float, a: tuple[float, float, float], b: tuple[float, float, float], c: tuple[float, float, float]) -> float | None:
    denominator = (b[1] - c[1]) * (a[0] - c[0]) + (c[0] - b[0]) * (a[1] - c[1])
    if abs(denominator) < 1e-8:
        return None
    u = ((b[1] - c[1]) * (px - c[0]) + (c[0] - b[0]) * (py - c[1])) / denominator
    v = ((c[1] - a[1]) * (px - c[0]) + (a[0] - c[0]) * (py - c[1])) / denominator
    w = 1.0 - u - v
    if u < -0.001 or v < -0.001 or w < -0.001:
        return None
    return u * a[2] + v * b[2] + w * c[2]


def face_depth(face: list[tuple[float, float, float]], x: float, y: float) -> float | None:
    depth = triangle_depth(x, y, face[0], face[1], face[2])
    return depth if depth is not None else triangle_depth(x, y, face[0], face[2], face[3])


def edge_hidden(edge: tuple[tuple[float, float, float], tuple[float, float, float]], faces: list[list[tuple[float, float, float]]]) -> bool:
    hidden_samples = 0
    for index in range(1, 8):
        t = index / 8
        x = edge[0][0] + (edge[1][0] - edge[0][0]) * t
        y = edge[0][1] + (edge[1][1] - edge[0][1]) * t
        depth = edge[0][2] + (edge[1][2] - edge[0][2]) * t
        nearest = math.inf
        for face in faces:
            candidate = face_depth(face, x, y)
            if candidate is not None:
                nearest = min(nearest, candidate)
        if nearest < depth - 2.5:
            hidden_samples += 1
    return hidden_samples >= 4


def build_projection() -> tuple[list[list[tuple[float, float, float]]], list[tuple[tuple[float, float, float], tuple[float, float, float]]], list[tuple[tuple[float, float, float], tuple[float, float, float]]], tuple[float, float, float]]:
    parts = model.build_parts()
    transform = projection_transform(parts)
    faces: list[list[tuple[float, float, float]]] = []
    all_edges: list[tuple[tuple[float, float, float], tuple[float, float, float]]] = []
    for part in parts:
        vertices = [project(vertex, transform) for vertex in box_vertices(part)]
        faces.extend([[vertices[index] for index in face] for face in FACES])
        all_edges.extend((vertices[first], vertices[second]) for first, second in EDGES)
    faces.sort(key=lambda face: sum(point[2] for point in face) / 4, reverse=True)
    hidden: list[tuple[tuple[float, float, float], tuple[float, float, float]]] = []
    visible: list[tuple[tuple[float, float, float], tuple[float, float, float]]] = []
    for edge in all_edges:
        (hidden if edge_hidden(edge, faces) else visible).append(edge)
    return faces, hidden, visible, transform


def get_font(size: int) -> ImageFont.FreeTypeFont | ImageFont.ImageFont:
    for candidate in (Path("C:/Windows/Fonts/arial.ttf"), Path("C:/Windows/Fonts/segoeui.ttf")):
        if candidate.exists():
            return ImageFont.truetype(str(candidate), size=size)
    return ImageFont.load_default()


def axis_dimension(
    p1: tuple[float, float],
    p2: tuple[float, float],
    first: tuple[float, float],
    second: tuple[float, float],
    label: str,
) -> dict[str, object]:
    dx, dy = second[0] - first[0], second[1] - first[1]
    length = math.hypot(dx, dy) or 1.0
    ux, uy = dx / length, dy / length
    ex, ey = first[0] - p1[0], first[1] - p1[1]
    extension_length = math.hypot(ex, ey) or 1.0
    extension = (ex / extension_length, ey / extension_length)
    angle = math.degrees(math.atan2(dy, dx))
    if angle > 90 or angle < -90:
        angle += 180
    return {
        "p1": p1,
        "p2": p2,
        "first": first,
        "second": second,
        "extension": extension,
        "unit": (ux, uy),
        "label": label,
        "angle": angle,
    }


def build_dimensions(transform: tuple[float, float, float]) -> list[dict[str, object]]:
    def p(x: float, y: float, z: float) -> tuple[float, float]:
        projected = project((x, y, z), transform)
        return (projected[0], projected[1])

    x0, split, x1 = p(0, 0, 0), p(1150, 0, 0), p(1800, 0, 0)
    y_end = p(0, 600, 0)
    rear_top = p(0, 600, 800)
    return [
        axis_dimension(x0, split, p(0, 0, -70), p(1150, 0, -70), "1150"),
        axis_dimension(split, x1, p(1150, 0, -70), p(1800, 0, -70), "650"),
        axis_dimension(x0, x1, p(0, 0, -155), p(1800, 0, -155), "1800"),
        axis_dimension(x0, y_end, p(-150, 0, 0), p(-150, 600, 0), "600"),
        axis_dimension(y_end, rear_top, p(-150, 600, 0), p(-150, 600, 800), "800"),
    ]


def svg_dimension(dimension: dict[str, object]) -> str:
    p1 = dimension["p1"]
    p2 = dimension["p2"]
    first = dimension["first"]
    second = dimension["second"]
    extension = dimension["extension"]
    unit = dimension["unit"]
    label = str(dimension["label"])
    angle = float(dimension["angle"])
    assert isinstance(p1, tuple) and isinstance(p2, tuple) and isinstance(first, tuple) and isinstance(second, tuple)
    assert isinstance(extension, tuple) and isinstance(unit, tuple)

    def triangle(tip: tuple[float, float], base_direction: tuple[float, float]) -> str:
        base = (tip[0] + base_direction[0] * 13, tip[1] + base_direction[1] * 13)
        perpendicular = (-base_direction[1] * 4.6, base_direction[0] * 4.6)
        return f'<path d="M {tip[0]:.2f} {tip[1]:.2f} L {base[0] + perpendicular[0]:.2f} {base[1] + perpendicular[1]:.2f} L {base[0] - perpendicular[0]:.2f} {base[1] - perpendicular[1]:.2f} Z"/>'

    midpoint = ((first[0] + second[0]) / 2, (first[1] + second[1]) / 2)
    width = max(78, len(label) * 22)
    return "".join(
        [
            f'<line x1="{p1[0] + extension[0] * 6:.2f}" y1="{p1[1] + extension[1] * 6:.2f}" x2="{first[0] + extension[0] * 8:.2f}" y2="{first[1] + extension[1] * 8:.2f}"/>',
            f'<line x1="{p2[0] + extension[0] * 6:.2f}" y1="{p2[1] + extension[1] * 6:.2f}" x2="{second[0] + extension[0] * 8:.2f}" y2="{second[1] + extension[1] * 8:.2f}"/>',
            f'<line x1="{first[0]:.2f}" y1="{first[1]:.2f}" x2="{second[0]:.2f}" y2="{second[1]:.2f}"/>',
            triangle(first, unit),
            triangle(second, (-unit[0], -unit[1])),
            f'<g transform="translate({midpoint[0]:.2f} {midpoint[1]:.2f}) rotate({angle:.2f})"><rect class="mask" x="{-width / 2:.2f}" y="-20" width="{width}" height="40"/><text x="0" y="0">{label}</text></g>',
        ]
    )


def write_svg(faces: list[list[tuple[float, float, float]]], hidden: list[tuple[tuple[float, float, float], tuple[float, float, float]]], visible: list[tuple[tuple[float, float, float], tuple[float, float, float]]], dimensions: list[dict[str, object]]) -> None:
    face_svg = "".join('<polygon points="' + " ".join(f"{point[0]:.2f},{point[1]:.2f}" for point in face) + '"/>' for face in faces)
    hidden_svg = "".join(f'<line x1="{a[0]:.2f}" y1="{a[1]:.2f}" x2="{b[0]:.2f}" y2="{b[1]:.2f}"/>' for a, b in hidden)
    visible_svg = "".join(f'<line x1="{a[0]:.2f}" y1="{a[1]:.2f}" x2="{b[0]:.2f}" y2="{b[1]:.2f}"/>' for a, b in visible)
    dimension_svg = "".join(svg_dimension(dimension) for dimension in dimensions)
    svg = f'''<svg xmlns="http://www.w3.org/2000/svg" width="{SHEET_WIDTH}" height="{SHEET_HEIGHT}" viewBox="0 0 {SHEET_WIDTH} {SHEET_HEIGHT}">
  <title>Аксонометрия тумбы с размерами</title>
  <desc>Ортографическая изометрическая проекция. Размеры указаны в миллиметрах.</desc>
  <style>
    .faces{{fill:#fff;stroke:none}}.hidden{{stroke:#888;stroke-width:1.05;stroke-dasharray:9 7;fill:none;vector-effect:non-scaling-stroke}}.visible{{stroke:#111;stroke-width:1.8;fill:none;vector-effect:non-scaling-stroke}}
    .dimension{{fill:#555;stroke:#777;stroke-width:.9;vector-effect:non-scaling-stroke}}.dimension text{{fill:#333;stroke:none;font:500 34px Arial,sans-serif;text-anchor:middle;dominant-baseline:central}}.dimension .mask{{fill:#fff;stroke:none}}
  </style>
  <rect width="{SHEET_WIDTH}" height="{SHEET_HEIGHT}" fill="#fff"/><g class="faces">{face_svg}</g><g class="hidden">{hidden_svg}</g><g class="visible">{visible_svg}</g><g class="dimension">{dimension_svg}</g>
</svg>
'''
    SVG_PATH.write_text(svg, encoding="utf-8")


def write_png(faces: list[list[tuple[float, float, float]]], hidden: list[tuple[tuple[float, float, float], tuple[float, float, float]]], visible: list[tuple[tuple[float, float, float], tuple[float, float, float]]], dimensions: list[dict[str, object]]) -> None:
    scale = 4000 / SHEET_WIDTH
    image = Image.new("RGB", (4000, round(SHEET_HEIGHT * scale)), "white")
    draw = ImageDraw.Draw(image)
    font = get_font(round(34 * scale))

    def sc(value: float) -> int:
        return round(value * scale)

    def dashed(a: tuple[float, float, float], b: tuple[float, float, float]) -> None:
        x0, y0, x1, y1 = a[0] * scale, a[1] * scale, b[0] * scale, b[1] * scale
        dx, dy, length = x1 - x0, y1 - y0, math.hypot(x1 - x0, y1 - y0)
        if not length:
            return
        ux, uy, cursor = dx / length, dy / length, 0.0
        while cursor < length:
            end = min(cursor + 9 * scale, length)
            draw.line((round(x0 + ux * cursor), round(y0 + uy * cursor), round(x0 + ux * end), round(y0 + uy * end)), fill=(136, 136, 136), width=max(1, round(1.05 * scale)))
            cursor += 16 * scale

    for face in faces:
        draw.polygon([(sc(point[0]), sc(point[1])) for point in face], fill="white")
    for first, second in hidden:
        dashed(first, second)
    for first, second in visible:
        draw.line((sc(first[0]), sc(first[1]), sc(second[0]), sc(second[1])), fill=(17, 17, 17), width=max(2, round(1.8 * scale)))

    def text_rotated(text: str, x: float, y: float, angle: float) -> None:
        bbox = font.getbbox(text)
        width, height = bbox[2] - bbox[0] + sc(10), bbox[3] - bbox[1] + sc(10)
        tile = Image.new("RGB", (width, height), "white")
        tile_draw = ImageDraw.Draw(tile)
        tile_draw.text((width // 2, height // 2), text, font=font, fill=(48, 48, 48), anchor="mm")
        tile = tile.rotate(-angle, expand=True, fillcolor="white")
        image.paste(tile, (sc(x) - tile.width // 2, sc(y) - tile.height // 2))

    for dimension in dimensions:
        p1, p2 = dimension["p1"], dimension["p2"]
        first, second = dimension["first"], dimension["second"]
        extension, unit = dimension["extension"], dimension["unit"]
        assert isinstance(p1, tuple) and isinstance(p2, tuple) and isinstance(first, tuple) and isinstance(second, tuple)
        assert isinstance(extension, tuple) and isinstance(unit, tuple)
        line_color, arrow_color = (112, 112, 112), (82, 82, 82)
        stroke = max(1, round(0.9 * scale))
        for start, end in (
            ((p1[0] + extension[0] * 6, p1[1] + extension[1] * 6), (first[0] + extension[0] * 8, first[1] + extension[1] * 8)),
            ((p2[0] + extension[0] * 6, p2[1] + extension[1] * 6), (second[0] + extension[0] * 8, second[1] + extension[1] * 8)),
            (first, second),
        ):
            draw.line((sc(start[0]), sc(start[1]), sc(end[0]), sc(end[1])), fill=line_color, width=stroke)

        def arrow(tip: tuple[float, float], direction: tuple[float, float]) -> None:
            base = (tip[0] + direction[0] * 13, tip[1] + direction[1] * 13)
            perpendicular = (-direction[1] * 4.6, direction[0] * 4.6)
            draw.polygon([(sc(tip[0]), sc(tip[1])), (sc(base[0] + perpendicular[0]), sc(base[1] + perpendicular[1])), (sc(base[0] - perpendicular[0]), sc(base[1] - perpendicular[1]))], fill=arrow_color)

        arrow(first, unit)
        arrow(second, (-unit[0], -unit[1]))
        text_rotated(str(dimension["label"]), (first[0] + second[0]) / 2, (first[1] + second[1]) / 2, float(dimension["angle"]))

    image.save(PNG_PATH, format="PNG", optimize=True)


def main() -> None:
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    faces, hidden, visible, transform = build_projection()
    dimensions = build_dimensions(transform)
    write_svg(faces, hidden, visible, dimensions)
    write_png(faces, hidden, visible, dimensions)
    with zipfile.ZipFile(BUNDLE_PATH, "w", compression=zipfile.ZIP_DEFLATED) as archive:
        archive.write(PNG_PATH, arcname=PNG_PATH.name)
        archive.write(SVG_PATH, arcname=SVG_PATH.name)
    print(f"PNG: {PNG_PATH}")
    print(f"SVG: {SVG_PATH}")
    print(f"Bundle: {BUNDLE_PATH}")
    print(f"Visible edges: {len(visible)}; hidden edges: {len(hidden)}")


if __name__ == "__main__":
    main()
