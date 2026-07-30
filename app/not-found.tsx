import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { BrandLogo } from "@/components/BrandLogo";
import { TechnicalVisual } from "@/components/TechnicalVisual";

export default function NotFound() {
  return (
    <main id="main-content" className="not-found-page">
      <div className="not-found-inner">
        <BrandLogo />
        <span className="eyebrow">Error 404</span>
        <h1>This drawing view does not exist.</h1>
        <p>
          The page may have moved, or the link may be incomplete. Return to the
          CarpDraw AI landing page or open the workspace.
        </p>
        <div>
          <Link className="secondary-button" href="/">
            <ArrowLeft size={16} aria-hidden="true" />
            Back home
          </Link>
          <Link className="primary-button" href="/app">
            Open workspace
          </Link>
        </div>
      </div>
      <TechnicalVisual label="404 / NOT FOUND" />
    </main>
  );
}
