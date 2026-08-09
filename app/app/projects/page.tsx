import type { Metadata } from "next";
import Link from "next/link";
import { ArrowRight, CirclePlus } from "lucide-react";

export const metadata: Metadata = {
  title: "Projects",
  description: "Saved CarpDraw AI projects.",
};

export default function ProjectsPage() {
  return (
    <div className="projects-page app-page-narrow">
      <div className="app-page-heading">
        <div>
          <span className="eyebrow">Workspace library</span>
          <h1>Projects</h1>
          <p>Your saved drawings, model drawing briefs and specifications.</p>
        </div>
        <Link className="primary-button" href="/app/photo-to-drawing">
          <CirclePlus size={17} aria-hidden="true" />
          New project
        </Link>
      </div>
      <div className="projects-grid">
        <article className="project-library-card">
          <div className="project-library-image">
            <img src="/examples/example-kitchen-drawing.webp" alt="Kitchen technical drawing" />
          </div>
          <div>
            <h2>Kitchen specification set</h2>
            <p>Technical drawing · Updated today</p>
          </div>
          <Link href="/app/photo-to-drawing">
            Continue
            <ArrowRight size={15} aria-hidden="true" />
          </Link>
        </article>
        <Link className="new-project-card" href="/app/photo-to-drawing">
          <CirclePlus size={28} aria-hidden="true" />
          <strong>Create another project</strong>
          <span>Start from a photo, model or reference file</span>
        </Link>
      </div>
    </div>
  );
}
