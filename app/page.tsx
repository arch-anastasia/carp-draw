import type { Metadata } from "next";
import Link from "next/link";
import {
  ArrowRight,
  Check,
  DraftingCompass,
  FileSpreadsheet,
  Ruler,
  ScanLine,
  Sparkles,
} from "lucide-react";
import { PublicHeader } from "@/components/PublicHeader";
import { Footer } from "@/components/Footer";
import { FaqList } from "@/components/FaqList";

export const metadata: Metadata = {
  title: "Technical Drawings from Photos and 3D Models",
  description:
    "Create technical drawings from product photos and 3D models, then prepare clear product specifications with CarpDraw AI.",
};

const steps = [
  {
    number: "01",
    icon: ScanLine,
    title: "Upload your reference",
    copy: "Add a clear product photo, hand sketch or project image. The guided workspace keeps the source and technical decisions together.",
    label: "INPUT / PHOTO",
    image: "/examples/step-photo.webp",
    alt: "Interior reference image for a fitted shelving project",
  },
  {
    number: "02",
    icon: Ruler,
    title: "Define scale and intent",
    copy: "Set one reliable measurement, select the drawing views and clarify what the workshop or project team needs to receive.",
    label: "MEASURE / MM",
    image: "/examples/hero-workflow.webp",
    alt: "Reference interior paired with a dimensioned technical drawing",
  },
  {
    number: "03",
    icon: FileSpreadsheet,
    title: "Review the technical output",
    copy: "Check dimensions, notes and views before preparing a drawing set or a structured product specification.",
    label: "OUTPUT / DRAWING",
    image: "/examples/step-drawing.webp",
    alt: "Dimensioned technical drawing of custom shelving",
  },
];

const testimonials = [
  { avatar: "/avatars/profile-1.jpg", name: "Lena M.", role: "Furniture designer", text: "The measuring step was realy clear. I sent the first sheet to our workshop same day and they understand the idea." },
  { avatar: "/avatars/profile-2.jpg", name: "Aiden K.", role: "Prototype engineer", text: "We use it for early drawings before CAD cleanup. Its quick, simple and nobody on the team gets lost in menus." },
  { avatar: "/avatars/profile-3.jpg", name: "Eva R.", role: "Studio lead", text: "The model-to-drawing route saves us a lot of setup. Views are organised in one place, which is what we needed." },
  { avatar: "/avatars/profile-4.jpg", name: "Noah S.", role: "Independent maker", text: "I dont work in CAD every day, but this flow still make sense. Adding one dimension is much easier then starting a sheet." },
  { avatar: "/avatars/profile-5.jpg", name: "Tomas B.", role: "Fabrication planner", text: "Specifications look calm and readable. We can mark what is missing before it goes into production." },
  { avatar: "/avatars/profile-6.jpg", name: "Maya I.", role: "Interior designer", text: "Photo, 3D model and specification feels like one connected workflow. Thats useful for small teams like ours." },
];

const examples = [
  { image: "/examples/example-kitchen.webp", title: "Kitchen reference", type: "PHOTO" },
  { image: "/examples/example-kitchen-drawing.webp", title: "Kitchen drawing set", type: "DRAWING" },
  { image: "/examples/example-bunk.webp", title: "Bunk room concept", type: "REFERENCE" },
  { image: "/examples/example-bunk-drawing.webp", title: "Bunk construction drawing", type: "DRAWING" },
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
            <div className="hero-kicker"><Sparkles size={15} aria-hidden="true" />AI-assisted technical documentation</div>
            <h1 className="display-title">From a reference to a clear technical drawing.</h1>
            <p className="hero-copy">CarpDraw AI helps furniture, interior and product teams create drawing concepts from photos and 3D models, then organize the result into a practical specification.</p>
            <div className="hero-actions">
              <Link className="primary-button" href="/app">Start creating<ArrowRight size={17} aria-hidden="true" /></Link>
              <Link className="secondary-button" href="/#workflow">See how it works</Link>
            </div>
            <div className="hero-proof" aria-label="Product highlights">
              <div><strong>3</strong><span>focused workflows</span></div>
              <div><strong>50 MB</strong><span>3D model upload</span></div>
              <div><strong>PDF · DXF · DWG</strong><span>workflow ready</span></div>
            </div>
            <div className="hero-product-frame">
              <div className="browser-bar"><span /><span /><span /><p>carpdraw.ai/workspace</p></div>
              <div className="hero-media-placeholder">
                <video
                  autoPlay
                  muted
                  loop
                  playsInline
                  preload="metadata"
                  poster="/examples/hero-workflow.webp"
                  aria-label="CarpDraw AI workflow animation"
                >
                  <source src="/video/carpdraw-workflow.mp4" type="video/mp4" />
                </video>
                <span>CarpDraw workflow animation</span>
              </div>
            </div>
          </div>
        </section>

        <section className="trusted-strip" aria-label="Designed for">
          <div className="container-shell">
            <p>Designed for practical product teams</p>
            <div><span>Furniture studios</span><span>Interior teams</span><span>Independent makers</span><span>Fabrication teams</span><span>Design educators</span></div>
          </div>
        </section>

        <section className="workflow-section section-pad" id="workflow">
          <div className="container-shell">
            <div className="section-heading-row">
              <div><span className="eyebrow">Simple by design</span><h2 className="section-title">Photo to technical concept in three focused steps.</h2></div>
              <p className="section-copy">The interface follows the same calm rhythm as a good drawing review: source, scale, organize and confirm.</p>
            </div>
            <div className="steps-list">
              {steps.map(({ number, icon: Icon, title, copy, label, image, alt }) => (
                <article className="step-row" key={number}>
                  <div className="step-copy">
                    <div className="step-index"><span>{number}</span><Icon size={20} aria-hidden="true" /></div>
                    <h3>{title}</h3><p>{copy}</p>
                  </div>
                  <div className="step-image"><img src={image} alt={alt} /><span>{label}</span></div>
                </article>
              ))}
            </div>
          </div>
        </section>

        <section className="examples-section section-pad">
          <div className="container-shell">
            <div className="section-heading-row">
              <div><span className="eyebrow">Project examples</span><h2 className="section-title">References and drawings in one visual language.</h2></div>
              <p className="section-copy">These examples show how product imagery and technical sheets can sit together in a clear project record.</p>
            </div>
            <div className="examples-grid">
              {examples.map((example) => <figure key={example.title}><img src={example.image} alt={example.title} /><figcaption><strong>{example.title}</strong><span>{example.type}</span></figcaption></figure>)}
            </div>
          </div>
        </section>

        <section className="feature-story section-pad">
          <div className="container-shell feature-story-grid">
            <div className="feature-story-sticky"><span className="eyebrow">Three connected tools</span><h2 className="section-title">From visual reference to a usable project document.</h2></div>
            <div className="feature-story-copy">
              <p className="section-copy">CarpDraw AI is shaped around three practical tasks: turning a photo into a drawing, translating a 3D model into readable views and preparing a structured specification.</p>
              <p className="section-copy">Each workflow asks only for the information needed at that moment. The current preview demonstrates the complete interface while production conversion remains ready for a future CAD processing backend.</p>
              <div className="feature-checks">
                {["Photo to Drawing", "3D Model to Drawing", "Create Specification", "PDF, DXF and DWG preparation"].map((item) => <span key={item}><Check size={16} aria-hidden="true" />{item}</span>)}
              </div>
              <div className="feature-tags" aria-label="Workflow links">
                <Link href="/app/photo-to-drawing">Photo to Drawing</Link>
                <Link href="/app/model-to-drawing">3D Model to Drawing</Link>
                <Link href="/app/create-specification">Create Specification</Link>
              </div>
            </div>
          </div>
        </section>

        <section className="social-proof section-pad">
          <div className="container-shell">
            <div className="metrics-heading"><span className="eyebrow">Illustrative feedback</span><h2 className="section-title">A preview of how customer stories will appear.</h2><p className="feedback-disclaimer">Demo profiles and sample quotes for interface presentation. Replace them with verified customer feedback before launch.</p></div>
            <div className="metrics-row"><div><strong>3</strong><span>creation modes</span></div><div><strong>4</strong><span>supported 3D formats</span></div><div><strong>360°</strong><span>responsive interface</span></div></div>
            <div className="testimonial-grid">
              {testimonials.map((testimonial) => (
                <figure key={testimonial.name}>
                  <div className="testimonial-author"><img src={testimonial.avatar} alt="Demo profile avatar" /><div><strong>{testimonial.name}</strong><small>{testimonial.role} · demo profile</small></div></div>
                  <div className="stars" aria-label="5 out of 5">★★★★★</div>
                  <blockquote>“{testimonial.text}”</blockquote>
                </figure>
              ))}
            </div>
          </div>
        </section>

        <section className="faq-section section-pad" id="faq"><div className="container-shell faq-grid"><div><span className="eyebrow">Useful details</span><h2 className="section-title">Frequently asked questions.</h2><p className="section-copy">Key details about the current product preview and the services required for production use.</p></div><FaqList /></div></section>

        <section className="final-cta"><div className="container-shell final-cta-inner"><div className="cta-icon" aria-hidden="true"><DraftingCompass size={30} /></div><div><span className="eyebrow">Your next drawing starts here</span><h2>Bring one reference. Leave with a clearer technical story.</h2></div><Link className="primary-button" href="/app">Open workspace<ArrowRight size={17} aria-hidden="true" /></Link></div></section>
      </main>
      <Footer />
    </>
  );
}
