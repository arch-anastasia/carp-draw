import type { Metadata } from "next";
import Link from "next/link";
import {
  ArrowRight,
  Box,
  FileSpreadsheet,
  Sparkles,
} from "lucide-react";

export const metadata: Metadata = {
  title: "Workspace",
  description: "CarpDraw AI technical documentation workspace.",
};

const creationModes = [
  {
    href: "/app/photo-to-drawing",
    title: "Photo to Drawing",
    copy: "Turn a product photo or sketch into a structured technical drawing.",
    icon: Sparkles,
    image: "/examples/step-photo.webp",
    label: "PHOTO / 01",
  },
  {
    href: "/app/model-to-drawing",
    title: "3D Model to Drawing",
    copy: "Upload a 3D model and prepare clear orthographic drawing views.",
    icon: Box,
    image: "/examples/step-drawing.webp",
    label: "MODEL / 02",
  },
  {
    href: "/app/create-specification",
    title: "Create Specification",
    copy: "Organize dimensions, materials, notes and outputs in one specification.",
    icon: FileSpreadsheet,
    image: "/examples/hero-workflow.webp",
    label: "SPEC / 03",
  },
];

export default function WorkspaceHomePage() {
  return (
    <div className="dashboard-page">
      <div className="dashboard-intro">
        <span className="eyebrow">CarpDraw workspace</span>
        <h1>What would you like to create?</h1>
        <p>
          Choose a focused workflow. Each route guides the technical decisions
          one step at a time.
        </p>
      </div>
      <div className="creation-grid">
        {creationModes.map(({ href, title, copy, icon: Icon, image, label }) => (
          <Link className="creation-card" href={href} key={href}>
            <div className="creation-card-top">
              <Icon size={19} aria-hidden="true" />
              <span>{title}</span>
              <ArrowRight size={18} aria-hidden="true" />
            </div>
            <div className="creation-image">
              <img src={image} alt="" />
              <span>{label}</span>
            </div>
            <p>{copy}</p>
          </Link>
        ))}
      </div>
      <Link className="try-banner" href="/app/photo-to-drawing">
        <div>
          <span>New workflow</span>
          <p>Photo or sketch to dimensioned technical drawing</p>
        </div>
        <strong>
          Try Drawing <span>V2</span>
        </strong>
        <ArrowRight aria-hidden="true" />
      </Link>
      <section className="recent-section">
        <div className="app-section-heading">
          <div>
            <span className="eyebrow">Saved work</span>
            <h2>Recent projects</h2>
          </div>
          <Link href="/app/projects">
            View all <ArrowRight size={15} aria-hidden="true" />
          </Link>
        </div>
        <Link className="project-card project-card-image" href="/app/projects">
          <img
            src="/examples/example-kitchen-drawing.webp"
            alt="Kitchen technical drawing example"
          />
          <strong>Kitchen specification set</strong>
          <span>Updated today</span>
        </Link>
      </section>
    </div>
  );
}
