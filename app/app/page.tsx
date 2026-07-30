import type { Metadata } from "next";
import Link from "next/link";
import { ArrowRight, Box, Image as ImageIcon, Sparkles } from "lucide-react";
import { TechnicalVisual } from "@/components/TechnicalVisual";

export const metadata: Metadata = {
  title: "Workspace",
  description: "CarpDraw AI creation workspace.",
};

const creationModes = [
  {
    href: "/app/photo-to-drawing",
    title: "Photo to Drawing",
    copy: "Turn a product photo or sketch into a structured technical sheet.",
    icon: Sparkles,
    label: "DRAWING / 01",
  },
  {
    href: "/app/drawing-to-photo",
    title: "Drawing to Photo",
    copy: "Transform a technical reference into a presentation-ready render.",
    icon: ImageIcon,
    label: "RENDER / 02",
  },
  {
    href: "/app/photo-to-3d",
    title: "Photo to 3D CAD",
    copy: "Prepare a photo reference for an editable 3D model concept.",
    icon: Box,
    label: "MODEL / 03",
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
        {creationModes.map(({ href, title, copy, icon: Icon, label }) => (
          <Link className="creation-card" href={href} key={href}>
            <div className="creation-card-top">
              <Icon size={19} aria-hidden="true" />
              <span>{title}</span>
              <ArrowRight size={18} aria-hidden="true" />
            </div>
            <TechnicalVisual label={label} />
            <p>{copy}</p>
          </Link>
        ))}
      </div>
      <Link className="try-banner" href="/app/photo-to-drawing">
        <div>
          <span>New workflow</span>
          <p>Photo or sketch to dimensioned drawing concept</p>
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
        <Link className="project-card" href="/app/projects">
          <TechnicalVisual label="SAMPLE / A3" />
          <strong>First furniture concept</strong>
          <span>Updated today</span>
        </Link>
      </section>
    </div>
  );
}
