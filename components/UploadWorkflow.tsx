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
  Check,
  CirclePlus,
  CloudUpload,
  FileImage,
  Ruler,
  RotateCcw,
} from "lucide-react";
import { TechnicalVisual } from "./TechnicalVisual";

type WorkflowMode = "drawing" | "render" | "model";

const workflowCopy = {
  drawing: {
    newLabel: "New generation",
    title: "Upload a product photo",
    subtitle: "A single, clear view of the object works best.",
    prompt: "Drop, paste or click to upload a product photo",
    resultTitle: "Drawing concept ready",
    resultCopy:
      "Your technical sheet structure is prepared for review and export.",
    steps: ["Upload", "Measure", "Setup", "Result"],
  },
  render: {
    newLabel: "New render",
    title: "Upload a technical drawing",
    subtitle: "A clean 2D drawing with orthographic views works best.",
    prompt: "Drop, paste or click to upload a technical drawing",
    resultTitle: "Render brief ready",
    resultCopy:
      "Your drawing has been organized into a presentation render brief.",
    steps: ["Upload", "Setup", "Render"],
  },
  model: {
    newLabel: "New model",
    title: "Upload an object photo",
    subtitle: "A three-quarter view with clear edges works best.",
    prompt: "Drop, paste or click to upload an object photo",
    resultTitle: "3D concept ready",
    resultCopy:
      "Your reference has been prepared for an editable CAD modeling stage.",
    steps: ["Upload", "Setup", "Model"],
  },
};

const acceptedTypes = [
  "image/jpeg",
  "image/png",
  "image/webp",
  "image/heic",
  "image/heif",
];

export function UploadWorkflow({ mode }: { mode: WorkflowMode }) {
  const copy = workflowCopy[mode];
  const inputRef = useRef<HTMLInputElement>(null);
  const [file, setFile] = useState<File | null>(null);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [stage, setStage] = useState(0);
  const [error, setError] = useState("");
  const [unit, setUnit] = useState("mm");
  const [dimension, setDimension] = useState("");
  const [style, setStyle] = useState("clean");

  const activeStep = useMemo(
    () => Math.min(stage, copy.steps.length - 1),
    [copy.steps.length, stage],
  );

  const processFile = (nextFile?: File) => {
    if (!nextFile) return;
    const extension = nextFile.name.split(".").pop()?.toLowerCase();
    const typeOk =
      acceptedTypes.includes(nextFile.type) ||
      extension === "heic" ||
      extension === "heif";
    if (!typeOk) {
      setError("Use a JPG, PNG, HEIC or WebP image.");
      return;
    }
    if (nextFile.size > 15 * 1024 * 1024) {
      setError("The file is larger than 15 MB.");
      return;
    }
    if (previewUrl) URL.revokeObjectURL(previewUrl);
    setPreviewUrl(URL.createObjectURL(nextFile));
    setFile(nextFile);
    setError("");
  };

  const reset = () => {
    if (previewUrl) URL.revokeObjectURL(previewUrl);
    setFile(null);
    setPreviewUrl(null);
    setStage(0);
    setDimension("");
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
      setError("Choose an image before continuing.");
      return;
    }
    if (
      stage === 1 &&
      mode === "drawing" &&
      (!dimension || Number(dimension) <= 0)
    ) {
      setError("Add one valid reference measurement.");
      return;
    }
    setError("");
    setStage((current) => current + 1);
  };

  const finalStage = stage >= copy.steps.length - 1;

  return (
    <div
      className="workflow-page"
      onPaste={(event) => processFile(event.clipboardData.files[0])}
    >
      <div className="workflow-toolbar">
        <button className="text-button new-workflow" type="button" onClick={reset}>
          <CirclePlus size={18} aria-hidden="true" />
          {copy.newLabel}
        </button>
        <ol className="workflow-steps" aria-label="Progress">
          {copy.steps.map((step, index) => (
            <li
              className={
                index === activeStep
                  ? "is-active"
                  : index < activeStep
                    ? "is-complete"
                    : ""
              }
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
                accept=".jpg,.jpeg,.png,.webp,.heic,.heif,image/*"
                hidden
                onChange={(event: ChangeEvent<HTMLInputElement>) =>
                  processFile(event.target.files?.[0])
                }
              />
              {previewUrl ? (
                <img src={previewUrl} alt="Selected upload preview" />
              ) : (
                <div className="upload-cards" aria-hidden="true">
                  <span />
                  <span>
                    <FileImage size={34} />
                  </span>
                  <span />
                </div>
              )}
              <CloudUpload size={34} aria-hidden="true" />
              <strong>{file ? file.name : copy.prompt}</strong>
              <small>
                {file
                  ? `${(file.size / 1024 / 1024).toFixed(2)} MB · click to replace`
                  : "JPG, PNG, HEIC or WebP · up to 15 MB"}
              </small>
            </div>
          </>
        ) : null}

        {stage === 1 ? (
          <>
            <div className="workflow-heading">
              <span className="eyebrow">
                Step 02 · {mode === "drawing" ? "Measure" : "Setup"}
              </span>
              <h1>
                {mode === "drawing"
                  ? "Add one reliable measurement"
                  : "Choose the output direction"}
              </h1>
              <p>
                {mode === "drawing"
                  ? "A known size gives the drawing a meaningful scale."
                  : "These choices become the brief for the next production step."}
              </p>
            </div>
            <div className="setup-grid">
              <div className="setup-preview">
                {previewUrl ? (
                  <img src={previewUrl} alt="Uploaded source" />
                ) : null}
                <div className="measure-line" aria-hidden="true">
                  <span />
                  <span />
                  <p>{dimension || "—"} {unit}</p>
                </div>
              </div>
              <div className="setup-form">
                {mode === "drawing" ? (
                  <>
                    <div className="form-field">
                      <label htmlFor="dimension">Known dimension</label>
                      <div className="dimension-input">
                        <Ruler size={18} aria-hidden="true" />
                        <input
                          id="dimension"
                          type="number"
                          min="0.1"
                          step="0.1"
                          value={dimension}
                          onChange={(event) => setDimension(event.target.value)}
                          placeholder="e.g. 450"
                        />
                        <select
                          aria-label="Measurement unit"
                          value={unit}
                          onChange={(event) => setUnit(event.target.value)}
                        >
                          <option value="mm">mm</option>
                          <option value="cm">cm</option>
                          <option value="in">in</option>
                        </select>
                      </div>
                    </div>
                    <div className="form-field">
                      <label htmlFor="drawing-style">Drawing standard</label>
                      <select
                        id="drawing-style"
                        value={style}
                        onChange={(event) => setStyle(event.target.value)}
                      >
                        <option value="clean">Clean orthographic set</option>
                        <option value="fabrication">Fabrication sheet</option>
                        <option value="assembly">Assembly overview</option>
                      </select>
                    </div>
                  </>
                ) : (
                  <>
                    <div className="form-field">
                      <label htmlFor="output-style">Output style</label>
                      <select
                        id="output-style"
                        value={style}
                        onChange={(event) => setStyle(event.target.value)}
                      >
                        {mode === "render" ? (
                          <>
                            <option value="clean">Clean studio render</option>
                            <option value="material">Material study</option>
                            <option value="context">Interior context</option>
                          </>
                        ) : (
                          <>
                            <option value="clean">Editable solid concept</option>
                            <option value="surface">Surface model concept</option>
                            <option value="assembly">Assembly breakdown</option>
                          </>
                        )}
                      </select>
                    </div>
                    <div className="form-field">
                      <label htmlFor="notes">Optional notes</label>
                      <textarea
                        id="notes"
                        rows={4}
                        placeholder="Add material, finish or modeling notes..."
                      />
                    </div>
                  </>
                )}
                <div className="setup-note">
                  <strong>Preview mode</strong>
                  <p>
                    Files remain in this browser. A production AI/CAD service
                    can be connected later.
                  </p>
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
              <TechnicalVisual label={`${mode.toUpperCase()} / REVIEW`} />
              <div>
                <strong>Source</strong>
                <p>{file?.name}</p>
                <strong>Output direction</strong>
                <p>{style.replace("-", " ")}</p>
                {mode === "drawing" ? (
                  <>
                    <strong>Reference measurement</strong>
                    <p>{dimension} {unit}</p>
                  </>
                ) : null}
              </div>
            </div>
          </>
        ) : null}

        {finalStage ? (
          <div className="workflow-result">
            <div className="result-check">
              <Check size={28} aria-hidden="true" />
            </div>
            <span className="eyebrow">Preview complete</span>
            <h1>{copy.resultTitle}</h1>
            <p>{copy.resultCopy}</p>
            <TechnicalVisual label="CARPDRAW / READY" />
            <div className="result-actions">
              <button className="secondary-button" type="button" onClick={reset}>
                <RotateCcw size={16} aria-hidden="true" />
                Start another
              </button>
              <Link className="primary-button" href="/app/projects">
                Save to projects
                <ArrowRight size={16} aria-hidden="true" />
              </Link>
            </div>
          </div>
        ) : null}

        {error ? (
          <p className="workflow-error error-text" role="alert">
            {error}
          </p>
        ) : null}
        {!finalStage ? (
          <div className="workflow-actions">
            {stage > 0 ? (
              <button
                className="secondary-button"
                type="button"
                onClick={() => {
                  setError("");
                  setStage((current) => current - 1);
                }}
              >
                <ArrowLeft size={16} aria-hidden="true" />
                Back
              </button>
            ) : null}
            <button
              className="primary-button"
              type="button"
              onClick={handleContinue}
              disabled={stage === 0 && !file}
            >
              Continue
              <ArrowRight size={16} aria-hidden="true" />
            </button>
          </div>
        ) : null}
      </section>
    </div>
  );
}
