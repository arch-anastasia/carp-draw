"""Reusable white technical furniture style for SVG and Pillow exports.

Geometry and dimension values are intentionally not included. Supply the bounds
and dimensions of each new furniture item while reusing these visual settings.
"""

from __future__ import annotations

import math
from pathlib import Path
from typing import Any


STYLE: dict[str, Any] = {
    "background": "#ffffff",
    "surface": {"color": "#ffffff", "metallic": 0.0, "roughness": 1.0, "emissive": (0.1, 0.1, 0.1)},
    "object": {"color": "#111111", "width": 1.8},
    "hidden": {"color": "#888888", "width": 1.05, "dash": (9.0, 7.0)},
    "dimension": {
        "line_color": "#777777",
        "line_width": 0.9,
        "text_color": "#333333",
        "arrow_color": "#555555",
        "font_size": 34,
        "arrow_length": 13.0,
        "arrow_wing": 4.6,
        "mask_width": 88.0,
        "mask_height": 40.0,
    },
    "layout": {
        "preferred_sides": ("top", "left"),
        "small_near_object": True,
        "overall_farther": True,
        "deduplicate": True,
        "outside_contour": True,
        "axis_aligned": True,
        "avoid_intersections": True,
        "group_by_element": True,
    },
    "shadows": {
        "enabled": False,
        "broad": {"offset": (19, 22), "blur": 13, "opacity": 0.17},
        "contact": {"offset": (7, 9), "blur": 4, "opacity": 0.10},
    },
    "max_raster_dimension": 4000,
}


def svg_style_block() -> str:
    """Return the SVG CSS block used by all approved technical exports."""
    obj = STYLE["object"]
    hidden = STYLE["hidden"]
    dim = STYLE["dimension"]
    return f"""<style>
.object{{fill:none;stroke:{obj['color']};stroke-width:{obj['width']};stroke-linecap:square;stroke-linejoin:miter;vector-effect:non-scaling-stroke}}
.hidden{{fill:none;stroke:{hidden['color']};stroke-width:{hidden['width']};stroke-dasharray:{hidden['dash'][0]} {hidden['dash'][1]};stroke-linecap:butt;stroke-linejoin:miter;vector-effect:non-scaling-stroke}}
.dimension{{fill:{dim['arrow_color']};stroke:{dim['line_color']};stroke-width:{dim['line_width']};stroke-linecap:square;stroke-linejoin:miter;vector-effect:non-scaling-stroke}}
.dimension text{{fill:{dim['text_color']};stroke:none;font:500 {dim['font_size']}px Arial,sans-serif;text-anchor:middle;dominant-baseline:central}}
.dimension .mask{{fill:{STYLE['background']};stroke:none}}
</style>"""


def svg_arrow(x: float, y: float, base_x: float, base_y: float) -> str:
    """Return one filled SVG dimension-arrow path."""
    wing = STYLE["dimension"]["arrow_wing"]
    dx, dy = base_x - x, base_y - y
    length = math.hypot(dx, dy) or 1.0
    px, py = -dy / length * wing, dx / length * wing
    return f'<path d="M {x} {y} L {base_x + px} {base_y + py} L {base_x - px} {base_y - py} Z"/>'


def svg_horizontal_dimension(x1: float, x2: float, y: float, label: str) -> str:
    """Return a horizontal SVG dimension with inward-facing arrows."""
    dim = STYLE["dimension"]
    midpoint = (x1 + x2) / 2
    half_w, half_h = dim["mask_width"] / 2, dim["mask_height"] / 2
    arrow = dim["arrow_length"]
    return "".join(
        (
            f'<line x1="{x1}" y1="{y}" x2="{x2}" y2="{y}"/>',
            svg_arrow(x1, y, x1 + arrow, y),
            svg_arrow(x2, y, x2 - arrow, y),
            f'<rect class="mask" x="{midpoint - half_w}" y="{y - half_h}" width="{dim["mask_width"]}" height="{dim["mask_height"]}"/>',
            f'<text x="{midpoint}" y="{y}">{label}</text>',
        )
    )


def svg_vertical_dimension(y1: float, y2: float, x: float, label: str) -> str:
    """Return a vertical SVG dimension with rotated text."""
    dim = STYLE["dimension"]
    midpoint = (y1 + y2) / 2
    half_w, half_h = dim["mask_height"] / 2, dim["mask_width"] / 2
    arrow = dim["arrow_length"]
    return "".join(
        (
            f'<line x1="{x}" y1="{y1}" x2="{x}" y2="{y2}"/>',
            svg_arrow(x, y1, x, y1 + arrow),
            svg_arrow(x, y2, x, y2 - arrow),
            f'<rect class="mask" x="{x - half_w}" y="{midpoint - half_h}" width="{dim["mask_height"]}" height="{dim["mask_width"]}"/>',
            f'<text x="{x}" y="{midpoint}" transform="rotate(-90 {x} {midpoint})">{label}</text>',
        )
    )


def get_font(size: int):
    """Load the same Arial/Segoe UI font used by the current exports."""
    from PIL import ImageFont

    for candidate in (Path("C:/Windows/Fonts/arial.ttf"), Path("C:/Windows/Fonts/segoeui.ttf")):
        if candidate.exists():
            return ImageFont.truetype(str(candidate), size=size)
    return ImageFont.load_default()


def draw_dashed_line(draw, line: tuple[float, float, float, float], scale: float = 1.0) -> None:
    """Draw one Pillow hidden edge using the approved dash and hierarchy."""
    hidden = STYLE["hidden"]
    x0, y0, x1, y1 = (value * scale for value in line)
    dx, dy = x1 - x0, y1 - y0
    length = math.hypot(dx, dy)
    if length == 0:
        return
    ux, uy = dx / length, dy / length
    dash, gap = hidden["dash"][0] * scale, hidden["dash"][1] * scale
    cursor = 0.0
    while cursor < length:
        end = min(cursor + dash, length)
        draw.line(
            (round(x0 + ux * cursor), round(y0 + uy * cursor), round(x0 + ux * end), round(y0 + uy * end)),
            fill=hidden["color"],
            width=max(1, round(hidden["width"] * scale)),
        )
        cursor += dash + gap


DIMENSION_RULES = (
    "Place dimensions outside the furniture contour whenever possible.",
    "Use each dimension once.",
    "Place small/detail dimensions closer to the object and overall dimensions farther away.",
    "Keep dimensions axis-aligned except for genuinely inclined geometry.",
    "Group dimensions that describe the same construction element.",
    "Tie every dimension to a specific modeled detail.",
    "Use only the top and left sides whenever the view allows it.",
    "Avoid crossings between object, extension, and dimension lines.",
)
