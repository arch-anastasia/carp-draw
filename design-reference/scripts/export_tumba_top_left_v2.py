#!/usr/bin/env python3
"""Export dimensioned top and left orthographic views in the approved style."""

from __future__ import annotations

import math
import zipfile
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont


ROOT = Path(__file__).resolve().parents[1]
OUTPUT_DIR = ROOT / "models" / "tumba_3d_v2" / "exports"
TOP_SVG = OUTPUT_DIR / "tumba-top-view-dimensioned-hidden-edges.svg"
TOP_PNG = OUTPUT_DIR / "tumba-top-view-dimensioned-hidden-edges-4k.png"
LEFT_SVG = OUTPUT_DIR / "tumba-left-view-dimensioned-hidden-edges.svg"
LEFT_PNG = OUTPUT_DIR / "tumba-left-view-dimensioned-hidden-edges-4k.png"
BUNDLE = OUTPUT_DIR / "tumba-top-left-dimensioned-export.zip"

Line = tuple[int, int, int, int]
Rect = tuple[int, int, int, int]
HDim = tuple[int, int, int, str]
VDim = tuple[int, int, int, str]


def rectangle_edges(rectangle: Rect) -> list[Line]:
    x0, y0, x1, y1 = rectangle
    return [(x0, y0, x1, y0), (x1, y0, x1, y1), (x1, y1, x0, y1), (x0, y1, x0, y0)]


def get_font(size: int) -> ImageFont.FreeTypeFont | ImageFont.ImageFont:
    for candidate in (Path("C:/Windows/Fonts/arial.ttf"), Path("C:/Windows/Fonts/segoeui.ttf")):
        if candidate.exists():
            return ImageFont.truetype(str(candidate), size=size)
    return ImageFont.load_default()


def svg_arrow(x: float, y: float, base_x: float, base_y: float) -> str:
    dx, dy = base_x - x, base_y - y
    length = math.hypot(dx, dy) or 1.0
    px, py = -dy / length * 4.6, dx / length * 4.6
    return f'<path d="M {x} {y} L {base_x + px} {base_y + py} L {base_x - px} {base_y - py} Z"/>'


def svg_h_dimension(x1: int, x2: int, y: int, label: str) -> str:
    midpoint = (x1 + x2) / 2
    return "".join(
        [
            f'<line x1="{x1}" y1="{y}" x2="{x2}" y2="{y}"/>',
            svg_arrow(x1, y, x1 + 13, y),
            svg_arrow(x2, y, x2 - 13, y),
            f'<rect class="mask" x="{midpoint - 44}" y="{y - 20}" width="88" height="40"/>',
            f'<text x="{midpoint}" y="{y}">{label}</text>',
        ]
    )


def svg_v_dimension(y1: int, y2: int, x: int, label: str) -> str:
    midpoint = (y1 + y2) / 2
    return "".join(
        [
            f'<line x1="{x}" y1="{y1}" x2="{x}" y2="{y2}"/>',
            svg_arrow(x, y1, x, y1 + 13),
            svg_arrow(x, y2, x, y2 - 13),
            f'<rect class="mask" x="{x - 20}" y="{midpoint - 44}" width="40" height="88"/>',
            f'<text x="{x}" y="{midpoint}" transform="rotate(-90 {x} {midpoint})">{label}</text>',
        ]
    )


def write_svg(
    path: Path,
    sheet_width: int,
    sheet_height: int,
    title: str,
    rectangles: list[Rect],
    lines: list[Line],
    hidden_lines: list[Line],
    extension_lines: list[Line],
    h_dimensions: list[HDim],
    v_dimensions: list[VDim],
) -> None:
    rects_svg = "".join(
        f'<rect x="{x0}" y="{y0}" width="{x1 - x0}" height="{y1 - y0}"/>'
        for x0, y0, x1, y1 in rectangles
    )
    lines_svg = "".join(
        f'<line x1="{x0}" y1="{y0}" x2="{x1}" y2="{y1}"/>'
        for x0, y0, x1, y1 in lines
    )
    hidden_svg = "".join(
        f'<line x1="{x0}" y1="{y0}" x2="{x1}" y2="{y1}"/>'
        for x0, y0, x1, y1 in hidden_lines
    )
    extensions_svg = "".join(
        f'<line x1="{x0}" y1="{y0}" x2="{x1}" y2="{y1}"/>'
        for x0, y0, x1, y1 in extension_lines
    )
    dimensions_svg = "".join(svg_h_dimension(*dimension) for dimension in h_dimensions)
    dimensions_svg += "".join(svg_v_dimension(*dimension) for dimension in v_dimensions)
    svg = f'''<svg xmlns="http://www.w3.org/2000/svg" width="{sheet_width}" height="{sheet_height}" viewBox="0 0 {sheet_width} {sheet_height}">
  <title>{title}</title>
  <desc>Ортографический вид тумбы. Размеры указаны в миллиметрах.</desc>
  <style>
    .object{{fill:none;stroke:#111;stroke-width:1.8;stroke-linecap:square;stroke-linejoin:miter;vector-effect:non-scaling-stroke}}
    .hidden{{fill:none;stroke:#888;stroke-width:1.05;stroke-dasharray:9 7;stroke-linecap:butt;stroke-linejoin:miter;vector-effect:non-scaling-stroke}}
    .dimension{{fill:#555;stroke:#777;stroke-width:.9;stroke-linecap:square;stroke-linejoin:miter;vector-effect:non-scaling-stroke}}
    .dimension text{{fill:#333;stroke:none;font:500 34px Arial,sans-serif;text-anchor:middle;dominant-baseline:central}}
    .dimension .mask{{fill:#fff;stroke:none}}
  </style>
  <rect width="{sheet_width}" height="{sheet_height}" fill="#fff"/>
  <g class="hidden">{hidden_svg}</g>
  <g class="object">{rects_svg}{lines_svg}</g>
  <g class="dimension">{extensions_svg}{dimensions_svg}</g>
</svg>
'''
    path.write_text(svg, encoding="utf-8")


def write_png(
    path: Path,
    sheet_width: int,
    sheet_height: int,
    output_scale: float,
    rectangles: list[Rect],
    lines: list[Line],
    hidden_lines: list[Line],
    extension_lines: list[Line],
    h_dimensions: list[HDim],
    v_dimensions: list[VDim],
) -> None:
    output_width = round(sheet_width * output_scale)
    output_height = round(sheet_height * output_scale)
    image = Image.new("RGB", (output_width, output_height), "white")
    draw = ImageDraw.Draw(image)
    object_color = (17, 17, 17)
    hidden_color = (136, 136, 136)
    dimension_color = (112, 112, 112)
    arrow_color = (82, 82, 82)
    text_color = (48, 48, 48)
    object_stroke = max(2, round(1.8 * output_scale))
    hidden_stroke = max(1, round(1.05 * output_scale))
    dimension_stroke = max(1, round(0.9 * output_scale))
    font = get_font(round(34 * output_scale))

    def sc(value: float) -> int:
        return round(value * output_scale)

    def line_scaled(values: Line, fill: tuple[int, int, int], width: int) -> None:
        draw.line(tuple(sc(value) for value in values), fill=fill, width=width)

    def dashed_line(values: Line) -> None:
        x0, y0, x1, y1 = (value * output_scale for value in values)
        dx, dy = x1 - x0, y1 - y0
        length = math.hypot(dx, dy)
        if length == 0:
            return
        ux, uy = dx / length, dy / length
        dash, gap = 9 * output_scale, 7 * output_scale
        cursor = 0.0
        while cursor < length:
            end = min(cursor + dash, length)
            draw.line(
                (round(x0 + ux * cursor), round(y0 + uy * cursor), round(x0 + ux * end), round(y0 + uy * end)),
                fill=hidden_color,
                width=hidden_stroke,
            )
            cursor += dash + gap

    def centered_text(text: str, x: float, y: float, vertical: bool = False) -> None:
        bbox = font.getbbox(text)
        tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
        pad = sc(4)
        if not vertical:
            cx, cy = sc(x), sc(y)
            draw.rectangle((cx - tw // 2 - pad, cy - th // 2 - pad, cx + tw // 2 + pad, cy + th // 2 + pad), fill="white")
            draw.text((cx, cy), text, font=font, fill=text_color, anchor="mm")
            return
        tile = Image.new("RGB", (tw + pad * 2, th + pad * 2), "white")
        tile_draw = ImageDraw.Draw(tile)
        tile_draw.text((tile.width // 2, tile.height // 2), text, font=font, fill=text_color, anchor="mm")
        tile = tile.rotate(90, expand=True, fillcolor="white")
        image.paste(tile, (sc(x) - tile.width // 2, sc(y) - tile.height // 2))

    def triangle(points: list[tuple[float, float]]) -> None:
        draw.polygon([(sc(x), sc(y)) for x, y in points], fill=arrow_color)

    def h_dimension(x1: int, x2: int, y: int, label: str) -> None:
        line_scaled((x1, y, x2, y), dimension_color, dimension_stroke)
        triangle([(x1, y), (x1 + 13, y - 4.6), (x1 + 13, y + 4.6)])
        triangle([(x2, y), (x2 - 13, y - 4.6), (x2 - 13, y + 4.6)])
        centered_text(label, (x1 + x2) / 2, y)

    def v_dimension(y1: int, y2: int, x: int, label: str) -> None:
        line_scaled((x, y1, x, y2), dimension_color, dimension_stroke)
        triangle([(x, y1), (x - 4.6, y1 + 13), (x + 4.6, y1 + 13)])
        triangle([(x, y2), (x - 4.6, y2 - 13), (x + 4.6, y2 - 13)])
        centered_text(label, x, (y1 + y2) / 2, vertical=True)

    for hidden_line in hidden_lines:
        dashed_line(hidden_line)
    for rectangle in rectangles:
        draw.rectangle(tuple(sc(value) for value in rectangle), outline=object_color, width=object_stroke)
    for object_line in lines:
        line_scaled(object_line, object_color, object_stroke)
    for extension in extension_lines:
        line_scaled(extension, dimension_color, dimension_stroke)
    for dimension in h_dimensions:
        h_dimension(*dimension)
    for dimension in v_dimensions:
        v_dimension(*dimension)
    image.save(path, format="PNG", optimize=True)


def top_view_data() -> tuple[int, int, list[Rect], list[Line], list[Line], list[Line], list[HDim], list[VDim]]:
    sheet_width, sheet_height = 2160, 940
    left, top = 240, 260
    rectangles: list[Rect] = [
        (left, top, left + 1800, top + 600),
        (left + 20, top + 20, left + 1780, top + 580),
        (left + 1180, top + 30, left + 1500, top + 570),
        (left + 1200, top + 50, left + 1480, top + 550),
        (left + 1480, top + 50, left + 1490, top + 550),
        (left + 1496, top + 60, left + 1504, top + 540),
    ]
    lines: list[Line] = [(left + 1150, top + 20, left + 1150, top + 580)]
    hidden: list[Line] = []
    hidden.extend(rectangle_edges((left + 40, top + 22, left + 1110, top + 550)))
    hidden.extend(rectangle_edges((left + 1130, top + 20, left + 1150, top + 580)))

    object_right, object_bottom = left + 1800, top + 600
    cavity_left, cavity_right = left + 1180, left + 1500
    split = left + 1150
    cavity_y, module_y, overall_y = 185, 120, 50
    cavity_chain_x, overall_x = 155, 60
    extensions: list[Line] = []
    extensions += [(x, top - 5, x, cavity_y - 8) for x in (cavity_left, cavity_right)]
    extensions += [(x, cavity_y - 16, x, module_y - 8) for x in (left, split, object_right)]
    extensions += [(x, module_y - 16, x, overall_y - 8) for x in (left, object_right)]
    extensions += [(left - 5, y, cavity_chain_x - 8, y) for y in (top + 30, top + 570)]
    extensions += [(cavity_chain_x - 16, y, overall_x - 8, y) for y in (top, object_bottom)]
    h_dimensions: list[HDim] = [
        (cavity_left, cavity_right, cavity_y, "320"),
        (left, split, module_y, "1150"),
        (split, object_right, module_y, "650"),
        (left, object_right, overall_y, "1800"),
    ]
    v_dimensions: list[VDim] = [
        (top + 30, top + 570, cavity_chain_x, "540"),
        (top, object_bottom, overall_x, "600"),
    ]
    return sheet_width, sheet_height, rectangles, lines, hidden, extensions, h_dimensions, v_dimensions


def left_view_data() -> tuple[int, int, list[Rect], list[Line], list[Line], list[Line], list[HDim], list[VDim]]:
    sheet_width, sheet_height = 1100, 1120
    left, top = 300, 220
    rectangles: list[Rect] = [
        (left, top, left + 600, top + 765),
        (left + 50, top + 765, left + 560, top + 800),
    ]
    lines: list[Line] = []

    def depth_height_rect(depth0: int, z0: int, depth1: int, z1: int) -> Rect:
        return (left + depth0, top + 800 - z1, left + depth1, top + 800 - z0)

    hidden: list[Line] = []
    for rectangle in (
        depth_height_rect(22, 185, 550, 315),
        depth_height_rect(22, 45, 550, 165),
        depth_height_rect(20, 620, 580, 640),
        depth_height_rect(20, 325, 580, 345),
        depth_height_rect(18, 475, 570, 485),
        depth_height_rect(570, 325, 580, 640),
    ):
        hidden.extend(rectangle_edges(rectangle))

    body_bottom, ground = top + 765, top + 800
    plinth_left, plinth_right = left + 50, left + 560
    plinth_y, depth_y = 145, 75
    chain_x, overall_x = 215, 95
    datum_y = [ground, body_bottom, top + 625, top + 475, top + 160, top]
    extensions: list[Line] = []
    extensions += [(x, top - 5, x, plinth_y - 8) for x in (plinth_left, plinth_right)]
    extensions += [(x, plinth_y - 16, x, depth_y - 8) for x in (left, left + 600)]
    extensions += [(left - 5, y, chain_x - 8, y) for y in datum_y]
    extensions += [(chain_x - 16, y, overall_x - 8, y) for y in (top, ground)]
    h_dimensions: list[HDim] = [
        (plinth_left, plinth_right, plinth_y, "510"),
        (left, left + 600, depth_y, "600"),
    ]
    vertical_labels = ["35", "140", "150", "315", "160"]
    v_dimensions: list[VDim] = [
        (datum_y[index + 1], datum_y[index], chain_x, label)
        for index, label in enumerate(vertical_labels)
    ]
    v_dimensions.append((top, ground, overall_x, "800"))
    return sheet_width, sheet_height, rectangles, lines, hidden, extensions, h_dimensions, v_dimensions


def main() -> None:
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    top = top_view_data()
    left = left_view_data()
    write_svg(TOP_SVG, top[0], top[1], "Вид сверху тумбы с размерами", *top[2:])
    write_png(TOP_PNG, top[0], top[1], 4000 / top[0], *top[2:])
    write_svg(LEFT_SVG, left[0], left[1], "Вид слева тумбы с размерами", *left[2:])
    write_png(LEFT_PNG, left[0], left[1], 4000 / left[1], *left[2:])

    with zipfile.ZipFile(BUNDLE, "w", compression=zipfile.ZIP_DEFLATED) as archive:
        for path in (TOP_PNG, TOP_SVG, LEFT_PNG, LEFT_SVG):
            archive.write(path, arcname=path.name)

    print(f"Top PNG: {TOP_PNG}")
    print(f"Top SVG: {TOP_SVG}")
    print(f"Left PNG: {LEFT_PNG}")
    print(f"Left SVG: {LEFT_SVG}")
    print(f"Bundle: {BUNDLE}")


if __name__ == "__main__":
    main()
