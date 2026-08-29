#!/usr/bin/env python3
"""Export the corrected cabinet facade as clean orthographic SVG and PNG."""

from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter, ImageFont


ROOT = Path(__file__).resolve().parents[1]
OUTPUT_DIR = ROOT / "models" / "tumba_3d_v2" / "exports"
SVG_PATH = OUTPUT_DIR / "tumba-front-facade.svg"
PNG_PATH = OUTPUT_DIR / "tumba-front-facade-4k.png"
DIMENSIONED_SVG_PATH = OUTPUT_DIR / "tumba-front-facade-dimensioned.svg"
DIMENSIONED_PNG_PATH = OUTPUT_DIR / "tumba-front-facade-dimensioned-4k.png"
HIDDEN_SVG_PATH = OUTPUT_DIR / "tumba-front-facade-dimensioned-hidden-edges.svg"
HIDDEN_PNG_PATH = OUTPUT_DIR / "tumba-front-facade-dimensioned-hidden-edges-4k.png"
SHADOWED_SVG_PATH = OUTPUT_DIR / "tumba-front-facade-dimensioned-hidden-edges-shadowed.svg"
SHADOWED_PNG_PATH = OUTPUT_DIR / "tumba-front-facade-dimensioned-hidden-edges-shadowed-4k.png"

# Drawing coordinates preserve the 1:1 millimetre proportions of the visible
# 1800 x 800 mm envelope.  The 80 mm margin is outside the furniture bounds.
WIDTH, HEIGHT = 1960, 960
MARGIN_X, TOP = 80, 80

RECTANGLES = [
    (80, 80, 1880, 845),   # body: Z 35..800
    (140, 845, 1820, 880), # recessed 35 mm plinth
]

LINES = [
    (100, 80, 100, 845),       # 20 mm left side panel
    (1210, 80, 1210, 845),     # left edge of 20 mm module divider
    (1230, 80, 1230, 845),     # 1150 mm module seam / right door edge
    (100, 240, 1230, 240),     # bottom of upper front panel, Z=640
    (110, 252, 1200, 252),     # upper metallic trim
    (110, 260, 1200, 260),     # niche ceiling edge, Z=620
    (110, 395, 1200, 395),     # shelf top, Z=485
    (110, 405, 1200, 405),     # shelf bottom, Z=475
    (100, 535, 1210, 535),     # niche floor top, Z=345
    (100, 555, 1210, 555),     # niche floor bottom, Z=325
    (100, 705, 1210, 705),     # lower drawer division, Z=175
]


def write_svg() -> None:
    rects = "\n".join(
        f'    <rect x="{x0}" y="{y0}" width="{x1 - x0}" height="{y1 - y0}"/>'
        for x0, y0, x1, y1 in RECTANGLES
    )
    lines = "\n".join(
        f'    <line x1="{x0}" y1="{y0}" x2="{x1}" y2="{y1}"/>'
        for x0, y0, x1, y1 in LINES
    )
    svg = f'''<svg xmlns="http://www.w3.org/2000/svg" width="{WIDTH}" height="{HEIGHT}" viewBox="0 0 {WIDTH} {HEIGHT}">
  <title>Ортографический фасад тумбы</title>
  <desc>Чистый линейный фасад без размеров и надписей. Габарит мебели 1800 на 800 миллиметров.</desc>
  <rect width="{WIDTH}" height="{HEIGHT}" fill="#fff"/>
  <g fill="none" stroke="#111" stroke-width="2" stroke-linecap="square" stroke-linejoin="miter" vector-effect="non-scaling-stroke">
{rects}
{lines}
  </g>
</svg>
'''
    SVG_PATH.write_text(svg, encoding="utf-8")


def write_png() -> None:
    output_width = 4000
    scale = output_width / WIDTH
    output_height = round(HEIGHT * scale)
    image = Image.new("RGB", (output_width, output_height), "white")
    draw = ImageDraw.Draw(image)
    stroke = max(1, round(1.45 * scale))

    def scaled(values: tuple[int, int, int, int]) -> tuple[int, int, int, int]:
        return tuple(round(value * scale) for value in values)  # type: ignore[return-value]

    for rectangle in RECTANGLES:
        draw.rectangle(scaled(rectangle), outline=(17, 17, 17), width=stroke)
    for line in LINES:
        draw.line(scaled(line), fill=(17, 17, 17), width=stroke)

    image.save(PNG_PATH, format="PNG", optimize=True)


def dimensioned_geometry() -> tuple[list[tuple[int, int, int, int]], list[tuple[int, int, int, int]]]:
    """Shift the 1:1 furniture geometry into a sheet with drawing margins."""
    dx, dy = 140, 100
    rectangles = [(x0 + dx, y0 + dy, x1 + dx, y1 + dy) for x0, y0, x1, y1 in RECTANGLES]
    lines = [(x0 + dx, y0 + dy, x1 + dx, y1 + dy) for x0, y0, x1, y1 in LINES]
    return rectangles, lines


def hidden_front_lines() -> list[tuple[int, int, int, int]]:
    """Return selected internal edges that are obscured in the frontal projection."""
    object_left, ground_y = 220, 980

    def line(x0: int, z0: int, x1: int, z1: int) -> tuple[int, int, int, int]:
        return (object_left + x0, ground_y - z0, object_left + x1, ground_y - z1)

    def rectangle(x0: int, z0: int, x1: int, z1: int) -> list[tuple[int, int, int, int]]:
        return [
            line(x0, z0, x1, z0),
            line(x1, z0, x1, z1),
            line(x1, z1, x0, z1),
            line(x0, z1, x0, z0),
        ]

    hidden: list[tuple[int, int, int, int]] = []
    hidden.extend(rectangle(40, 185, 1110, 315))   # upper drawer box
    hidden.extend(rectangle(40, 45, 1110, 165))    # lower drawer box
    hidden.extend(rectangle(20, 35, 1130, 55))     # main bottom panel
    hidden.extend(rectangle(1150, 35, 1780, 55))   # right bottom panel
    hidden.extend(rectangle(1180, 690, 1500, 710)) # recessed top floor
    hidden.extend(rectangle(1180, 710, 1500, 780)) # recessed top walls
    hidden.append(line(1200, 710, 1200, 780))
    hidden.append(line(1480, 710, 1480, 780))
    hidden.extend(rectangle(1478, 738, 1500, 766)) # longitudinal guide assembly
    return hidden


def svg_arrow(x: float, y: float, base_x: float, base_y: float) -> str:
    dx, dy = base_x - x, base_y - y
    length = (dx * dx + dy * dy) ** 0.5 or 1.0
    px, py = -dy / length * 4.6, dx / length * 4.6
    return f'<path d="M {x} {y} L {base_x + px} {base_y + py} L {base_x - px} {base_y - py} Z"/>'


def svg_h_dimension(x1: int, x2: int, y: int, label: str) -> str:
    return "\n".join(
        [
            f'<line x1="{x1}" y1="{y}" x2="{x2}" y2="{y}"/>',
            svg_arrow(x1, y, x1 + 13, y),
            svg_arrow(x2, y, x2 - 13, y),
            f'<rect class="mask" x="{(x1 + x2) / 2 - 44}" y="{y - 20}" width="88" height="40"/>',
            f'<text x="{(x1 + x2) / 2}" y="{y}">{label}</text>',
        ]
    )


def svg_v_dimension(y1: int, y2: int, x: int, label: str) -> str:
    midpoint = (y1 + y2) / 2
    return "\n".join(
        [
            f'<line x1="{x}" y1="{y1}" x2="{x}" y2="{y2}"/>',
            svg_arrow(x, y1, x, y1 + 13),
            svg_arrow(x, y2, x, y2 - 13),
            f'<rect class="mask" x="{x - 20}" y="{midpoint - 44}" width="40" height="88"/>',
            f'<text x="{x}" y="{midpoint}" transform="rotate(-90 {x} {midpoint})">{label}</text>',
        ]
    )


def write_dimensioned_svg(
    path: Path = DIMENSIONED_SVG_PATH,
    include_hidden: bool = False,
    include_shadow: bool = False,
) -> None:
    sheet_width, sheet_height = 2160, 1080
    rectangles, lines = dimensioned_geometry()
    rects_svg = "\n".join(
        f'<rect x="{x0}" y="{y0}" width="{x1 - x0}" height="{y1 - y0}"/>'
        for x0, y0, x1, y1 in rectangles
    )
    lines_svg = "\n".join(
        f'<line x1="{x0}" y1="{y0}" x2="{x1}" y2="{y1}"/>'
        for x0, y0, x1, y1 in lines
    )

    left, split, right = 220, 1370, 2020
    top, bottom = 180, 980
    top_chain_y, top_overall_y = 112, 45
    left_chain_x, left_overall_x = 135, 45
    datum_y = [980, 945, 805, 655, 340, 180]
    vertical_labels = ["35", "140", "150", "315", "160"]

    extensions = [
        f'<line x1="{x}" y1="{top - 5}" x2="{x}" y2="{top_chain_y - 8}"/>'
        for x in (left, split, right)
    ]
    extensions += [
        f'<line x1="{x}" y1="{top_chain_y - 16}" x2="{x}" y2="{top_overall_y - 8}"/>'
        for x in (left, right)
    ]
    extensions += [
        f'<line x1="{left - 5}" y1="{y}" x2="{left_chain_x - 8}" y2="{y}"/>'
        for y in datum_y
    ]
    extensions += [
        f'<line x1="{left_chain_x - 16}" y1="{y}" x2="{left_overall_x - 8}" y2="{y}"/>'
        for y in (top, bottom)
    ]
    dimensions = [
        svg_h_dimension(left, split, top_chain_y, "1150"),
        svg_h_dimension(split, right, top_chain_y, "650"),
        svg_h_dimension(left, right, top_overall_y, "1800"),
        svg_v_dimension(top, bottom, left_overall_x, "800"),
    ]
    for index, label in enumerate(vertical_labels):
        dimensions.append(svg_v_dimension(datum_y[index + 1], datum_y[index], left_chain_x, label))

    hidden_svg = ""
    if include_hidden:
        hidden_svg = "".join(
            f'<line x1="{x0}" y1="{y0}" x2="{x1}" y2="{y1}"/>'
            for x0, y0, x1, y1 in hidden_front_lines()
        )
    shadow_svg = ""
    shadow_mask_svg = ""
    if include_shadow:
        shadow_shapes = '<rect x="220" y="180" width="1800" height="765"/><rect x="280" y="945" width="1680" height="35"/>'
        shadow_svg = (
            f'<g class="shadow broad" transform="translate(19 22)">{shadow_shapes}</g>'
            f'<g class="shadow contact" transform="translate(7 9)">{shadow_shapes}</g>'
        )
        shadow_mask_svg = '<g fill="#fff"><rect x="220" y="180" width="1800" height="765"/><rect x="280" y="945" width="1680" height="35"/></g>'

    svg = f'''<svg xmlns="http://www.w3.org/2000/svg" width="{sheet_width}" height="{sheet_height}" viewBox="0 0 {sheet_width} {sheet_height}">
  <title>Фасад тумбы с профессиональной размерной схемой</title>
  <desc>Ортографический фасад 1800 на 800 миллиметров. Размеры указаны в миллиметрах.</desc>
  <defs><filter id="shadowBroad" x="-20%" y="-20%" width="150%" height="150%"><feGaussianBlur stdDeviation="13"/></filter><filter id="shadowContact" x="-20%" y="-20%" width="150%" height="150%"><feGaussianBlur stdDeviation="4"/></filter></defs>
  <style>
    .object{{fill:none;stroke:#111;stroke-width:1.8;stroke-linecap:square;stroke-linejoin:miter;vector-effect:non-scaling-stroke}}
    .dimension{{fill:#555;stroke:#777;stroke-width:.9;stroke-linecap:square;stroke-linejoin:miter;vector-effect:non-scaling-stroke}}
    .dimension text{{fill:#333;stroke:none;font:500 34px Arial,sans-serif;text-anchor:middle;dominant-baseline:central}}
    .dimension .mask{{fill:#fff;stroke:none}}
    .hidden{{fill:none;stroke:#888;stroke-width:1.05;stroke-dasharray:9 7;stroke-linecap:butt;stroke-linejoin:miter;vector-effect:non-scaling-stroke}}
    .shadow{{fill:#333;stroke:none}}.shadow.broad{{opacity:.17;filter:url(#shadowBroad)}}.shadow.contact{{opacity:.10;filter:url(#shadowContact)}}
  </style>
  <rect width="{sheet_width}" height="{sheet_height}" fill="#fff"/>
  {shadow_svg}{shadow_mask_svg}
  <g class="hidden">{hidden_svg}</g>
  <g class="object">{rects_svg}{lines_svg}</g>
  <g class="dimension">{''.join(extensions)}{''.join(dimensions)}</g>
</svg>
'''
    path.write_text(svg, encoding="utf-8")


def get_font(size: int) -> ImageFont.FreeTypeFont | ImageFont.ImageFont:
    for candidate in (Path("C:/Windows/Fonts/arial.ttf"), Path("C:/Windows/Fonts/segoeui.ttf")):
        if candidate.exists():
            return ImageFont.truetype(str(candidate), size=size)
    return ImageFont.load_default()


def write_dimensioned_png(
    path: Path = DIMENSIONED_PNG_PATH,
    include_hidden: bool = False,
    include_shadow: bool = False,
) -> None:
    sheet_width, sheet_height = 2160, 1080
    output_width = 4000
    scale = output_width / sheet_width
    output_height = round(sheet_height * scale)
    image = Image.new("RGBA", (output_width, output_height), "white")
    draw = ImageDraw.Draw(image)
    rectangles, lines = dimensioned_geometry()
    object_stroke = max(2, round(1.8 * scale))
    dimension_stroke = max(1, round(0.9 * scale))
    font = get_font(round(34 * scale))
    dimension_color = (112, 112, 112)
    arrow_color = (82, 82, 82)
    text_color = (48, 48, 48)

    def sc(value: float) -> int:
        return round(value * scale)

    def sc4(values: tuple[int, int, int, int]) -> tuple[int, int, int, int]:
        return tuple(sc(value) for value in values)  # type: ignore[return-value]

    def centered_text(text: str, x: float, y: float, vertical: bool = False) -> None:
        bbox = font.getbbox(text)
        tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
        pad = sc(4)
        if not vertical:
            cx, cy = sc(x), sc(y)
            draw.rectangle((cx - tw // 2 - pad, cy - th // 2 - pad, cx + tw // 2 + pad, cy + th // 2 + pad), fill="white")
            draw.text((cx, cy), text, font=font, fill=text_color, anchor="mm")
            return
        tile = Image.new("RGBA", (tw + pad * 2, th + pad * 2), "white")
        td = ImageDraw.Draw(tile)
        td.text((tile.width // 2, tile.height // 2), text, font=font, fill=text_color, anchor="mm")
        tile = tile.rotate(90, expand=True, fillcolor="white")
        image.paste(tile, (sc(x) - tile.width // 2, sc(y) - tile.height // 2))

    def triangle(points: list[tuple[float, float]]) -> None:
        draw.polygon([(sc(x), sc(y)) for x, y in points], fill=arrow_color)

    def dashed_line(values: tuple[int, int, int, int]) -> None:
        x0, y0, x1, y1 = (value * scale for value in values)
        dx, dy = x1 - x0, y1 - y0
        length = (dx * dx + dy * dy) ** 0.5
        if length == 0:
            return
        ux, uy = dx / length, dy / length
        dash, gap = 9 * scale, 7 * scale
        cursor = 0.0
        while cursor < length:
            end = min(cursor + dash, length)
            draw.line(
                (round(x0 + ux * cursor), round(y0 + uy * cursor), round(x0 + ux * end), round(y0 + uy * end)),
                fill=(136, 136, 136),
                width=max(1, round(1.05 * scale)),
            )
            cursor += dash + gap

    def h_dimension(x1: int, x2: int, y: int, label: str) -> None:
        draw.line((sc(x1), sc(y), sc(x2), sc(y)), fill=dimension_color, width=dimension_stroke)
        triangle([(x1, y), (x1 + 13, y - 4.6), (x1 + 13, y + 4.6)])
        triangle([(x2, y), (x2 - 13, y - 4.6), (x2 - 13, y + 4.6)])
        centered_text(label, (x1 + x2) / 2, y)

    def v_dimension(y1: int, y2: int, x: int, label: str) -> None:
        draw.line((sc(x), sc(y1), sc(x), sc(y2)), fill=dimension_color, width=dimension_stroke)
        triangle([(x, y1), (x - 4.6, y1 + 13), (x + 4.6, y1 + 13)])
        triangle([(x, y2), (x - 4.6, y2 - 13), (x + 4.6, y2 - 13)])
        centered_text(label, x, (y1 + y2) / 2, vertical=True)

    if include_shadow:
        def add_shadow(offset_x: int, offset_y: int, blur: int, alpha: int) -> None:
            layer = Image.new("RGBA", image.size, (0, 0, 0, 0))
            layer_draw = ImageDraw.Draw(layer)
            for rectangle in rectangles[:2]:
                x0, y0, x1, y1 = rectangle
                layer_draw.rectangle(
                    (sc(x0 + offset_x), sc(y0 + offset_y), sc(x1 + offset_x), sc(y1 + offset_y)),
                    fill=(45, 45, 45, alpha),
                )
            layer = layer.filter(ImageFilter.GaussianBlur(radius=blur * scale))
            image.alpha_composite(layer)

        add_shadow(19, 22, 13, 44)
        add_shadow(7, 9, 4, 26)
        draw = ImageDraw.Draw(image)
        for rectangle in rectangles[:2]:
            draw.rectangle(sc4(rectangle), fill="white")

    if include_hidden:
        for hidden_line in hidden_front_lines():
            dashed_line(hidden_line)

    for rectangle in rectangles:
        draw.rectangle(sc4(rectangle), outline=(17, 17, 17), width=object_stroke)
    for line in lines:
        draw.line(sc4(line), fill=(17, 17, 17), width=object_stroke)

    left, split, right = 220, 1370, 2020
    top, bottom = 180, 980
    top_chain_y, top_overall_y = 112, 45
    left_chain_x, left_overall_x = 135, 45
    datum_y = [980, 945, 805, 655, 340, 180]
    vertical_labels = ["35", "140", "150", "315", "160"]
    extension_color = dimension_color

    for x in (left, split, right):
        draw.line((sc(x), sc(top - 5), sc(x), sc(top_chain_y - 8)), fill=extension_color, width=dimension_stroke)
    for x in (left, right):
        draw.line((sc(x), sc(top_chain_y - 16), sc(x), sc(top_overall_y - 8)), fill=extension_color, width=dimension_stroke)
    for y in datum_y:
        draw.line((sc(left - 5), sc(y), sc(left_chain_x - 8), sc(y)), fill=extension_color, width=dimension_stroke)
    for y in (top, bottom):
        draw.line((sc(left_chain_x - 16), sc(y), sc(left_overall_x - 8), sc(y)), fill=extension_color, width=dimension_stroke)

    h_dimension(left, split, top_chain_y, "1150")
    h_dimension(split, right, top_chain_y, "650")
    h_dimension(left, right, top_overall_y, "1800")
    v_dimension(top, bottom, left_overall_x, "800")
    for index, label in enumerate(vertical_labels):
        v_dimension(datum_y[index + 1], datum_y[index], left_chain_x, label)

    image.convert("RGB").save(path, format="PNG", optimize=True)


def main() -> None:
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    write_svg()
    write_png()
    write_dimensioned_svg()
    write_dimensioned_png()
    write_dimensioned_svg(HIDDEN_SVG_PATH, include_hidden=True)
    write_dimensioned_png(HIDDEN_PNG_PATH, include_hidden=True)
    write_dimensioned_svg(SHADOWED_SVG_PATH, include_hidden=True, include_shadow=True)
    write_dimensioned_png(SHADOWED_PNG_PATH, include_hidden=True, include_shadow=True)
    print(f"SVG: {SVG_PATH}")
    print(f"PNG: {PNG_PATH}")
    print(f"Dimensioned SVG: {DIMENSIONED_SVG_PATH}")
    print(f"Dimensioned PNG: {DIMENSIONED_PNG_PATH}")
    print(f"Hidden-edge SVG: {HIDDEN_SVG_PATH}")
    print(f"Hidden-edge PNG: {HIDDEN_PNG_PATH}")
    print(f"Shadowed SVG: {SHADOWED_SVG_PATH}")
    print(f"Shadowed PNG: {SHADOWED_PNG_PATH}")


if __name__ == "__main__":
    main()
