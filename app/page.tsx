import type { Metadata } from "next";
import Link from "next/link";
import {
  ArrowRight,
  Check,
  DraftingCompass,
  Layers3,
  Ruler,
  ScanLine,
  Sparkles,
} from "lucide-react";
import { PublicHeader } from "@/components/PublicHeader";
import { Footer } from "@/components/Footer";
import { FaqList } from "@/components/FaqList";
import { TechnicalVisual } from "@/components/TechnicalVisual";

export const metadata: Metadata = {
  title: "AI Product Drawing Workspace",
  description:
    "Convert product photos and sketches into technical drawing concepts, visual renders and editable 3D workflows with CarpDraw AI.",
};

const steps = [
  {
    number: "01",
    icon: ScanLine,
    title: "Upload your object",
    copy: "Drop in a clean product photo, hand sketch or reference image. Your file remains local in this interface preview.",
    label: "INPUT / PHOTO",
  },
  {
    number: "02",
    icon: Ruler,
    title: "Set the real scale",
    copy: "Choose the drawing intent, units and one reliable measurement so the geometry has a useful point of reference.",
    label: "MEASURE / MM",
  },
  {
    number: "03",
    icon: Layers3,
    title: "Prepare the output",
    copy: "Review a clean multi-view technical concept, then continue into PDF, DXF, render or 3D CAD production.",
    label: "OUTPUT / A3",
  },
];

const testimonials = [
  {
    initials: "LM",
    name: "Lena Morris",
    role: "Furniture designer",
    text: "The guided measurement step makes early workshop conversations much clearer. I can show intent before the final CAD pass.",
  },
  {
    initials: "AK",
    name: "Aiden Kim",
    role: "Prototype engineer",
    text: "It turns a rough reference into an organized drawing brief in minutes. The visual hierarchy feels calm and predictable.",
  },
  {
    initials: "ER",
    name: "Eva Roth",
    role: "Product studio lead",
    text: "Our team uses the flow to align on views, scale and output format before sending a job to production.",
  },
  {
    initials: "NS",
    name: "Noah Silva",
    role: "Independent maker",
    text: "I do not live in CAD every day. CarpDraw keeps the technical choices understandable without hiding the important details.",
  },
  {
    initials: "TB",
    name: "Tomas Benda",
    role: "Fabrication planner",
    text: "The neutral drawing canvas and step-by-step controls make client markups far easier to review.",
  },
  {
    initials: "MI",
    name: "Maya Ito",
    role: "Industrial designer",
    text: "The photo-to-drawing and drawing-to-render paths feel like one connected product workflow, not separate tools.",
  },
];

export default function HomePage() {
  return (
    <>
      <PublicHeader />
      <main id="main-content">
        <section className="hero-section">
          <div className="hero-orbit hero-orbit-one" />
          <div className="hero-orbit hero-orbit-two" />
          <div className="container-shell hero-inner">
            <div className="hero-kicker">
              <Sparkles size={15} aria-hidden="true" />
              AI-assisted product documentation
            </div>
            <h1 className="display-title">
              From product photo to a clear technical workflow.
            </h1>
            <p className="hero-copy">
              CarpDraw AI helps makers and product teams transform photos and
              sketches into structured drawing concepts, polished renders and
              editable 3D starting points.
            </p>
            <div className="hero-actions">
              <Link className="primary-button" href="/app">
                Start creating
                <ArrowRight size={17} aria-hidden="true" />
              </Link>
              <Link className="secondary-button" href="/#workflow">
                See how it works
              </Link>
            </div>
            <div className="hero-proof" aria-label="Product highlights">
              <div>
                <strong>4</strong>
                <span>guided steps</span>
              </div>
              <div>
                <strong>15 MB</strong>
                <span>upload preview</span>
              </div>
              <div>
                <strong>PDF · DXF · 3D</strong>
                <span>workflow ready</span>
              </div>
            </div>
            <div className="hero-product-frame">
              <div className="browser-bar">
                <span />
                <span />
                <span />
                <p>carpdraw.ai/workspace</p>
              </div>
              <div className="hero-product-grid">
                <div className="hero-source-card">
                  <span className="eyebrow">Reference object</span>
                  <div className="source-object" aria-label="Grey object placeholder">
                    <div />
                    <div />
                    <div />
                  </div>
                  <small>Neutral product placeholder</small>
                </div>
                <div className="product-flow-arrow" aria-hidden="true">
                  <ArrowRight />
                </div>
                <TechnicalVisual label="SHEET / 01" />
              </div>
            </div>
          </div>
        </section>

        <section className="trusted-strip" aria-label="Designed for">
          <div className="container-shell">
            <p>Designed for practical product teams</p>
            <div>
              <span>Furniture studios</span>
              <span>Prototype labs</span>
              <span>Independent makers</span>
              <span>Fabrication teams</span>
              <span>Design educators</span>
            </div>
          </div>
        </section>

        <section className="workflow-section section-pad" id="workflow">
          <div className="container-shell">
            <div className="section-heading-row">
              <div>
                <span className="eyebrow">Simple by design</span>
                <h2 className="section-title">
                  Photo to technical concept in three focused steps.
                </h2>
              </div>
              <p className="section-copy">
                The interface follows the same calm rhythm as a good drawing
                review: input, measure, organize and confirm.
              </p>
            </div>
            <div className="steps-list">
              {steps.map(({ number, icon: Icon, title, copy, label }, index) => (
                <article className="step-row" key={number}>
                  <div className="step-copy">
                    <div className="step-index">
                      <span>{number}</span>
                      <Icon size={20} aria-hidden="true" />
                    </div>
                    <h3>{title}</h3>
                    <p>{copy}</p>
                  </div>
                  <TechnicalVisual
                    className={`step-visual step-visual-${index + 1}`}
                    label={label}
                  />
                </article>
              ))}
            </div>
          </div>
        </section>

        <section className="feature-story section-pad">
          <div className="container-shell feature-story-grid">
            <div className="feature-story-sticky">
              <span className="eyebrow">Made for real objects</span>
              <h2 className="section-title">
                More than an outline trace.
              </h2>
            </div>
            <div className="feature-story-copy">
              <p className="section-copy">
                CarpDraw AI is shaped around the decisions a technical drafter
                would ask for: which views matter, what measurement is trusted
                and how the result should be communicated. This creates a more
                useful brief than a pixel-only conversion.
              </p>
              <p className="section-copy">
                Use it for product references, furniture, workshop parts,
                display concepts and early documentation. The current demo
                provides the complete front-end experience while leaving
                production geometry processing ready for a future backend.
              </p>
              <div className="feature-checks">
                {[
                  "Orthographic view planning",
                  "Measurement and unit controls",
                  "Drawing, render and 3D paths",
                  "Accessible, keyboard-friendly flow",
                ].map((item) => (
                  <span key={item}>
                    <Check size={16} aria-hidden="true" />
                    {item}
                  </span>
                ))}
              </div>
              <div className="feature-tags" aria-label="Workflow tags">
                {[
                  "Photo to drawing",
                  "Sketch to sheet",
                  "Drawing to render",
                  "Photo to 3D",
                  "PDF brief",
                  "DXF workflow",
                ].map((tag) => (
                  <Link href="/app" key={tag}>
                    {tag}
                  </Link>
                ))}
              </div>
            </div>
          </div>
        </section>

        <section className="social-proof section-pad">
          <div className="container-shell">
            <div className="metrics-heading">
              <span className="eyebrow">A connected design workflow</span>
              <h2 className="section-title">
                Built to keep early ideas technically clear.
              </h2>
            </div>
            <div className="metrics-row">
              <div>
                <strong>3</strong>
                <span>creation modes</span>
              </div>
              <div>
                <strong>4</strong>
                <span>output directions</span>
              </div>
              <div>
                <strong>360°</strong>
                <span>responsive interface</span>
              </div>
            </div>
            <div className="testimonial-grid">
              {testimonials.map((testimonial) => (
                <figure key={testimonial.name}>
                  <div className="testimonial-author">
                    <span>{testimonial.initials}</span>
                    <div>
                      <strong>{testimonial.name}</strong>
                      <small>{testimonial.role}</small>
                    </div>
                  </div>
                  <div className="stars" aria-label="5 out of 5">
                    ★★★★★
                  </div>
                  <blockquote>“{testimonial.text}”</blockquote>
                </figure>
              ))}
            </div>
          </div>
        </section>

        <section className="faq-section section-pad" id="faq">
          <div className="container-shell faq-grid">
            <div>
              <span className="eyebrow">Useful details</span>
              <h2 className="section-title">Frequently asked questions.</h2>
              <p className="section-copy">
                Everything you need to understand the current product preview
                and the services required for production use.
              </p>
            </div>
            <FaqList />
          </div>
        </section>

        <section className="final-cta">
          <div className="container-shell final-cta-inner">
            <div className="cta-icon" aria-hidden="true">
              <DraftingCompass size={30} />
            </div>
            <div>
              <span className="eyebrow">Your next drawing starts here</span>
              <h2>Bring one object. Leave with a clearer technical story.</h2>
            </div>
            <Link className="primary-button" href="/app">
              Open workspace
              <ArrowRight size={17} aria-hidden="true" />
            </Link>
          </div>
        </section>
      </main>
      <Footer />
    </>
  );
}
