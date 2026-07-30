"use client";

import { useState } from "react";
import Link from "next/link";
import {
  Box,
  ChevronDown,
  FileImage,
  Menu,
  PenTool,
  X,
} from "lucide-react";
import { BrandLogo } from "./BrandLogo";
import { AuthModal } from "./AuthModal";

const productLinks = [
  {
    href: "/app/photo-to-drawing",
    label: "Photo to Drawing",
    copy: "Draft clear technical views",
    icon: PenTool,
  },
  {
    href: "/app/drawing-to-photo",
    label: "Drawing to Photo",
    copy: "Create presentation-ready renders",
    icon: FileImage,
  },
  {
    href: "/app/photo-to-3d",
    label: "Photo to 3D CAD",
    copy: "Prepare an editable model concept",
    icon: Box,
  },
];

export function PublicHeader() {
  const [menuOpen, setMenuOpen] = useState(false);
  const [productsOpen, setProductsOpen] = useState(false);
  const [authOpen, setAuthOpen] = useState(false);

  return (
    <>
      <header className="public-header">
        <div className="container-shell public-header-inner">
          <BrandLogo />
          <nav className="public-nav" aria-label="Primary navigation">
            <div className="nav-dropdown">
              <button
                className="nav-link"
                type="button"
                aria-expanded={productsOpen}
                onClick={() => setProductsOpen((current) => !current)}
              >
                Product
                <ChevronDown size={15} aria-hidden="true" />
              </button>
              {productsOpen ? (
                <div className="product-menu">
                  {productLinks.map(({ href, label, copy, icon: Icon }) => (
                    <Link
                      href={href}
                      key={href}
                      onClick={() => setProductsOpen(false)}
                    >
                      <Icon size={18} aria-hidden="true" />
                      <span>
                        <strong>{label}</strong>
                        <small>{copy}</small>
                      </span>
                    </Link>
                  ))}
                </div>
              ) : null}
            </div>
            <Link className="nav-link" href="/#workflow">
              Workflow
            </Link>
            <Link className="nav-link" href="/app/plans">
              Pricing
            </Link>
            <Link className="nav-link" href="/contact">
              Contact
            </Link>
          </nav>
          <div className="public-actions">
            <button
              className="text-button header-sign-in"
              type="button"
              onClick={() => setAuthOpen(true)}
            >
              Sign in
            </button>
            <Link className="primary-button header-cta" href="/app">
              Start free
            </Link>
            <button
              className="mobile-menu-trigger"
              type="button"
              aria-label={menuOpen ? "Close navigation" : "Open navigation"}
              aria-expanded={menuOpen}
              onClick={() => setMenuOpen((current) => !current)}
            >
              {menuOpen ? <X size={22} /> : <Menu size={22} />}
            </button>
          </div>
        </div>
        {menuOpen ? (
          <nav className="mobile-nav" aria-label="Mobile navigation">
            {productLinks.map(({ href, label }) => (
              <Link
                href={href}
                key={href}
                onClick={() => setMenuOpen(false)}
              >
                {label}
              </Link>
            ))}
            <Link href="/#workflow" onClick={() => setMenuOpen(false)}>
              Workflow
            </Link>
            <Link href="/app/plans" onClick={() => setMenuOpen(false)}>
              Pricing
            </Link>
            <Link href="/contact" onClick={() => setMenuOpen(false)}>
              Contact
            </Link>
            <button
              type="button"
              onClick={() => {
                setMenuOpen(false);
                setAuthOpen(true);
              }}
            >
              Sign in
            </button>
          </nav>
        ) : null}
      </header>
      <AuthModal open={authOpen} onClose={() => setAuthOpen(false)} />
    </>
  );
}
