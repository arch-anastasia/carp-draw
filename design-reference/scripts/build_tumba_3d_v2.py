#!/usr/bin/env python3
"""Build the corrected 1:1 cabinet model from the latest approved drawings.

The geometry is stored in millimetres with X = width, Y = depth (front at 0),
and Z = height.  Export helpers from ``build_tumba_3d.py`` write GLB/OBJ in
metres and OpenSCAD in millimetres.
"""

from __future__ import annotations

import json
import sys
import zipfile
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(Path(__file__).resolve().parent))

import build_tumba_3d as base  # noqa: E402


OUTPUT_DIR = ROOT / "models" / "tumba_3d_v2"
BUNDLE_PATH = ROOT / "models" / "tumba_3d_v2_bundle.zip"

MATERIALS: dict[str, base.Material] = {
    "BrownLeather": base.Material("BrownLeather", (0.985, 0.985, 0.985), 0.0, 1.0, emissive=(0.10, 0.10, 0.10)),
    "BrownEdge": base.Material("BrownEdge", (0.985, 0.985, 0.985), 0.0, 1.0, emissive=(0.10, 0.10, 0.10)),
    "InteriorBronze": base.Material("InteriorBronze", (0.985, 0.985, 0.985), 0.0, 1.0, emissive=(0.10, 0.10, 0.10)),
    "DarkInterior": base.Material("DarkInterior", (0.985, 0.985, 0.985), 0.0, 1.0, emissive=(0.10, 0.10, 0.10)),
    "BronzeMetal": base.Material("BronzeMetal", (0.985, 0.985, 0.985), 0.0, 1.0, emissive=(0.10, 0.10, 0.10)),
    "WarmLight": base.Material(
        "WarmLight",
        (0.985, 0.985, 0.985),
        0.0,
        1.0,
        emissive=(0.10, 0.10, 0.10),
    ),
    "LineDark": base.Material("LineDark", (0.02, 0.02, 0.02), 0.0, 1.0),
}


def add_box(
    parts: list[base.Part],
    name: str,
    x0: float,
    y0: float,
    z0: float,
    x1: float,
    y1: float,
    z1: float,
    material: str,
) -> None:
    part = base.Part(name, (x0, y0, z0), (x1, y1, z1), material)
    if any(size <= 0 for size in part.size_mm):
        raise ValueError(f"Invalid dimensions for {name}: {part.size_mm}")
    if material not in MATERIALS:
        raise KeyError(f"Unknown material {material!r}")
    parts.append(part)


def build_parts() -> list[base.Part]:
    """Create the corrected visible construction at full 1:1 scale."""
    p: list[base.Part] = []

    # Confirmed overall envelope: 1800 x 600 x 800 mm.  The plinth is recessed.
    add_box(p, "plinth", 60, 50, 0, 1740, 560, 35, "BrownEdge")

    # 20 mm carcass.  Latest orthographic projection fixes the module seam at
    # X=1150: 1150 mm left/display module and 650 mm right/full-height module.
    add_box(p, "left_side_panel", 0, 0, 35, 20, 600, 800, "BrownLeather")
    add_box(p, "right_side_panel", 1780, 0, 35, 1800, 600, 800, "BrownLeather")
    add_box(p, "rear_panel", 20, 580, 35, 1780, 600, 780, "DarkInterior")
    add_box(p, "module_divider", 1130, 20, 35, 1150, 580, 780, "BrownEdge")
    add_box(p, "main_bottom_panel", 20, 20, 35, 1130, 580, 55, "BrownEdge")
    add_box(p, "right_bottom_panel", 1150, 20, 35, 1780, 580, 55, "BrownEdge")

    # Flush front composition copied from the front elevation.  Four-millimetre
    # horizontal reveals keep the two handleless lower fronts distinct.
    add_box(p, "upper_front_panel", 20, 0, 640, 1130, 20, 800, "BrownLeather")
    add_box(p, "upper_drawer_front", 20, 0, 177, 1130, 20, 323, "BrownLeather")
    add_box(p, "lower_drawer_front", 20, 0, 37, 1130, 20, 173, "BrownLeather")
    add_box(p, "right_full_height_door", 1150, 0, 35, 1780, 20, 800, "BrownLeather")

    # Simplified hidden drawer volumes make the exchange model watertight and
    # understandable from oblique/rear viewpoints without inventing hardware.
    add_box(p, "upper_drawer_box", 40, 22, 185, 1110, 550, 315, "DarkInterior")
    add_box(p, "lower_drawer_box", 40, 22, 45, 1110, 550, 165, "DarkInterior")

    # Open display niche: 315 mm clear visual band, bronze lining, central shelf,
    # narrow metallic leading edges and the concealed warm light under the top.
    add_box(p, "niche_ceiling", 20, 20, 620, 1130, 580, 640, "InteriorBronze")
    add_box(p, "niche_floor", 20, 20, 325, 1130, 580, 345, "InteriorBronze")
    add_box(p, "niche_back_lining", 20, 570, 325, 1130, 580, 640, "InteriorBronze")
    add_box(p, "niche_shelf", 30, 18, 475, 1120, 570, 485, "InteriorBronze")
    add_box(p, "niche_shelf_front_edge", 30, 12, 475, 1120, 22, 485, "BronzeMetal")
    add_box(p, "niche_upper_trim", 30, 12, 622, 1120, 24, 632, "BronzeMetal")
    add_box(p, "niche_light_strip", 40, 20, 624, 1110, 26, 629, "WarmLight")

    # Continuous 20 mm external top rim and the two solid top fields.
    add_box(p, "top_front_rim", 0, 0, 780, 1800, 20, 800, "BrownEdge")
    add_box(p, "top_back_rim", 0, 580, 780, 1800, 600, 800, "BrownEdge")
    add_box(p, "top_left_rim", 0, 20, 780, 20, 580, 800, "BrownEdge")
    add_box(p, "top_right_rim", 1780, 20, 780, 1800, 580, 800, "BrownEdge")
    add_box(p, "main_top_surface", 20, 20, 780, 1150, 580, 798, "BrownLeather")
    add_box(p, "right_top_surface", 1500, 20, 780, 1780, 580, 798, "BrownLeather")

    # Corrected top recess from the latest top view: 320 x 540 mm outside,
    # 280 x 500 mm inner floor.  It sits immediately right of the module seam.
    add_box(p, "cavity_left_rim", 1150, 20, 780, 1180, 580, 798, "BrownLeather")
    add_box(p, "cavity_front_rim", 1180, 20, 780, 1500, 30, 798, "BrownLeather")
    add_box(p, "cavity_back_rim", 1180, 570, 780, 1500, 580, 798, "BrownLeather")
    add_box(p, "cavity_floor", 1180, 30, 690, 1500, 570, 710, "InteriorBronze")
    add_box(p, "cavity_left_wall", 1180, 30, 710, 1200, 570, 780, "InteriorBronze")
    add_box(p, "cavity_right_wall", 1480, 30, 710, 1500, 570, 780, "InteriorBronze")
    add_box(p, "cavity_front_wall", 1200, 30, 710, 1480, 50, 780, "InteriorBronze")
    add_box(p, "cavity_back_wall", 1200, 550, 710, 1480, 570, 780, "InteriorBronze")

    # The narrow guide and luminous strip run front-to-back along the right wall;
    # this orientation is the key correction established by the top projection.
    add_box(p, "cavity_longitudinal_guide", 1478, 50, 752, 1488, 550, 766, "BronzeMetal")
    add_box(p, "cavity_inner_rail", 1488, 50, 738, 1496, 550, 752, "BronzeMetal")
    add_box(p, "cavity_light_bar", 1492, 60, 754, 1500, 540, 762, "WarmLight")

    return p


def write_manifest(path: Path, parts: list[base.Part]) -> None:
    manifest = {
        "model": "Tumba cabinet — corrected reconstruction v2",
        "units": "millimetres",
        "overallEnvelope": {"width": 1800, "depth": 600, "height": 800},
        "documentedDimensions": {
            "mainModuleWidth": 1150,
            "rightModuleWidth": 650,
            "panelThickness": 20,
            "upperPanelHeight": 160,
            "openNicheVisualBand": 315,
            "upperDrawerFrontHeight": 146,
            "lowerDrawerFrontHeight": 136,
            "plinthHeight": 35,
            "plinthSideInset": 60,
            "shelfThickness": 10,
            "topRecessOuterWidth": 320,
            "topRecessOuterDepth": 540,
            "topRecessInnerWidth": 280,
            "topRecessInnerDepth": 500,
        },
        "sources": [
            "Изображение Codex 21 авг. 2026 г., 20_51_08.png",
            "codex-clipboard-97a97fde-9ed8-4ac8-8627-7460992e7f1c.png",
            "codex-clipboard-ff45843a-00ae-4c45-8c66-516144fddf12.png",
            "tumba-orthographic-three-views-no-labels.svg",
        ],
        "geometryNotes": [
            "The 1150/650 mm module split is derived from the corrected orthographic projection.",
            "The upper recessed guide/light assembly runs along depth, not across the cavity.",
            "No handles are modeled because none are visible in the supplied references.",
        ],
        "assumptions": [
            "Overall envelope and visible orthographic proportions are treated as authoritative.",
            "Hidden drawer construction, hinges, runners, rear build-up, and manufacturing clearances are inferred or omitted.",
            "The model is suitable for visualization and dimensional development; hidden construction requires fabrication drawings.",
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
        """# Tumba 3D model — corrected reconstruction v2

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
""",
        encoding="utf-8",
    )


def write_interactive_preview(path: Path, parts: list[base.Part]) -> None:
    """Write a dependency-free orbiting 3D preview that also works from file://."""
    payload = [
        {
            "name": part.name,
            "material": part.material,
            "min": list(part.minimum_mm),
            "max": list(part.maximum_mm),
        }
        for part in parts
    ]
    template = r'''<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Тумба — интерактивный 3D-предпросмотр</title>
<style>
  :root{color-scheme:light;--ink:#111;--muted:#666;--paper:#fff;--panel:rgba(255,255,255,.96);--accent:#111}
  *{box-sizing:border-box} html,body{width:100%;height:100%;margin:0;overflow:hidden;background:var(--paper);font-family:Inter,Segoe UI,Arial,sans-serif;color:var(--ink)}
  canvas{display:block;width:100%;height:100%;cursor:grab;touch-action:none} canvas.dragging{cursor:grabbing}
  .panel{position:fixed;z-index:2;background:var(--panel);border:1px solid #dedede;box-shadow:none}
  .info{left:20px;top:20px;border-radius:16px;padding:16px 18px;max-width:min(430px,calc(100vw - 40px))}
  h1{font-size:17px;line-height:1.2;margin:0 0 6px;font-weight:650}.dim{font-size:13px;color:var(--muted)}
  .controls{left:50%;bottom:20px;transform:translateX(-50%);display:flex;gap:7px;padding:8px;border-radius:14px;max-width:calc(100vw - 24px)}
  button,a{border:0;border-radius:9px;background:#fff;color:var(--ink);font:600 12px/1 inherit;padding:10px 12px;white-space:nowrap;text-decoration:none;cursor:pointer;box-shadow:inset 0 0 0 1px #d9d9d9}
  button:hover,a:hover{background:#f5f5f5}button.active{background:var(--accent);color:#fff;box-shadow:none}
  .hint{position:fixed;right:20px;top:20px;font-size:12px;color:var(--muted);padding:11px 13px;border-radius:12px}
  @media(max-width:720px){.hint{display:none}.controls{overflow-x:auto;justify-content:flex-start}.info{top:12px;left:12px}.controls{bottom:12px}}
</style>
</head>
<body>
<canvas id="view" aria-label="Интерактивная 3D-модель тумбы"></canvas>
<section class="panel info"><h1>Тумба — белый линейный 3D-стиль</h1><div class="dim">1800 × 600 × 800 мм · реконструкция v2 · 37 деталей</div></section>
<div class="panel hint">Перетаскивание — вращение · колесо — масштаб</div>
<nav class="panel controls" aria-label="Виды модели">
  <button data-view="perspective" class="active">Перспектива</button>
  <button data-view="axonometric">Аксонометрия</button>
  <button data-view="front">Фасад с размерами</button>
  <button data-view="top">Сверху</button>
  <button data-view="left">Слева</button>
  <button id="hiddenEdgesToggle" class="active">Невидимые грани: вкл</button>
  <button id="shadowsToggle">Тени: выкл</button>
  <a href="tumba_3d_v2.glb" download>Скачать GLB</a>
</nav>
<script>
const parts=__PARTS_JSON__;
const colors={BrownLeather:[255,255,255],BrownEdge:[255,255,255],InteriorBronze:[255,255,255],DarkInterior:[255,255,255],BronzeMetal:[255,255,255],WarmLight:[255,255,255],LineDark:[0,0,0]};
const canvas=document.getElementById('view'),ctx=canvas.getContext('2d',{alpha:false});
let yaw=-0.92,pitch=0.42,distance=3.25,drag=false,lastX=0,lastY=0,raf=0;
let projectionMode='perspective',currentView='perspective',orthoZoom=1,showHiddenEdges=true,showShadows=false;
const worldScale=1000,target=[0,0,0];
const faces=[[0,1,3,2],[4,6,7,5],[0,4,5,1],[2,3,7,6],[0,2,6,4],[1,5,7,3]];
const edgePairs=[[0,1],[0,2],[0,4],[1,3],[1,5],[2,3],[2,6],[3,7],[4,5],[4,6],[5,7],[6,7]];
const normals=[[-1,0,0],[1,0,0],[0,-1,0],[0,1,0],[0,0,-1],[0,0,1]];
const light=norm([-.45,-.65,1]);
function norm(v){const l=Math.hypot(...v)||1;return v.map(x=>x/l)}
function dot(a,b){return a[0]*b[0]+a[1]*b[1]+a[2]*b[2]}
function cross(a,b){return[a[1]*b[2]-a[2]*b[1],a[2]*b[0]-a[0]*b[2],a[0]*b[1]-a[1]*b[0]]}
function sub(a,b){return[a[0]-b[0],a[1]-b[1],a[2]-b[2]]}
function resize(){const d=Math.min(devicePixelRatio||1,2),w=innerWidth,h=innerHeight;canvas.width=Math.round(w*d);canvas.height=Math.round(h*d);canvas.style.width=w+'px';canvas.style.height=h+'px';ctx.setTransform(d,0,0,d,0,0);draw()}
function camera(){const cp=Math.cos(pitch),eye=[distance*cp*Math.cos(yaw),distance*cp*Math.sin(yaw),distance*Math.sin(pitch)];const forward=norm(sub(target,eye)),right=norm(cross(forward,[0,0,1])),up=norm(cross(right,forward));return{eye,forward,right,up}}
function point(p){return[(p[0]-900)/worldScale,(p[1]-300)/worldScale,(p[2]-400)/worldScale]}
function project(p,c,w,h){const rel=sub(p,c.eye),z=dot(rel,c.forward);if(projectionMode==='orthographic'){
  const extents={front:[2.28,1.27],top:[2.30,1.06],left:[1.08,1.27],axonometric:[2.48,1.92]};const extent=extents[currentView]||[1.92,1.02];
  const usableW=Math.max(320,w-80),usableH=Math.max(260,h-150);const scale=Math.min(usableW/extent[0],usableH/extent[1])*orthoZoom;
  return{x:w/2+dot(rel,c.right)*scale,y:h/2+10-dot(rel,c.up)*scale,z};}
  const focal=Math.min(w,h)*1.22;return{x:w/2+dot(rel,c.right)/z*focal,y:h/2-dot(rel,c.up)/z*focal,z}}
function vertices(part){const a=part.min,b=part.max;return[[a[0],a[1],a[2]],[a[0],a[1],b[2]],[a[0],b[1],a[2]],[a[0],b[1],b[2]],[b[0],a[1],a[2]],[b[0],a[1],b[2]],[b[0],b[1],a[2]],[b[0],b[1],b[2]]].map(point)}
function shade(){return[255,255,255]}
function dimArrow(x,y,dx,dy){const len=8.5,wing=4,bx=x-dx*len,by=y-dy*len,px=-dy,py=dx;ctx.beginPath();ctx.moveTo(x,y);ctx.lineTo(bx+px*wing,by+py*wing);ctx.lineTo(bx-px*wing,by-py*wing);ctx.closePath();ctx.fillStyle='#666';ctx.fill()}
function dimText(text,x,y,rotation=0){ctx.save();ctx.translate(x,y);ctx.rotate(rotation);ctx.font='550 16px Segoe UI,Arial,sans-serif';ctx.textAlign='center';ctx.textBaseline='middle';const width=ctx.measureText(text).width+12;ctx.fillStyle='#fff';ctx.fillRect(-width/2,-11,width,22);ctx.fillStyle='#333';ctx.fillText(text,0,0);ctx.restore()}
function dimHSegment(x1,x2,y,label){ctx.beginPath();ctx.moveTo(x1,y);ctx.lineTo(x2,y);ctx.stroke();dimArrow(x1,y,-1,0);dimArrow(x2,y,1,0);dimText(label,(x1+x2)/2,y)}
function dimVSegment(y1,y2,x,label){ctx.beginPath();ctx.moveTo(x,y1);ctx.lineTo(x,y2);ctx.stroke();dimArrow(x,y1,0,-1);dimArrow(x,y2,0,1);dimText(label,x,(y1+y2)/2,-Math.PI/2)}
function facadePoint(x,z,c,w,h){return project(point([x,0,z]),c,w,h)}
function drawFacadeDimensions(c,w,h){const p00=facadePoint(0,0,c,w,h),p18=facadePoint(1800,800,c,w,h),pSplit=facadePoint(1150,800,c,w,h);const left=p00.x,right=p18.x,top=p18.y,bottom=p00.y;
  ctx.save();ctx.strokeStyle='#777';ctx.fillStyle='#666';ctx.lineWidth=.55;ctx.lineCap='square';ctx.lineJoin='miter';
  // All horizontal dimensions are grouped above the object: details near, overall farther away.
  const chainY=top-43;for(const x of [left,pSplit.x,right]){ctx.beginPath();ctx.moveTo(x,top-4);ctx.lineTo(x,chainY-7);ctx.stroke()}dimHSegment(left,pSplit.x,chainY,'1150');dimHSegment(pSplit.x,right,chainY,'650');
  const overallY=top-99;for(const x of [left,right]){ctx.beginPath();ctx.moveTo(x,chainY-12);ctx.lineTo(x,overallY-7);ctx.stroke()}dimHSegment(left,right,overallY,'1800');
  // All vertical dimensions are grouped on the left: construction chain near, overall farther away.
  const chainX=left-53,datums=[0,35,175,325,640,800],labels=['35','140','150','315','160'];const ys=datums.map(z=>facadePoint(0,z,c,w,h).y);for(const y of ys){ctx.beginPath();ctx.moveTo(left-4,y);ctx.lineTo(chainX-7,y);ctx.stroke()}for(let i=0;i<labels.length;i++)dimVSegment(ys[i+1],ys[i],chainX,labels[i]);
  const overallX=left-116;for(const y of [top,bottom]){ctx.beginPath();ctx.moveTo(chainX-12,y);ctx.lineTo(overallX-7,y);ctx.stroke()}dimVSegment(top,bottom,overallX,'800');ctx.restore()}
function topPoint(x,y,c,w,h){return project(point([x,y,800]),c,w,h)}
function drawTopDimensions(c,w,h){const pFrontLeft=topPoint(0,0,c,w,h),pFrontRight=topPoint(1800,0,c,w,h),pBackLeft=topPoint(0,600,c,w,h);const left=Math.min(pFrontLeft.x,pFrontRight.x),right=Math.max(pFrontLeft.x,pFrontRight.x),top=Math.min(pFrontLeft.y,pBackLeft.y),bottom=Math.max(pFrontLeft.y,pBackLeft.y);const split=topPoint(1150,0,c,w,h).x,cavityLeft=topPoint(1180,0,c,w,h).x,cavityRight=topPoint(1500,0,c,w,h).x;ctx.save();ctx.strokeStyle='#777';ctx.fillStyle='#666';ctx.lineWidth=.55;ctx.lineCap='square';ctx.lineJoin='miter';
  const cavityY=top-43;for(const x of [cavityLeft,cavityRight]){ctx.beginPath();ctx.moveTo(x,top-4);ctx.lineTo(x,cavityY-7);ctx.stroke()}dimHSegment(Math.min(cavityLeft,cavityRight),Math.max(cavityLeft,cavityRight),cavityY,'320');
  const moduleY=top-91;for(const x of [left,split,right]){ctx.beginPath();ctx.moveTo(x,cavityY-12);ctx.lineTo(x,moduleY-7);ctx.stroke()}dimHSegment(left,split,moduleY,'1150');dimHSegment(split,right,moduleY,'650');
  const overallY=top-147;for(const x of [left,right]){ctx.beginPath();ctx.moveTo(x,moduleY-12);ctx.lineTo(x,overallY-7);ctx.stroke()}dimHSegment(left,right,overallY,'1800');
  const cavityA=topPoint(0,30,c,w,h).y,cavityB=topPoint(0,570,c,w,h).y,cavityTop=Math.min(cavityA,cavityB),cavityBottom=Math.max(cavityA,cavityB),chainX=left-53;for(const y of [cavityTop,cavityBottom]){ctx.beginPath();ctx.moveTo(left-4,y);ctx.lineTo(chainX-7,y);ctx.stroke()}dimVSegment(cavityTop,cavityBottom,chainX,'540');
  const overallX=left-116;for(const y of [top,bottom]){ctx.beginPath();ctx.moveTo(chainX-12,y);ctx.lineTo(overallX-7,y);ctx.stroke()}dimVSegment(top,bottom,overallX,'600');ctx.restore()}
function sidePoint(depth,z,c,w,h){return project(point([0,depth,z]),c,w,h)}
function drawLeftDimensions(c,w,h){const pFront=sidePoint(0,0,c,w,h),pBack=sidePoint(600,0,c,w,h),pTop=sidePoint(0,800,c,w,h);const left=Math.min(pFront.x,pBack.x),right=Math.max(pFront.x,pBack.x),top=pTop.y,bottom=pFront.y;const plinthA=sidePoint(50,800,c,w,h).x,plinthB=sidePoint(560,800,c,w,h).x;ctx.save();ctx.strokeStyle='#777';ctx.fillStyle='#666';ctx.lineWidth=.55;ctx.lineCap='square';ctx.lineJoin='miter';
  const plinthY=top-43;for(const x of [plinthA,plinthB]){ctx.beginPath();ctx.moveTo(x,top-4);ctx.lineTo(x,plinthY-7);ctx.stroke()}dimHSegment(Math.min(plinthA,plinthB),Math.max(plinthA,plinthB),plinthY,'510');
  const overallY=top-99;for(const x of [left,right]){ctx.beginPath();ctx.moveTo(x,plinthY-12);ctx.lineTo(x,overallY-7);ctx.stroke()}dimHSegment(left,right,overallY,'600');
  const chainX=left-53,datums=[0,35,175,325,640,800],labels=['35','140','150','315','160'],ys=datums.map(z=>sidePoint(0,z,c,w,h).y);for(const y of ys){ctx.beginPath();ctx.moveTo(left-4,y);ctx.lineTo(chainX-7,y);ctx.stroke()}for(let i=0;i<labels.length;i++)dimVSegment(ys[i+1],ys[i],chainX,labels[i]);
  const overallX=left-116;for(const y of [top,bottom]){ctx.beginPath();ctx.moveTo(chainX-12,y);ctx.lineTo(overallX-7,y);ctx.stroke()}dimVSegment(top,bottom,overallX,'800');ctx.restore()}
function axonPoint(x,y,z,c,w,h){return project(point([x,y,z]),c,w,h)}
function axisDimension(p1,p2,a,b,label){const dx=b.x-a.x,dy=b.y-a.y,len=Math.hypot(dx,dy)||1,ux=dx/len,uy=dy/len;const ex1=a.x-p1.x,ey1=a.y-p1.y,el1=Math.hypot(ex1,ey1)||1,ev1={x:ex1/el1,y:ey1/el1};const ex2=b.x-p2.x,ey2=b.y-p2.y,el2=Math.hypot(ex2,ey2)||1,ev2={x:ex2/el2,y:ey2/el2};ctx.beginPath();ctx.moveTo(p1.x+ev1.x*5,p1.y+ev1.y*5);ctx.lineTo(a.x+ev1.x*7,a.y+ev1.y*7);ctx.moveTo(p2.x+ev2.x*5,p2.y+ev2.y*5);ctx.lineTo(b.x+ev2.x*7,b.y+ev2.y*7);ctx.moveTo(a.x,a.y);ctx.lineTo(b.x,b.y);ctx.stroke();dimArrow(a.x,a.y,-ux,-uy);dimArrow(b.x,b.y,ux,uy);let angle=Math.atan2(dy,dx);if(angle>Math.PI/2||angle<-Math.PI/2)angle+=Math.PI;dimText(label,(a.x+b.x)/2,(a.y+b.y)/2,angle)}
function drawAxonometricDimensions(c,w,h){const origin=axonPoint(0,0,0,c,w,h),xSplit=axonPoint(1150,0,0,c,w,h),xEnd=axonPoint(1800,0,0,c,w,h),yEnd=axonPoint(0,600,0,c,w,h),rearTop=axonPoint(0,600,800,c,w,h);ctx.save();ctx.strokeStyle='#777';ctx.fillStyle='#666';ctx.lineWidth=.55;ctx.lineCap='square';ctx.lineJoin='miter';
  // Dimension and extension lines follow only projected furniture axes.
  axisDimension(origin,xSplit,axonPoint(0,0,-70,c,w,h),axonPoint(1150,0,-70,c,w,h),'1150');
  axisDimension(xSplit,xEnd,axonPoint(1150,0,-70,c,w,h),axonPoint(1800,0,-70,c,w,h),'650');
  axisDimension(origin,xEnd,axonPoint(0,0,-155,c,w,h),axonPoint(1800,0,-155,c,w,h),'1800');
  axisDimension(origin,yEnd,axonPoint(-150,0,0,c,w,h),axonPoint(-150,600,0,c,w,h),'600');
  axisDimension(yEnd,rearTop,axonPoint(-150,600,0,c,w,h),axonPoint(-150,600,800,c,w,h),'800');ctx.restore()}
function triangleDepth(px,py,a,b,c){const d=(b.y-c.y)*(a.x-c.x)+(c.x-b.x)*(a.y-c.y);if(Math.abs(d)<1e-8)return null;const u=((b.y-c.y)*(px-c.x)+(c.x-b.x)*(py-c.y))/d;const v=((c.y-a.y)*(px-c.x)+(a.x-c.x)*(py-c.y))/d;const q=1-u-v;if(u<-.001||v<-.001||q<-.001)return null;return u*a.z+v*b.z+q*c.z}
function faceDepth(face,x,y){let z=triangleDepth(x,y,face.pv[0],face.pv[1],face.pv[2]);if(z===null)z=triangleDepth(x,y,face.pv[0],face.pv[2],face.pv[3]);return z}
function edgeIsHidden(edge,renderFaces){let hidden=0;const samples=7;for(let i=1;i<=samples;i++){const t=i/(samples+1),x=edge.a.x+(edge.b.x-edge.a.x)*t,y=edge.a.y+(edge.b.y-edge.a.y)*t,z=edge.a.z+(edge.b.z-edge.a.z)*t;let nearest=Infinity;for(const face of renderFaces){const faceZ=faceDepth(face,x,y);if(faceZ!==null&&faceZ<nearest)nearest=faceZ}if(nearest<z-.0025)hidden++}return hidden>=4}
function drawModelEdges(projectedParts,renderFaces){const visible=[],hidden=[];for(const projected of projectedParts){for(const pair of edgePairs){const edge={a:projected[pair[0]],b:projected[pair[1]]};if(edge.a.z<=.05||edge.b.z<=.05)continue;(edgeIsHidden(edge,renderFaces)?hidden:visible).push(edge)}}ctx.save();ctx.lineCap='butt';ctx.lineJoin='miter';if(showHiddenEdges){ctx.setLineDash([6,5]);ctx.strokeStyle='rgba(120,120,120,.72)';ctx.lineWidth=.6;for(const edge of hidden){ctx.beginPath();ctx.moveTo(edge.a.x,edge.a.y);ctx.lineTo(edge.b.x,edge.b.y);ctx.stroke()}}ctx.setLineDash([]);ctx.strokeStyle='#111';ctx.lineWidth=.8;for(const edge of visible){ctx.beginPath();ctx.moveTo(edge.a.x,edge.a.y);ctx.lineTo(edge.b.x,edge.b.y);ctx.stroke()}ctx.restore()}
function shadowPolygons(c,w,h){const projectedPolygon=points=>points.map(p=>project(point(p),c,w,h));if(currentView==='front'&&projectionMode==='orthographic')return[projectedPolygon([[0,0,35],[1800,0,35],[1800,0,800],[0,0,800]]),projectedPolygon([[60,0,0],[1740,0,0],[1740,0,35],[60,0,35]])];if(currentView==='left'&&projectionMode==='orthographic')return[projectedPolygon([[0,0,0],[0,600,0],[0,600,800],[0,0,800]])];return[projectedPolygon([[0,0,0],[1800,0,0],[1800,600,0],[0,600,0]])]}
function drawSceneShadow(c,w,h){const polygons=shadowPolygons(c,w,h),layers=[[7,9,4,.10],[19,22,13,.17]];for(const layer of layers){ctx.save();ctx.translate(layer[0],layer[1]);ctx.filter=`blur(${layer[2]}px)`;ctx.fillStyle=`rgba(45,45,45,${layer[3]})`;for(const polygon of polygons){ctx.beginPath();ctx.moveTo(polygon[0].x,polygon[0].y);for(let i=1;i<polygon.length;i++)ctx.lineTo(polygon[i].x,polygon[i].y);ctx.closePath();ctx.fill()}ctx.restore()}ctx.filter='none'}
function draw(){raf=0;const w=innerWidth,h=innerHeight,c=camera();ctx.fillStyle='#fff';ctx.fillRect(0,0,w,h);
  if(showShadows)drawSceneShadow(c,w,h);
  const queue=[],projectedParts=[];for(const part of parts){const vv=vertices(part),projected=vv.map(v=>project(v,c,w,h));projectedParts.push(projected);for(let fi=0;fi<6;fi++){const pv=faces[fi].map(i=>projected[i]);if(pv.some(v=>v.z<=.05))continue;const center=pv.reduce((s,v)=>s+v.z,0)/4;queue.push({pv,center,n:normals[fi],material:part.material})}}
  queue.sort((a,b)=>b.center-a.center);ctx.lineJoin='round';for(const f of queue){const rgb=shade();ctx.beginPath();ctx.moveTo(f.pv[0].x,f.pv[0].y);for(let i=1;i<4;i++)ctx.lineTo(f.pv[i].x,f.pv[i].y);ctx.closePath();ctx.fillStyle=`rgb(${rgb.join(',')})`;ctx.fill()}drawModelEdges(projectedParts,queue);if(projectionMode==='orthographic'){if(currentView==='front')drawFacadeDimensions(c,w,h);else if(currentView==='top')drawTopDimensions(c,w,h);else if(currentView==='left')drawLeftDimensions(c,w,h);else if(currentView==='axonometric')drawAxonometricDimensions(c,w,h)}}
function requestDraw(){if(!raf)raf=requestAnimationFrame(draw)}
canvas.addEventListener('pointerdown',e=>{drag=true;lastX=e.clientX;lastY=e.clientY;canvas.classList.add('dragging');canvas.setPointerCapture(e.pointerId)});
canvas.addEventListener('pointermove',e=>{if(!drag)return;yaw-=(e.clientX-lastX)*.008;pitch=Math.max(-1.28,Math.min(1.42,pitch+(e.clientY-lastY)*.007));lastX=e.clientX;lastY=e.clientY;requestDraw()});
canvas.addEventListener('pointerup',()=>{drag=false;canvas.classList.remove('dragging')});canvas.addEventListener('pointercancel',()=>{drag=false;canvas.classList.remove('dragging')});
canvas.addEventListener('wheel',e=>{e.preventDefault();if(projectionMode==='orthographic')orthoZoom=Math.max(.55,Math.min(2.8,orthoZoom*Math.exp(-e.deltaY*.001)));else distance=Math.max(1.8,Math.min(6.2,distance*Math.exp(e.deltaY*.001)));requestDraw()},{passive:false});
const views={perspective:[-.92,.42,3.25],axonometric:[-3*Math.PI/4,Math.atan(1/Math.sqrt(2)),3.4],front:[-Math.PI/2,0,3.1],top:[-Math.PI/2,Math.PI/2-.0001,3.2],left:[Math.PI,0,2.6]};
document.querySelectorAll('[data-view]').forEach(btn=>btn.addEventListener('click',()=>{currentView=btn.dataset.view;projectionMode=currentView==='perspective'?'perspective':'orthographic';orthoZoom=1;[yaw,pitch,distance]=views[currentView];document.querySelectorAll('[data-view]').forEach(x=>x.classList.toggle('active',x===btn));requestDraw()}));
document.getElementById('hiddenEdgesToggle').addEventListener('click',e=>{showHiddenEdges=!showHiddenEdges;e.currentTarget.classList.toggle('active',showHiddenEdges);e.currentTarget.textContent=`Невидимые грани: ${showHiddenEdges?'вкл':'выкл'}`;requestDraw()});
document.getElementById('shadowsToggle').addEventListener('click',e=>{showShadows=!showShadows;e.currentTarget.classList.toggle('active',showShadows);e.currentTarget.textContent=`Тени: ${showShadows?'вкл':'выкл'}`;requestDraw()});
addEventListener('resize',resize);resize();
</script>
</body>
</html>
'''
    html = template.replace("__PARTS_JSON__", json.dumps(payload, separators=(",", ":")))
    path.write_text(html, encoding="utf-8")


def validate_envelope(parts: list[base.Part]) -> dict[str, list[float]]:
    minimum = [min(part.minimum_mm[axis] for part in parts) for axis in range(3)]
    maximum = [max(part.maximum_mm[axis] for part in parts) for axis in range(3)]
    size = [maximum[axis] - minimum[axis] for axis in range(3)]
    if minimum != [0, 0, 0] or size != [1800, 600, 800]:
        raise ValueError(f"Unexpected envelope: min={minimum}, size={size}")
    return {"minimumMm": minimum, "maximumMm": maximum, "sizeMm": size}


def write_bundle(paths: list[Path]) -> None:
    BUNDLE_PATH.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(BUNDLE_PATH, "w", compression=zipfile.ZIP_DEFLATED) as archive:
        for path in paths:
            archive.write(path, arcname=f"tumba_3d_v2/{path.name}")


def main() -> None:
    # The shared exporters look up their material table at module scope.
    base.MATERIALS = MATERIALS
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    parts = build_parts()

    obj_path = OUTPUT_DIR / "tumba_3d_v2.obj"
    mtl_path = OUTPUT_DIR / "tumba_3d_v2.mtl"
    glb_path = OUTPUT_DIR / "tumba_3d_v2.glb"
    scad_path = OUTPUT_DIR / "tumba_3d_v2.scad"
    preview_path = OUTPUT_DIR / "tumba_3d_v2_preview.png"
    manifest_path = OUTPUT_DIR / "tumba_3d_v2_manifest.json"
    readme_path = OUTPUT_DIR / "README.md"
    interactive_path = OUTPUT_DIR / "tumba-3d-interactive-preview.html"

    base.write_mtl(mtl_path)
    vertices, faces = base.write_obj(obj_path, mtl_path.name, parts)
    base.write_glb(glb_path, parts)
    base.write_scad(scad_path, parts)
    base.render_preview(preview_path, parts)
    write_manifest(manifest_path, parts)
    write_readme(readme_path)
    write_interactive_preview(interactive_path, parts)

    envelope = validate_envelope(parts)
    glb_validation = base.validate_glb(glb_path, len(parts))
    deliverables = [
        glb_path,
        obj_path,
        mtl_path,
        scad_path,
        preview_path,
        interactive_path,
        manifest_path,
        readme_path,
    ]
    write_bundle(deliverables)

    summary = {
        "parts": len(parts),
        "envelope": envelope,
        "objVertices": vertices,
        "objFaces": faces,
        "glb": glb_validation,
        "outputDirectory": str(OUTPUT_DIR),
        "bundle": str(BUNDLE_PATH),
    }
    print(json.dumps(summary, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
