#!/usr/bin/env python3
"""Compose the approved cabinet views into one A3 general-arrangement sheet."""

from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageDraw, ImageFont
from reportlab.lib.pagesizes import A2, landscape
from reportlab.pdfgen import canvas as pdf_canvas
from reportlab.lib.utils import ImageReader


ROOT = Path(__file__).resolve().parents[1]
EXPORTS = ROOT / "models" / "tumba_3d_v2" / "exports"
TEMPLATE = Path(r"C:\Users\archa\OneDrive\Desktop\CarpDraw\Draw style\02.png")
TMP_DIR = ROOT / "tmp" / "pdfs"
OUTPUT_DIR = ROOT / "output" / "pdf"
PREVIEW = TMP_DIR / "display-cabinet-general-arrangement-a2-source.png"
PDF_PATH = OUTPUT_DIR / "display-cabinet-general-arrangement-a2.pdf"

SHEET_W, SHEET_H = 5700, 4028
INK = (25, 25, 25)
MID = (72, 72, 72)
LIGHT = (145, 145, 145)
GRID = (112, 112, 112)


def font(size: int, bold: bool = False) -> ImageFont.FreeTypeFont:
    name = "arialbd.ttf" if bold else "arial.ttf"
    path = Path("C:/Windows/Fonts") / name
    return ImageFont.truetype(str(path), size=size)


def place_scaled(
    sheet: Image.Image,
    source: Path,
    x: int,
    y: int,
    width: int,
    height: int,
) -> None:
    view = Image.open(source).convert("RGB")
    view = view.resize((width, height), Image.Resampling.LANCZOS)
    sheet.paste(view, (x, y))


def centered(draw: ImageDraw.ImageDraw, text: str, center_x: int, y: int, text_font: ImageFont.FreeTypeFont, fill: tuple[int, int, int] = INK) -> None:
    draw.text((center_x, y), text, font=text_font, fill=fill, anchor="ma")


def view_caption(draw: ImageDraw.ImageDraw, title: str, scale: str, center_x: int, y: int) -> None:
    centered(draw, title, center_x, y, font(43), INK)
    centered(draw, scale, center_x, y + 48, font(28), MID)


def draw_specification(draw: ImageDraw.ImageDraw) -> None:
    x, y, width = 3650, 1960, 1850
    title_h, head_h, row_h = 64, 60, 91
    columns = [110, 1540, 200]
    rows = [
        ("01", "SIDE PANELS", "LEATHER-WRAPPED MDF", "2"),
        ("02", "FRONT PANELS", "LEATHER-WRAPPED MDF", "4"),
        ("03", "CARCASS PANELS", "MDF / BRONZE FINISH", "6"),
        ("04", "NICHE SHELF", "BRONZE GLASS", "1"),
        ("05", "PLINTH", "POWDER-COATED STEEL", "1"),
        ("06", "LINEAR LIGHTING", "LED 3000 K", "2"),
        ("07", "FITTINGS SET", "SOFT-CLOSE HARDWARE", "1 SET"),
    ]
    total_h = title_h + head_h + row_h * len(rows)
    draw.rectangle((x, y, x + width, y + total_h), outline=INK, width=3)
    draw.line((x, y + title_h, x + width, y + title_h), fill=INK, width=3)
    draw.line((x, y + title_h + head_h, x + width, y + title_h + head_h), fill=INK, width=2)

    centered(draw, "SPECIFICATION", x + width // 2, y + 13, font(42), INK)
    headers = ("NO.", "COMPONENT / MATERIAL / FINISH", "QTY")
    cursor = x
    for index, col_width in enumerate(columns):
        if index:
            draw.line((cursor, y + title_h, cursor, y + total_h), fill=GRID, width=2)
        centered(draw, headers[index], cursor + col_width // 2, y + title_h + 15, font(29, bold=True), MID)
        cursor += col_width

    component_font = font(31, bold=True)
    material_font = font(27)
    item_font = font(30)
    for row_index, row in enumerate(rows):
        top = y + title_h + head_h + row_index * row_h
        if row_index:
            draw.line((x, top, x + width, top), fill=LIGHT, width=2)
        number, component, material, quantity = row
        centered(draw, number, x + columns[0] // 2, top + 29, item_font, INK)
        description_x = x + columns[0] + columns[1] // 2
        centered(draw, component, description_x, top + 13, component_font, INK)
        centered(draw, material, description_x, top + 50, material_font, MID)
        centered(draw, quantity, x + columns[0] + columns[1] + columns[2] // 2, top + 29, item_font, INK)

    note_y = y + total_h + 50
    draw.text((x, note_y), "GENERAL NOTES", font=font(32, bold=True), fill=INK)
    notes = [
        "1. ALL DIMENSIONS ARE IN MILLIMETRES.",
        "2. VERIFY SITE DIMENSIONS BEFORE FABRICATION.",
        "3. AXONOMETRIC VIEW IS NOT TO SCALE.",
    ]
    for index, note in enumerate(notes):
        draw.text((x, note_y + 50 + index * 42), note, font=font(27), fill=MID)


def draw_title_block_values(draw: ImageDraw.ImageDraw) -> None:
    value_font = font(39, bold=True)
    small_font = font(29)
    project_font = font(50, bold=True)

    draw.text((310, 3694), "GENERAL ARRANGEMENT", font=value_font, fill=INK)
    draw.text((310, 3880), "DISPLAY CABINET", font=value_font, fill=INK)

    draw.text((1498, 3702), "AURORA RESIDENCE", font=project_font, fill=INK)
    draw.text((1498, 3794), "DRAWING: AR-F-021     REV: 00", font=small_font, fill=MID)
    draw.text((1498, 3852), "DATE: 26.08.2026     SCALE: 1:10", font=small_font, fill=MID)
    draw.text((1498, 3910), "DRAWN: M. KOVACS     CHECKED: L. BROWN", font=small_font, fill=MID)

    draw.text((3305, 3694), "LIVING ROOM", font=value_font, fill=INK)
    draw.text((3305, 3880), "LEVEL 01", font=value_font, fill=INK)


def build_sheet() -> Image.Image:
    sheet = Image.open(TEMPLATE).convert("RGB")
    if sheet.size != (SHEET_W, SHEET_H):
        sheet = sheet.resize((SHEET_W, SHEET_H), Image.Resampling.LANCZOS)

    # 5700 px represent 594 mm on the A2 page; this gives an exact printed scale of 1:10.
    ortho_scale = SHEET_W / 5940
    top_size = (round(2160 * ortho_scale), round(940 * ortho_scale))
    front_size = (round(2160 * ortho_scale), round(1080 * ortho_scale))
    left_size = (round(1100 * ortho_scale), round(1120 * ortho_scale))
    axon_scale = 0.80
    axon_size = (round(2400 * axon_scale), round(2100 * axon_scale))

    # Projection grid: top/front share the same object X datums; front/left share top and ground datums.
    place_scaled(sheet, EXPORTS / "tumba-top-view-dimensioned-hidden-edges-4k.png", 231, 650, *top_size)
    place_scaled(sheet, EXPORTS / "tumba-front-facade-dimensioned-hidden-edges-4k.png", 250, 1800, *front_size)
    place_scaled(sheet, EXPORTS / "tumba-left-view-dimensioned-hidden-edges-4k.png", 2300, 1762, *left_size)
    place_scaled(sheet, EXPORTS / "tumba-axonometric-dimensioned-hidden-edges-4k.png", 2920, 160, *axon_size)

    draw = ImageDraw.Draw(sheet)
    orthographic_center = round(250 + 1120 * ortho_scale)
    side_center = round(2300 + 600 * ortho_scale)
    view_caption(draw, "TOP VIEW", "SCALE 1:10", orthographic_center, 1590)
    view_caption(draw, "FRONT VIEW", "SCALE 1:10", orthographic_center, 2890)
    view_caption(draw, "LEFT VIEW", "SCALE 1:10", side_center, 2890)
    view_caption(draw, "AXONOMETRIC VIEW", "NTS", 2920 + axon_size[0] // 2, 1870)

    draw_specification(draw)
    draw_title_block_values(draw)
    return sheet


def write_pdf(source: Image.Image) -> None:
    page_width, page_height = landscape(A2)
    pdf = pdf_canvas.Canvas(str(PDF_PATH), pagesize=(page_width, page_height), pageCompression=1)
    pdf.setTitle("Display Cabinet - General Arrangement")
    pdf.setAuthor("CarpDraw.ai")
    pdf.setSubject("Dimensioned furniture general-arrangement drawing")
    pdf.drawImage(ImageReader(source), 0, 0, width=page_width, height=page_height, preserveAspectRatio=False, mask="auto")
    pdf.showPage()
    pdf.save()


def main() -> None:
    TMP_DIR.mkdir(parents=True, exist_ok=True)
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    sheet = build_sheet()
    sheet.save(PREVIEW, format="PNG", optimize=True)
    write_pdf(sheet)
    print(f"Preview source: {PREVIEW}")
    print(f"PDF: {PDF_PATH}")


if __name__ == "__main__":
    main()
