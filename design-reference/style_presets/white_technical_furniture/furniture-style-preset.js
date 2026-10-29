/* White Technical Furniture style preset v1.0.0
 * Paste this file into the next HTML/Canvas 3D viewer or load it with:
 * <script src="furniture-style-preset.js"></script>
 */
(function installFurnitureTechnicalStyle(root) {
  "use strict";

  const STYLE = Object.freeze({
    background: "#ffffff",
    surface: Object.freeze({ color: "#ffffff", metallic: 0, roughness: 1, emissive: [0.1, 0.1, 0.1] }),
    objectLine: Object.freeze({ color: "#111111", width: 0.8, lineCap: "butt", lineJoin: "miter" }),
    hiddenLine: Object.freeze({ enabled: true, color: "rgba(120,120,120,.72)", width: 0.6, dash: [6, 5], depthTolerance: 0.0025 }),
    dimension: Object.freeze({
      lineColor: "#777777",
      lineWidth: 0.55,
      textColor: "#333333",
      arrowColor: "#666666",
      font: "550 16px Segoe UI, Arial, sans-serif",
      arrowLength: 8.5,
      arrowWing: 4,
      maskHeight: 22,
      horizontalPadding: 6,
    }),
    shadows: Object.freeze({
      enabled: false,
      broad: Object.freeze({ offsetX: 19, offsetY: 22, blur: 13, opacity: 0.17 }),
      contact: Object.freeze({ offsetX: 7, offsetY: 9, blur: 4, opacity: 0.10 }),
    }),
    layout: Object.freeze({
      preferredSides: ["top", "left"],
      firstDetailOffset: 43,
      nextDetailOffset: 91,
      overallOffset: 147,
      verticalDetailOffset: 53,
      verticalOverallOffset: 116,
    }),
    export: Object.freeze({
      maxRasterDimension: 4000,
      objectLine: Object.freeze({ color: "#111111", width: 1.8 }),
      hiddenLine: Object.freeze({ color: "#888888", width: 1.05, dash: [9, 7] }),
      dimensionLine: Object.freeze({ color: "#777777", width: 0.9 }),
      dimensionText: Object.freeze({ color: "#333333", fontSize: 34, fontFamily: "Arial, sans-serif", fontWeight: 500 }),
      dimensionArrow: Object.freeze({ color: "#555555", length: 13, wing: 4.6 }),
    }),
  });

  function applyLayer(ctx, layer) {
    ctx.setLineDash([]);
    if (layer === "object") {
      ctx.strokeStyle = STYLE.objectLine.color;
      ctx.lineWidth = STYLE.objectLine.width;
      ctx.lineCap = STYLE.objectLine.lineCap;
      ctx.lineJoin = STYLE.objectLine.lineJoin;
      return;
    }
    if (layer === "hidden") {
      ctx.strokeStyle = STYLE.hiddenLine.color;
      ctx.lineWidth = STYLE.hiddenLine.width;
      ctx.lineCap = "butt";
      ctx.lineJoin = "miter";
      ctx.setLineDash(STYLE.hiddenLine.dash);
      return;
    }
    if (layer === "dimension") {
      ctx.strokeStyle = STYLE.dimension.lineColor;
      ctx.fillStyle = STYLE.dimension.arrowColor;
      ctx.lineWidth = STYLE.dimension.lineWidth;
      ctx.lineCap = "square";
      ctx.lineJoin = "miter";
    }
  }

  function drawArrow(ctx, x, y, dx, dy, scale = 1) {
    const length = STYLE.dimension.arrowLength * scale;
    const wing = STYLE.dimension.arrowWing * scale;
    const baseX = x - dx * length;
    const baseY = y - dy * length;
    const perpendicularX = -dy;
    const perpendicularY = dx;
    ctx.save();
    ctx.fillStyle = STYLE.dimension.arrowColor;
    ctx.beginPath();
    ctx.moveTo(x, y);
    ctx.lineTo(baseX + perpendicularX * wing, baseY + perpendicularY * wing);
    ctx.lineTo(baseX - perpendicularX * wing, baseY - perpendicularY * wing);
    ctx.closePath();
    ctx.fill();
    ctx.restore();
  }

  function drawDimensionText(ctx, text, x, y, rotation = 0, scale = 1) {
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(rotation);
    ctx.font = STYLE.dimension.font.replace("16px", `${16 * scale}px`);
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";
    const width = ctx.measureText(text).width + STYLE.dimension.horizontalPadding * 2 * scale;
    const height = STYLE.dimension.maskHeight * scale;
    ctx.fillStyle = STYLE.background;
    ctx.fillRect(-width / 2, -height / 2, width, height);
    ctx.fillStyle = STYLE.dimension.textColor;
    ctx.fillText(text, 0, 0);
    ctx.restore();
  }

  function drawHorizontalDimension(ctx, x1, x2, y, label, scale = 1) {
    ctx.save();
    applyLayer(ctx, "dimension");
    ctx.beginPath();
    ctx.moveTo(x1, y);
    ctx.lineTo(x2, y);
    ctx.stroke();
    drawArrow(ctx, x1, y, -1, 0, scale);
    drawArrow(ctx, x2, y, 1, 0, scale);
    drawDimensionText(ctx, label, (x1 + x2) / 2, y, 0, scale);
    ctx.restore();
  }

  function drawVerticalDimension(ctx, y1, y2, x, label, scale = 1) {
    ctx.save();
    applyLayer(ctx, "dimension");
    ctx.beginPath();
    ctx.moveTo(x, y1);
    ctx.lineTo(x, y2);
    ctx.stroke();
    drawArrow(ctx, x, y1, 0, -1, scale);
    drawArrow(ctx, x, y2, 0, 1, scale);
    drawDimensionText(ctx, label, x, (y1 + y2) / 2, -Math.PI / 2, scale);
    ctx.restore();
  }

  function svgStyleBlock() {
    const e = STYLE.export;
    return `<style>
.object{fill:none;stroke:${e.objectLine.color};stroke-width:${e.objectLine.width};stroke-linecap:square;stroke-linejoin:miter;vector-effect:non-scaling-stroke}
.hidden{fill:none;stroke:${e.hiddenLine.color};stroke-width:${e.hiddenLine.width};stroke-dasharray:${e.hiddenLine.dash.join(" ")};stroke-linecap:butt;stroke-linejoin:miter;vector-effect:non-scaling-stroke}
.dimension{fill:${e.dimensionArrow.color};stroke:${e.dimensionLine.color};stroke-width:${e.dimensionLine.width};stroke-linecap:square;stroke-linejoin:miter;vector-effect:non-scaling-stroke}
.dimension text{fill:${e.dimensionText.color};stroke:none;font:${e.dimensionText.fontWeight} ${e.dimensionText.fontSize}px ${e.dimensionText.fontFamily};text-anchor:middle;dominant-baseline:central}
.dimension .mask{fill:${STYLE.background};stroke:none}
</style>`;
  }

  root.FURNITURE_TECHNICAL_STYLE = STYLE;
  root.FurnitureTechnicalDrawing = Object.freeze({
    applyLayer,
    drawArrow,
    drawDimensionText,
    drawHorizontalDimension,
    drawVerticalDimension,
    svgStyleBlock,
  });
})(globalThis);
