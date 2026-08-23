import type { Metadata } from "next";
import Link from "next/link";
import { CirclePlus } from "lucide-react";
import { ProjectsOrganizer } from "@/components/ProjectsOrganizer";

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
      <ProjectsOrganizer />
    </div>
  );
}
