type TechnicalVisualProps = {
  label?: string;
  className?: string;
};

export function TechnicalVisual({
  label = "CONCEPT / 01",
  className = "",
}: TechnicalVisualProps) {
  return (
    <div
      className={`technical-visual ${className}`}
      role="img"
      aria-label="Neutral technical drawing placeholder"
    >
      <span className="tech-line one" />
      <span className="tech-line two" />
      <span className="tech-line three" />
      <span className="tech-dot one" />
      <span className="tech-dot two" />
      <span className="tech-label">{label}</span>
    </div>
  );
}
