import type { Metadata } from "next";
import { PublicHeader } from "@/components/PublicHeader";
import { Footer } from "@/components/Footer";

export const metadata: Metadata = {
  title: "Privacy Policy",
  description: "Privacy information for the CarpDraw AI interface.",
};

export default function PrivacyPage() {
  return (
    <>
      <PublicHeader />
      <main id="main-content" className="legal-page section-pad">
        <article className="container-shell">
          <span className="eyebrow">Last updated August 9, 2026</span>
          <h1 className="section-title">Privacy Policy</h1>
          <p className="legal-lead">
            This policy describes the intended privacy approach for the
            CarpDraw AI interface preview. It should be reviewed by a qualified
            legal professional before a production launch.
          </p>
          <section>
            <h2>1. Information you provide</h2>
            <p>
              The interface may collect account details, contact form content
              and files you choose to process once production services are
              connected. In this preview, upload files remain within your
              browser session and contact forms are not transmitted.
            </p>
          </section>
          <section>
            <h2>2. How information is used</h2>
            <p>
              Production data would be used to provide photo-to-drawing,
              model-to-drawing and specification workflows, manage accounts, respond to support requests and
              improve service reliability. Data should not be sold.
            </p>
          </section>
          <section>
            <h2>3. File processing</h2>
            <p>
              Before enabling AI or CAD processing, the service must publish
              retention periods, processing locations, security controls and
              deletion procedures for uploaded files and generated outputs.
            </p>
          </section>
          <section>
            <h2>4. Service providers</h2>
            <p>
              Authentication, storage, analytics, email and payment providers
              may process limited data on behalf of CarpDraw AI. Each provider
              should be documented here once selected.
            </p>
          </section>
          <section>
            <h2>5. Your choices</h2>
            <p>
              Users should be able to access, correct or delete account data
              and request a copy of stored information. Cookie and analytics
              choices should be offered where required.
            </p>
          </section>
          <section>
            <h2>6. Contact</h2>
            <p>
              For privacy questions, email{" "}
              <a href="mailto:arch.anastasia.ivanova@gmail.com">
                arch.anastasia.ivanova@gmail.com
              </a>{" "}
              or call <a href="tel:+421918135587">+421 918 135 587</a>.
            </p>
          </section>
        </article>
      </main>
      <Footer />
    </>
  );
}
