import type { Metadata } from "next";
import { SettingsPanel } from "./SettingsPanel";

export const metadata: Metadata = {
  title: "Settings",
  description: "Manage CarpDraw AI workspace preferences.",
};

export default function SettingsPage() {
  return (
    <div className="settings-page app-page-narrow">
      <div className="app-page-heading">
        <div>
          <span className="eyebrow">Workspace preferences</span>
          <h1>Settings</h1>
          <p>Manage your profile, credits and interface preferences.</p>
        </div>
      </div>
      <SettingsPanel />
    </div>
  );
}
