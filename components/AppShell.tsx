"use client";

import { ReactNode, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import {
  Box,
  ChevronDown,
  Coins,
  Folder,
  Home,
  FileSpreadsheet,
  LogOut,
  Menu,
  PanelLeftClose,
  PenTool,
  Settings,
  Sparkles,
} from "lucide-react";
import { BrandLogo } from "./BrandLogo";

const navItems = [
  { href: "/app", label: "Home", icon: Home },
  {
    href: "/app/photo-to-drawing",
    label: "Photo to Drawing",
    icon: Sparkles,
  },
  {
    href: "/app/model-to-drawing",
    label: "3D Model to Drawing",
    icon: Box,
  },
  {
    href: "/app/create-specification",
    label: "Create Specification",
    icon: FileSpreadsheet,
  },
];

const pathLabels: Record<string, string> = {
  "/app": "Home",
  "/app/photo-to-drawing": "Photo to Drawing",
  "/app/model-to-drawing": "3D Model to Drawing",
  "/app/create-specification": "Create Specification",
  "/app/projects": "Projects",
  "/app/settings": "Settings",
  "/app/plans": "Plans",
};

export function AppShell({ children }: { children: ReactNode }) {
  const pathname = usePathname();
  const router = useRouter();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [profileOpen, setProfileOpen] = useState(false);
  const profileRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    setSidebarOpen(false);
    setProfileOpen(false);
  }, [pathname]);

  useEffect(() => {
    const handleClick = (event: MouseEvent) => {
      if (
        profileRef.current &&
        !profileRef.current.contains(event.target as Node)
      ) {
        setProfileOpen(false);
      }
    };
    document.addEventListener("mousedown", handleClick);
    return () => document.removeEventListener("mousedown", handleClick);
  }, []);

  const isActive = (href: string) =>
    href === "/app" ? pathname === href : pathname.startsWith(href);

  return (
    <div className="app-shell">
      <aside className={`app-sidebar${sidebarOpen ? " is-open" : ""}`}>
        <div className="app-sidebar-brand">
          <BrandLogo href="/" compact />
          <button
            className="sidebar-close"
            type="button"
            onClick={() => setSidebarOpen(false)}
            aria-label="Close navigation"
          >
            <PanelLeftClose size={20} />
          </button>
        </div>
        <nav aria-label="Workspace">
          <div className="app-nav-group">
            {navItems.map(({ href, label, icon: Icon }) => (
              <Link
                className={isActive(href) ? "is-active" : ""}
                href={href}
                key={href}
              >
                <Icon size={19} aria-hidden="true" />
                {label}
              </Link>
            ))}
          </div>
          <div className="app-nav-group">
            <p>Projects</p>
            <Link
              className={isActive("/app/projects") ? "is-active" : ""}
              href="/app/projects"
            >
              <Folder size={19} aria-hidden="true" />
              All Projects
            </Link>
          </div>
        </nav>
        <Link className="primary-button app-credits-button" href="/app/plans">
          <Coins size={17} aria-hidden="true" />
          Get more credits
        </Link>
      </aside>
      {sidebarOpen ? (
        <button
          className="sidebar-backdrop"
          type="button"
          aria-label="Close navigation overlay"
          onClick={() => setSidebarOpen(false)}
        />
      ) : null}
      <div className="app-main-area">
        <header className="app-topbar">
          <div className="app-topbar-title">
            <button
              className="app-menu-trigger"
              type="button"
              aria-label="Open navigation"
              onClick={() => setSidebarOpen(true)}
            >
              <Menu size={22} />
            </button>
            <PenTool size={18} aria-hidden="true" />
            <span>{pathLabels[pathname] || "Workspace"}</span>
          </div>
          <div className="profile-menu" ref={profileRef}>
            <button
              className="profile-trigger"
              type="button"
              aria-expanded={profileOpen}
              aria-haspopup="menu"
              onClick={() => setProfileOpen((current) => !current)}
            >
              <span>AI</span>
              <ChevronDown size={14} aria-hidden="true" />
            </button>
            {profileOpen ? (
              <div className="profile-popover" role="menu">
                <div className="profile-summary">
                  <span>AI</span>
                  <div>
                    <strong>Anastasia Ivanova</strong>
                    <small>arch.anastasia.ivanova@gmail.com</small>
                  </div>
                </div>
                <Link href="/app/settings" role="menuitem">
                  <Settings size={17} aria-hidden="true" />
                  Settings
                </Link>
                <Link href="/app/plans" role="menuitem">
                  <Coins size={17} aria-hidden="true" />
                  Plans
                </Link>
                <button
                  type="button"
                  role="menuitem"
                  onClick={() => router.push("/")}
                >
                  <LogOut size={17} aria-hidden="true" />
                  Sign out
                </button>
              </div>
            ) : null}
          </div>
        </header>
        <main id="main-content" className="app-content">
          {children}
        </main>
      </div>
    </div>
  );
}
