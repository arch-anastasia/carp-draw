import Link from "next/link";
import { ArrowUpRight, Mail, Phone } from "lucide-react";
import { BrandLogo } from "./BrandLogo";

const footerGroups = [
  {
    title: "Create",
    links: [
      ["/app/photo-to-drawing", "Photo to Drawing"],
      ["/app/drawing-to-photo", "Drawing to Photo"],
      ["/app/photo-to-3d", "Photo to 3D CAD"],
    ],
  },
  {
    title: "Workspace",
    links: [
      ["/app", "Dashboard"],
      ["/app/projects", "Projects"],
      ["/app/plans", "Plans"],
      ["/app/settings", "Settings"],
    ],
  },
  {
    title: "Company",
    links: [
      ["/contact", "Contact"],
      ["/privacy", "Privacy Policy"],
      ["/#faq", "FAQ"],
    ],
  },
];

export function Footer() {
  return (
    <footer className="site-footer">
      <div className="container-shell">
        <div className="footer-top">
          <div className="footer-brand">
            <BrandLogo />
            <p>
              A focused AI workspace for product documentation, visual
              prototyping and clear technical communication.
            </p>
            <a href="mailto:arch.anastasia.ivanova@gmail.com">
              <Mail size={16} aria-hidden="true" />
              arch.anastasia.ivanova@gmail.com
            </a>
            <a href="tel:+421918135587">
              <Phone size={16} aria-hidden="true" />
              +421 918 135 587
            </a>
          </div>
          <nav className="footer-nav" aria-label="Footer navigation">
            {footerGroups.map((group) => (
              <div key={group.title}>
                <h3>{group.title}</h3>
                <ul>
                  {group.links.map(([href, label]) => (
                    <li key={href}>
                      <Link href={href}>
                        {label}
                        <ArrowUpRight size={13} aria-hidden="true" />
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </nav>
        </div>
        <div className="footer-bottom">
          <p>© 2026 CarpDraw AI. Independent interface concept.</p>
          <p>Designed for makers, studios and product teams.</p>
        </div>
      </div>
    </footer>
  );
}
