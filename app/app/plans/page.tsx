import type { Metadata } from "next";
import { PlansPanel } from "./PlansPanel";

export const metadata: Metadata = {
  title: "Plans",
  description: "Choose a CarpDraw AI workspace plan.",
};

export default function PlansPage() {
  return (
    <div className="plans-page app-page-narrow">
      <div className="plans-heading">
        <span className="eyebrow">Flexible workspace</span>
        <h1>Choose your plan</h1>
        <p>Upgrade for more creation credits. Change or cancel anytime.</p>
      </div>
      <PlansPanel />
    </div>
  );
}
