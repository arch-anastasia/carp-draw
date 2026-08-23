"use client";

import {
  KeyboardEvent,
  PointerEvent as ReactPointerEvent,
  useMemo,
  useRef,
  useState,
} from "react";
import { Move, PenTool, RotateCcw } from "lucide-react";

type Point = {
  x: number;
  y: number;
};

type ToolMode = "lasso" | "move";

type Interaction =
  | { mode: "draw" }
  | {
      mode: "move";
      start: Point;
      initialPoints: Point[];
    };

type FurnitureMeasurementToolProps = {
  imageUrl: string;
  widthValue: string;
  heightValue: string;
  unit: string;
};

const defaultPoints: Point[] = [
  { x: 18, y: 25 },
  { x: 29, y: 18 },
  { x: 58, y: 16 },
  { x: 78, y: 23 },
  { x: 86, y: 41 },
  { x: 82, y: 67 },
  { x: 68, y: 79 },
  { x: 39, y: 83 },
  { x: 20, y: 73 },
  { x: 13, y: 50 },
];

const clamp = (value: number, minimum: number, maximum: number) =>
  Math.min(Math.max(value, minimum), maximum);

const getBounds = (points: Point[]) => {
  const xs = points.map((point) => point.x);
  const ys = points.map((point) => point.y);
  const minX = Math.min(...xs);
  const maxX = Math.max(...xs);
  const minY = Math.min(...ys);
  const maxY = Math.max(...ys);

  return {
    minX,
    maxX,
    minY,
    maxY,
    width: maxX - minX,
    height: maxY - minY,
  };
};

const isInsidePolygon = (point: Point, polygon: Point[]) => {
  let inside = false;

  for (let index = 0, previous = polygon.length - 1; index < polygon.length; previous = index++) {
    const currentPoint = polygon[index];
    const previousPoint = polygon[previous];
    const intersects =
      currentPoint.y > point.y !== previousPoint.y > point.y &&
      point.x <
        ((previousPoint.x - currentPoint.x) * (point.y - currentPoint.y)) /
          (previousPoint.y - currentPoint.y) +
          currentPoint.x;

    if (intersects) inside = !inside;
  }

  return inside;
};

const translatePoints = (points: Point[], deltaX: number, deltaY: number) => {
  const bounds = getBounds(points);
  const safeDeltaX = clamp(deltaX, -bounds.minX, 100 - bounds.maxX);
  const safeDeltaY = clamp(deltaY, -bounds.minY, 100 - bounds.maxY);

  return points.map((point) => ({
    x: point.x + safeDeltaX,
    y: point.y + safeDeltaY,
  }));
};

export function FurnitureMeasurementTool({
  imageUrl,
  widthValue,
  heightValue,
  unit,
}: FurnitureMeasurementToolProps) {
  const canvasRef = useRef<HTMLDivElement>(null);
  const interactionRef = useRef<Interaction | null>(null);
  const [points, setPoints] = useState<Point[]>(defaultPoints);
  const [toolMode, setToolMode] = useState<ToolMode>("lasso");
  const [isDrawing, setIsDrawing] = useState(false);

  const bounds = useMemo(() => getBounds(points.length ? points : defaultPoints), [points]);
  const pointList = points.map((point) => `${point.x},${point.y}`).join(" ");

  const pointFromEvent = (event: ReactPointerEvent<HTMLDivElement>) => {
    const canvasBounds = canvasRef.current?.getBoundingClientRect();
    if (!canvasBounds) return { x: 0, y: 0 };

    return {
      x: clamp(((event.clientX - canvasBounds.left) / canvasBounds.width) * 100, 0, 100),
      y: clamp(((event.clientY - canvasBounds.top) / canvasBounds.height) * 100, 0, 100),
    };
  };

  const beginInteraction = (event: ReactPointerEvent<HTMLDivElement>) => {
    const target = event.target as HTMLElement;
    if (target.closest("[data-measurement-control]")) return;

    const point = pointFromEvent(event);
    if (toolMode === "move" && !isInsidePolygon(point, points)) return;

    event.preventDefault();
    event.currentTarget.setPointerCapture(event.pointerId);

    if (toolMode === "lasso") {
      interactionRef.current = { mode: "draw" };
      setPoints([point]);
      setIsDrawing(true);
      return;
    }

    interactionRef.current = {
      mode: "move",
      start: point,
      initialPoints: points,
    };
  };

  const updateInteraction = (event: ReactPointerEvent<HTMLDivElement>) => {
    const interaction = interactionRef.current;
    if (!interaction) return;

    const point = pointFromEvent(event);

    if (interaction.mode === "draw") {
      setPoints((currentPoints) => {
        const previousPoint = currentPoints[currentPoints.length - 1];
        if (!previousPoint) return [point];

        const distance = Math.hypot(point.x - previousPoint.x, point.y - previousPoint.y);
        return distance >= 0.75 ? [...currentPoints, point] : currentPoints;
      });
      return;
    }

    setPoints(
      translatePoints(
        interaction.initialPoints,
        point.x - interaction.start.x,
        point.y - interaction.start.y,
      ),
    );
  };

  const endInteraction = (event: ReactPointerEvent<HTMLDivElement>) => {
    const interaction = interactionRef.current;
    if (!interaction) return;

    interactionRef.current = null;
    if (event.currentTarget.hasPointerCapture(event.pointerId)) {
      event.currentTarget.releasePointerCapture(event.pointerId);
    }

    if (interaction.mode === "draw") {
      setIsDrawing(false);
      setPoints((currentPoints) => {
        if (currentPoints.length < 4) return defaultPoints;
        const currentBounds = getBounds(currentPoints);
        return currentBounds.width >= 5 && currentBounds.height >= 5
          ? currentPoints
          : defaultPoints;
      });
    }
  };

  const handleKeyboard = (event: KeyboardEvent<HTMLDivElement>) => {
    if (toolMode !== "move") return;
    if (!["ArrowLeft", "ArrowRight", "ArrowUp", "ArrowDown"].includes(event.key)) return;

    event.preventDefault();
    const movement = event.shiftKey ? 2 : 1;
    const deltaX = event.key === "ArrowLeft" ? -movement : event.key === "ArrowRight" ? movement : 0;
    const deltaY = event.key === "ArrowUp" ? -movement : event.key === "ArrowDown" ? movement : 0;
    setPoints((currentPoints) => translatePoints(currentPoints, deltaX, deltaY));
  };

  const resetSelection = () => {
    setPoints(defaultPoints);
    setToolMode("lasso");
    setIsDrawing(false);
  };

  const calloutWidth = widthValue ? `${widthValue} ${unit}` : `Width · ${unit}`;
  const calloutHeight = heightValue ? `${heightValue} ${unit}` : `Height · ${unit}`;

  return (
    <div
      className={`measurement-canvas is-${toolMode}`}
      ref={canvasRef}
      tabIndex={0}
      role="application"
      aria-label="Furniture lasso selection. Choose Lasso and draw around the furniture. Choose Move to reposition the closed contour."
      onKeyDown={handleKeyboard}
      onPointerDown={beginInteraction}
      onPointerMove={updateInteraction}
      onPointerUp={endInteraction}
      onPointerCancel={endInteraction}
    >
      <img src={imageUrl} alt="Uploaded furniture reference" draggable={false} />
      <div className="measurement-canvas-shade" aria-hidden="true" />

      <svg
        className="lasso-overlay"
        viewBox="0 0 100 100"
        preserveAspectRatio="none"
        aria-hidden="true"
      >
        {isDrawing ? (
          <polyline className="lasso-selection is-drawing" points={pointList} />
        ) : (
          <polygon className="lasso-selection" points={pointList} />
        )}
      </svg>

      {!isDrawing && (
        <>
          <div
            className="dimension-guide dimension-guide-width"
            style={{
              left: `${bounds.minX}%`,
              top: `${Math.max(4, bounds.minY - 8)}%`,
              width: `${bounds.width}%`,
            }}
            aria-hidden="true"
          >
            <span className="dimension-cap" />
            <strong>{calloutWidth}</strong>
            <span className="dimension-cap" />
          </div>

          <div
            className="dimension-guide dimension-guide-height"
            style={{
              left: `${Math.max(4, bounds.minX - 7)}%`,
              top: `${bounds.minY}%`,
              height: `${bounds.height}%`,
            }}
            aria-hidden="true"
          >
            <span className="dimension-cap" />
            <strong>{calloutHeight}</strong>
            <span className="dimension-cap" />
          </div>
        </>
      )}

      <div className="lasso-toolbar" data-measurement-control>
        <button
          className={`lasso-tool-button${toolMode === "lasso" ? " is-active" : ""}`}
          type="button"
          aria-pressed={toolMode === "lasso"}
          onClick={() => setToolMode("lasso")}
        >
          <PenTool size={15} aria-hidden="true" />
          Lasso
        </button>
        <button
          className={`lasso-tool-button${toolMode === "move" ? " is-active" : ""}`}
          type="button"
          aria-pressed={toolMode === "move"}
          onClick={() => setToolMode("move")}
        >
          <Move size={15} aria-hidden="true" />
          Move
        </button>
        <button className="lasso-tool-button" type="button" onClick={resetSelection}>
          <RotateCcw size={15} aria-hidden="true" />
          Reset
        </button>
      </div>

      <div className="lasso-status" data-measurement-control aria-live="polite">
        {isDrawing
          ? "Keep drawing around the furniture"
          : toolMode === "lasso"
            ? "Draw a freehand contour around one item"
            : "Drag inside the contour to move it"}
      </div>
    </div>
  );
}
