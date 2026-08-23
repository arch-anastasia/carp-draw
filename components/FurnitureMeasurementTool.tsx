"use client";

import {
  KeyboardEvent,
  PointerEvent as ReactPointerEvent,
  useRef,
  useState,
} from "react";
import { RotateCcw } from "lucide-react";

type Selection = {
  x: number;
  y: number;
  width: number;
  height: number;
};

type DragMode = "draw" | "move" | "nw" | "ne" | "sw" | "se";

type DragState = {
  mode: DragMode;
  startX: number;
  startY: number;
  initial: Selection;
};

type FurnitureMeasurementToolProps = {
  imageUrl: string;
  widthValue: string;
  heightValue: string;
  unit: string;
};

const defaultSelection: Selection = {
  x: 14,
  y: 17,
  width: 72,
  height: 66,
};

const minimumSize = 12;

const clamp = (value: number, minimum: number, maximum: number) =>
  Math.min(Math.max(value, minimum), maximum);

export function FurnitureMeasurementTool({
  imageUrl,
  widthValue,
  heightValue,
  unit,
}: FurnitureMeasurementToolProps) {
  const canvasRef = useRef<HTMLDivElement>(null);
  const dragRef = useRef<DragState | null>(null);
  const [selection, setSelection] = useState<Selection>(defaultSelection);

  const pointFromEvent = (event: ReactPointerEvent<HTMLDivElement>) => {
    const bounds = canvasRef.current?.getBoundingClientRect();
    if (!bounds) return { x: 0, y: 0 };
    return {
      x: clamp(((event.clientX - bounds.left) / bounds.width) * 100, 0, 100),
      y: clamp(((event.clientY - bounds.top) / bounds.height) * 100, 0, 100),
    };
  };

  const beginInteraction = (event: ReactPointerEvent<HTMLDivElement>) => {
    const target = event.target as HTMLElement;
    if (target.closest("[data-measurement-control]")) return;

    event.preventDefault();
    const point = pointFromEvent(event);
    const handle = target.closest<HTMLElement>("[data-handle]")?.dataset
      .handle as DragMode | undefined;

    dragRef.current = {
      mode: handle ?? "draw",
      startX: point.x,
      startY: point.y,
      initial: selection,
    };

    if (!handle) {
      setSelection({ x: point.x, y: point.y, width: 0, height: 0 });
    }

    event.currentTarget.setPointerCapture(event.pointerId);
  };

  const updateInteraction = (event: ReactPointerEvent<HTMLDivElement>) => {
    const drag = dragRef.current;
    if (!drag) return;

    const point = pointFromEvent(event);
    const deltaX = point.x - drag.startX;
    const deltaY = point.y - drag.startY;
    const right = drag.initial.x + drag.initial.width;
    const bottom = drag.initial.y + drag.initial.height;

    if (drag.mode === "draw") {
      setSelection({
        x: Math.min(drag.startX, point.x),
        y: Math.min(drag.startY, point.y),
        width: Math.abs(point.x - drag.startX),
        height: Math.abs(point.y - drag.startY),
      });
      return;
    }

    if (drag.mode === "move") {
      setSelection({
        ...drag.initial,
        x: clamp(drag.initial.x + deltaX, 0, 100 - drag.initial.width),
        y: clamp(drag.initial.y + deltaY, 0, 100 - drag.initial.height),
      });
      return;
    }

    if (drag.mode === "nw") {
      const x = clamp(point.x, 0, right - minimumSize);
      const y = clamp(point.y, 0, bottom - minimumSize);
      setSelection({ x, y, width: right - x, height: bottom - y });
      return;
    }

    if (drag.mode === "ne") {
      const y = clamp(point.y, 0, bottom - minimumSize);
      setSelection({
        x: drag.initial.x,
        y,
        width: clamp(point.x - drag.initial.x, minimumSize, 100 - drag.initial.x),
        height: bottom - y,
      });
      return;
    }

    if (drag.mode === "sw") {
      const x = clamp(point.x, 0, right - minimumSize);
      setSelection({
        x,
        y: drag.initial.y,
        width: right - x,
        height: clamp(point.y - drag.initial.y, minimumSize, 100 - drag.initial.y),
      });
      return;
    }

    setSelection({
      x: drag.initial.x,
      y: drag.initial.y,
      width: clamp(point.x - drag.initial.x, minimumSize, 100 - drag.initial.x),
      height: clamp(point.y - drag.initial.y, minimumSize, 100 - drag.initial.y),
    });
  };

  const endInteraction = (event: ReactPointerEvent<HTMLDivElement>) => {
    if (!dragRef.current) return;
    dragRef.current = null;
    event.currentTarget.releasePointerCapture(event.pointerId);
    setSelection((current) => {
      if (current.width >= minimumSize && current.height >= minimumSize) {
        return current;
      }
      return defaultSelection;
    });
  };

  const handleKeyboard = (event: KeyboardEvent<HTMLDivElement>) => {
    const movement = event.shiftKey ? 2 : 1;
    const resize = event.altKey;
    if (!["ArrowLeft", "ArrowRight", "ArrowUp", "ArrowDown"].includes(event.key)) {
      return;
    }

    event.preventDefault();
    setSelection((current) => {
      if (resize) {
        const widthDelta = event.key === "ArrowLeft" ? -movement : event.key === "ArrowRight" ? movement : 0;
        const heightDelta = event.key === "ArrowUp" ? -movement : event.key === "ArrowDown" ? movement : 0;
        return {
          ...current,
          width: clamp(current.width + widthDelta, minimumSize, 100 - current.x),
          height: clamp(current.height + heightDelta, minimumSize, 100 - current.y),
        };
      }

      const xDelta = event.key === "ArrowLeft" ? -movement : event.key === "ArrowRight" ? movement : 0;
      const yDelta = event.key === "ArrowUp" ? -movement : event.key === "ArrowDown" ? movement : 0;
      return {
        ...current,
        x: clamp(current.x + xDelta, 0, 100 - current.width),
        y: clamp(current.y + yDelta, 0, 100 - current.height),
      };
    });
  };

  const calloutWidth = widthValue ? `${widthValue} ${unit}` : `Width · ${unit}`;
  const calloutHeight = heightValue ? `${heightValue} ${unit}` : `Height · ${unit}`;

  return (
    <div
      className="measurement-canvas"
      ref={canvasRef}
      onPointerDown={beginInteraction}
      onPointerMove={updateInteraction}
      onPointerUp={endInteraction}
      onPointerCancel={endInteraction}
    >
      <img src={imageUrl} alt="Uploaded furniture reference" draggable={false} />
      <div className="measurement-canvas-shade" aria-hidden="true" />

      <div
        className="furniture-selection"
        style={{
          left: `${selection.x}%`,
          top: `${selection.y}%`,
          width: `${selection.width}%`,
          height: `${selection.height}%`,
        }}
      >
        <div
          className="furniture-selection-body"
          data-handle="move"
          tabIndex={0}
          role="group"
          aria-label="Selected furniture area. Use arrow keys to move it. Hold Alt and use arrow keys to resize it."
          onKeyDown={handleKeyboard}
        >
          <span>Selected furniture</span>
        </div>
        {(["nw", "ne", "sw", "se"] as const).map((handle) => (
          <button
            className={`selection-handle selection-handle-${handle}`}
            data-handle={handle}
            type="button"
            aria-label={`Resize selection from ${handle.toUpperCase()} corner`}
            key={handle}
          />
        ))}
      </div>

      <div
        className="dimension-guide dimension-guide-width"
        style={{
          left: `${selection.x}%`,
          top: `${Math.max(3, selection.y - 8)}%`,
          width: `${selection.width}%`,
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
          left: `${Math.max(3, selection.x - 7)}%`,
          top: `${selection.y}%`,
          height: `${selection.height}%`,
        }}
        aria-hidden="true"
      >
        <span className="dimension-cap" />
        <strong>{calloutHeight}</strong>
        <span className="dimension-cap" />
      </div>

      <button
        className="selection-reset"
        type="button"
        data-measurement-control
        onPointerDown={(event) => event.stopPropagation()}
        onClick={() => setSelection(defaultSelection)}
      >
        <RotateCcw size={14} aria-hidden="true" />
        Reset selection
      </button>
    </div>
  );
}
