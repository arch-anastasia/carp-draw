"use client";

import {
  ChangeEvent,
  DragEvent,
  KeyboardEvent,
  useMemo,
  useRef,
  useState,
} from "react";
import Link from "next/link";
import {
  ArrowLeft,
  ArrowRight,
  Box,
  Check,
  CirclePlus,
  CloudUpload,
  FileImage,
  FileSpreadsheet,
  Move,
  Ruler,
  RotateCcw,
} from "lucide-react";
import { FurnitureMeasurementTool } from "./FurnitureMeasurementTool";
import { TechnicalVisual } from "./TechnicalVisual";

export type WorkflowMode = "drawing" | "modelDrawing" | "specification";

const workflowCopy = {
  drawing: {
    newLabel: "New drawing",
    title: "Upload a product photo",
    subtitle: "A single, clear view of the object works best.",
    prompt: "Drop, paste or click to upload a product photo",
    resultTitle: "Drawing concept ready",
    resultCopy: "Your technical sheet structure is prepared for review and export.",
    steps: ["Upload", "Measure", "Review", "Result"],
    accept: ".jpg,.jpeg,.png,.webp,.heic,.heif,image/*",
    formats: "JPG, PNG, HEIC or WebP · up to 15 MB",
    extensions: ["jpg", "jpeg", "png", "webp", "heic", "heif"],
    maxMb: 15,
  },
  modelDrawing: {
    newLabel: "New model drawing",
    title: "Upload a 3D model",
    subtitle: "Use a clean model with correctly grouped geometry.",
    prompt: "Drop or click to upload a 3D model",
    resultTitle: "Model drawing brief ready",
    resultCopy: "Orthographic views and sheet settings are prepared for production.",
    steps: ["Upload", "Views", "Review", "Result"],
    accept: ".skp,.3ds,.obj,.dwg",
    formats: "SKP, 3DS, OBJ or DWG · up to 50 MB",
    extensions: ["skp", "3ds", "obj", "dwg"],
    maxMb: 50,
  },
  specification: {
    newLabel: "New specification",
    title: "Upload your project reference",
    subtitle: "Add a drawing, model or product image as the source.",
    prompt: "Drop, paste or click to upload a project reference",
    resultTitle: "Specification draft ready",
    resultCopy: "The project information is organized into a structured specification.",
    steps: ["Upload", "Details", "Review", "Result"],
    accept: ".jpg,.jpeg,.png,.webp,.pdf,.skp,.3ds,.obj,.dwg,.dxf",
    formats: "Image, PDF, SKP, 3DS, OBJ, DWG or DXF · up to 50 MB",
    extensions: ["jpg", "jpeg", "png", "webp", "pdf", "skp", "3ds", "obj", "dwg", "dxf"],
    maxMb: 50,
  },
} as const;

export function UploadWorkflow({ mode }: { mode: WorkflowMode }) {
  const copy = workflowCopy[mode];
  const inputRef = useRef<HTMLInputElement>(null);
  const [file, setFile] = useState<File | null>(null);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [stage, setStage] = useState(0);
  const [error, setError] = useState("");
  const [unit, setUnit] = useState("mm");
  const [widthValue, setWidthValue] = useState("");
  const [heightValue, setHeightValue] = useState("");
  const [depthValue, setDepthValue] = useState("");
  const [style, setStyle] = useState("clean");
  const [projectName, setProjectName] = useState("");
  const [material, setMaterial] = useState("");

  const activeStep = useMemo(
    () => Math.min(stage, copy.steps.length - 1),
    [copy.steps.length, stage],
  );

  const processFile = (nextFile?: File) => {
    if (!nextFile) return;
    const extension = nextFile.name.split(".").pop()?.toLowerCase() || "";
    if (!(copy.extensions as readonly string[]).includes(extension)) {
      setError(`Supported formats: ${copy.extensions.join(", ").toUpperCase()}.`);
      return;
    }
    if (nextFile.size > copy.maxMb * 1024 * 1024) {
      setError(`The file is larger than ${copy.maxMb} MB.`);
      return;
    }

    if (previewUrl) URL.revokeObjectURL(previewUrl);
    setPreviewUrl(nextFile.type.startsWith("image/") ? URL.createObjectURL(nextFile) : null);
    setFile(nextFile);
    setError("");
  };

  const reset = () => {
    if (previewUrl) URL.revokeObjectURL(previewUrl);
    setFile(null);
    setPreviewUrl(null);
    setStage(0);
    setWidthValue("");
    setHeightValue("");
    setDepthValue("");
    setProjectName("");
    setMaterial("");
    setError("");
    if (inputRef.current) inputRef.current.value = "";
  };

  const handleDrop = (event: DragEvent<HTMLDivElement>) => {
    event.preventDefault();
    processFile(event.dataTransfer.files[0]);
  };

  const handleKey = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      inputRef.current?.click();
    }
  };

  const handleContinue = () => {
    if (stage === 0 && !file) {
      setError("Choose a file before continuing.");
      return;
    }
    if (
      stage === 1 &&
      mode === "drawing" &&
      (!widthValue || Number(widthValue) <= 0 || !heightValue || Number(heightValue) <= 0)
    ) {
      setError("Add valid width and height measurements.");
      return;
    }
    if (stage === 1 && mode === "specification" && !projectName.trim()) {
      setError("Add a project or product name.");
      return;
    }
    setError("");
    setStage((current) => current + 1);
  };

  const finalStage = stage >= copy.steps.length - 1;
  const setupTitle =
    mode === "drawing"
      ? "Select furniture & set real dimensions"
      : mode === "modelDrawing"
        ? "Choose the required drawing views"
        : "Add the specification details";

  return (
    <div className="workflow-page" onPaste={(event) => processFile(event.clipboardData.files[0])}>
      <div className="workflow-toolbar">
        <button className="text-button new-workflow" type="button" onClick={reset}>
          <CirclePlus size={18} aria-hidden="true" />
          {copy.newLabel}
        </button>
        <ol className="workflow-steps" aria-label="Progress">
          {copy.steps.map((step, index) => (
            <li
              className={index === activeStep ? "is-active" : index < activeStep ? "is-complete" : ""}
              key={step}
              aria-current={index === activeStep ? "step" : undefined}
            >
              <span>{index < activeStep ? <Check size={13} /> : index + 1}</span>
              {step}
            </li>
          ))}
        </ol>
      </div>

      <section className="workflow-card" aria-live="polite">
        {stage === 0 ? (
          <>
            <div className="workflow-heading">
              <span className="eyebrow">Step 01 · Source</span>
              <h1>{copy.title}</h1>
              <p>{copy.subtitle}</p>
            </div>
            <div
              className={`upload-dropzone${file ? " has-file" : ""}`}
              role="button"
              tabIndex={0}
              onClick={() => inputRef.current?.click()}
              onKeyDown={handleKey}
              onDragOver={(event) => event.preventDefault()}
              onDrop={handleDrop}
              aria-label={copy.prompt}
            >
              <input
                ref={inputRef}
                type="file"
                accept={copy.accept}
                hidden
                onChange={(event: ChangeEvent<HTMLInputElement>) => processFile(event.target.files?.[0])}
              />
              {previewUrl ? (
                <img src={previewUrl} alt="Selected upload preview" />
              ) : file ? (
                <div className="uploaded-file-card" aria-hidden="true">
                  <Box size={52} />
                  <span>{file.name.split(".").pop()?.toUpperCase()}</span>
                </div>
              ) : (
                <div className="upload-cards" aria-hidden="true">
                  <span />
                  <span>{mode === "modelDrawing" ? <Box size={34} /> : mode === "specification" ? <FileSpreadsheet size={34} /> : <FileImage size={34} />}</span>
                  <span />
                </div>
              )}
              <CloudUpload size={34} aria-hidden="true" />
              <strong>{file ? file.name : copy.prompt}</strong>
              <small>{file ? `${(file.size / 1024 / 1024).toFixed(2)} MB · click to replace` : copy.formats}</small>
            </div>
          </>
        ) : null}

        {stage === 1 ? (
          <>
            <div className="workflow-heading">
              <span className="eyebrow">Step 02 · {copy.steps[1]}</span>
              <h1>{setupTitle}</h1>
              <p>{mode === "drawing" ? "Frame the furniture item and enter the measurements you know in the real world." : "These choices form the technical brief for the next production step."}</p>
            </div>
            <div className={`setup-grid${mode === "drawing" ? " measurement-grid" : ""}`}>
              {mode === "drawing" && previewUrl ? (
                <FurnitureMeasurementTool
                  imageUrl={previewUrl}
                  widthValue={widthValue}
                  heightValue={heightValue}
                  unit={unit}
                />
              ) : (
                <div className="setup-preview">
                  {previewUrl ? <img src={previewUrl} alt="Uploaded source" /> : (
                    <div className="setup-file-preview">
                      <Box size={58} aria-hidden="true" />
                      <strong>{file?.name}</strong>
                    </div>
                  )}
                </div>
              )}
              <div className="setup-form">
                {mode === "drawing" ? (
                  <>
                    <div className="selection-guidance">
                      <span><Move size={18} aria-hidden="true" /></span>
                      <div>
                        <strong>Select one furniture item</strong>
                        <p>Drag a new box around the item, then move it or resize it from the corner points.</p>
                      </div>
                    </div>
                    <div className="measurement-fields">
                      <div className="form-field">
                        <label htmlFor="furniture-width">Width</label>
                        <div className="dimension-input compact-dimension-input">
                          <Ruler size={17} aria-hidden="true" />
                          <input id="furniture-width" type="number" min="0.1" step="0.1" value={widthValue} onChange={(event) => setWidthValue(event.target.value)} placeholder="2000" inputMode="decimal" />
                        </div>
                      </div>
                      <div className="form-field">
                        <label htmlFor="furniture-height">Height</label>
                        <div className="dimension-input compact-dimension-input">
                          <Ruler size={17} aria-hidden="true" />
                          <input id="furniture-height" type="number" min="0.1" step="0.1" value={heightValue} onChange={(event) => setHeightValue(event.target.value)} placeholder="850" inputMode="decimal" />
                        </div>
                      </div>
                    </div>
                    <div className="form-field">
                      <label htmlFor="furniture-depth">Depth <span>optional</span></label>
                      <div className="dimension-input compact-dimension-input">
                        <Ruler size={18} aria-hidden="true" />
                        <input id="furniture-depth" type="number" min="0.1" step="0.1" value={depthValue} onChange={(event) => setDepthValue(event.target.value)} placeholder="450" inputMode="decimal" />
                      </div>
                    </div>
                    <div className="form-field">
                      <label htmlFor="measurement-unit">Measurement unit</label>
                      <select id="measurement-unit" value={unit} onChange={(event) => setUnit(event.target.value)}>
                        <option value="mm">Millimetres (mm)</option><option value="cm">Centimetres (cm)</option><option value="in">Inches (in)</option>
                      </select>
                    </div>
                    <div className="form-field">
                      <label htmlFor="drawing-style">Drawing standard</label>
                      <select id="drawing-style" value={style} onChange={(event) => setStyle(event.target.value)}>
                        <option value="clean orthographic set">Clean orthographic set</option>
                        <option value="fabrication sheet">Fabrication sheet</option>
                        <option value="assembly overview">Assembly overview</option>
                      </select>
                    </div>
                  </>
                ) : mode === "modelDrawing" ? (
                  <>
                    <div className="form-field">
                      <label htmlFor="view-set">Drawing view set</label>
                      <select id="view-set" value={style} onChange={(event) => setStyle(event.target.value)}>
                        <option value="front side and plan views">Front, side and plan views</option>
                        <option value="orthographic and isometric views">Orthographic + isometric</option>
                        <option value="assembly and detail views">Assembly + detail views</option>
                      </select>
                    </div>
                    <div className="form-field">
                      <label htmlFor="model-units">Model units</label>
                      <select id="model-units" value={unit} onChange={(event) => setUnit(event.target.value)}>
                        <option value="mm">Millimetres</option><option value="cm">Centimetres</option><option value="in">Inches</option>
                      </select>
                    </div>
                  </>
                ) : (
                  <>
                    <div className="form-field">
                      <label htmlFor="project-name">Project or product name</label>
                      <input id="project-name" value={projectName} onChange={(event) => setProjectName(event.target.value)} placeholder="e.g. Living room shelving" />
                    </div>
                    <div className="form-field">
                      <label htmlFor="material">Primary material</label>
                      <input id="material" value={material} onChange={(event) => setMaterial(event.target.value)} placeholder="e.g. Oak veneer, 30 mm" />
                    </div>
                    <div className="form-field">
                      <label htmlFor="specification-type">Specification type</label>
                      <select id="specification-type" value={style} onChange={(event) => setStyle(event.target.value)}>
                        <option value="manufacturing specification">Manufacturing specification</option>
                        <option value="materials and finishes schedule">Materials and finishes schedule</option>
                        <option value="project handover document">Project handover document</option>
                      </select>
                    </div>
                  </>
                )}
                <div className="setup-note">
                  <strong>Preview mode</strong>
                  <p>Files remain in this browser. Production conversion and storage can be connected to a backend later.</p>
                </div>
              </div>
            </div>
          </>
        ) : null}

        {stage > 1 && !finalStage ? (
          <>
            <div className="workflow-heading">
              <span className="eyebrow">Step 03 · Review</span>
              <h1>Review the prepared brief</h1>
              <p>Confirm the source and output intent before the result stage.</p>
            </div>
            <div className="review-panel">
              <TechnicalVisual label={`${mode === "modelDrawing" ? "MODEL" : mode.toUpperCase()} / REVIEW`} />
              <div>
                <strong>Source</strong><p>{file?.name}</p>
                <strong>Output direction</strong><p>{style}</p>
                {mode === "drawing" ? <><strong>Furniture dimensions</strong><p>{widthValue} × {heightValue}{depthValue ? ` × ${depthValue}` : ""} {unit}</p></> : null}
                {mode === "modelDrawing" ? <><strong>Model units</strong><p>{unit}</p></> : null}
                {mode === "specification" ? <><strong>Project</strong><p>{projectName}</p><strong>Material</strong><p>{material || "Not specified"}</p></> : null}
              </div>
            </div>
          </>
        ) : null}

        {finalStage ? (
          <div className="workflow-result">
            <div className="result-check"><Check size={28} aria-hidden="true" /></div>
            <span className="eyebrow">Preview complete</span>
            <h1>{copy.resultTitle}</h1>
            <p>{copy.resultCopy}</p>
            <TechnicalVisual label="CARPDRAW / READY" />
            <div className="result-actions">
              <button className="secondary-button" type="button" onClick={reset}><RotateCcw size={16} aria-hidden="true" />Start another</button>
              <Link className="primary-button" href="/app/projects">Save to projects<ArrowRight size={16} aria-hidden="true" /></Link>
            </div>
          </div>
        ) : null}

        {error ? <p className="workflow-error error-text" role="alert">{error}</p> : null}
        {!finalStage ? (
          <div className="workflow-actions">
            {stage > 0 ? <button className="secondary-button" type="button" onClick={() => { setError(""); setStage((current) => current - 1); }}><ArrowLeft size={16} aria-hidden="true" />Back</button> : null}
            <button className="primary-button" type="button" onClick={handleContinue} disabled={stage === 0 && !file}>Continue<ArrowRight size={16} aria-hidden="true" /></button>
          </div>
        ) : null}
      </section>
    </div>
  );
}
