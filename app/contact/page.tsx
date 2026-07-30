import type { Metadata } from "next";
import { Mail, MapPin, Phone } from "lucide-react";
import { PublicHeader } from "@/components/PublicHeader";
import { Footer } from "@/components/Footer";
import { ContactForm } from "@/components/ContactForm";

export const metadata: Metadata = {
  title: "Contact",
  description: "Contact the CarpDraw AI team.",
};

export default function ContactPage() {
  return (
    <>
      <PublicHeader />
      <main id="main-content" className="contact-page section-pad">
        <div className="container-shell contact-grid">
          <div className="contact-copy">
            <span className="eyebrow">Contact CarpDraw AI</span>
            <h1 className="section-title">Let’s discuss your drawing workflow.</h1>
            <p className="section-copy">
              Ask about the product, a studio plan or a future technical
              integration. We will respond using the details below.
            </p>
            <div className="contact-details">
              <a href="mailto:arch.anastasia.ivanova@gmail.com">
                <Mail size={20} aria-hidden="true" />
                <span>
                  <small>Email</small>
                  arch.anastasia.ivanova@gmail.com
                </span>
              </a>
              <a href="tel:+421918135587">
                <Phone size={20} aria-hidden="true" />
                <span>
                  <small>Phone</small>
                  +421 918 135 587
                </span>
              </a>
              <div>
                <MapPin size={20} aria-hidden="true" />
                <span>
                  <small>Availability</small>
                  Central European Time
                </span>
              </div>
            </div>
          </div>
          <ContactForm />
        </div>
      </main>
      <Footer />
    </>
  );
}
