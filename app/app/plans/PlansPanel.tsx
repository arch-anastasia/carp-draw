"use client";

import { useState } from "react";
import { Check, X } from "lucide-react";

const plans = [
  {
    name: "Maker",
    price: "€19",
    description: "For independent makers and small product studies.",
    features: [
      [true, "40 creation credits / month"],
      [true, "PDF and SVG project briefs"],
      [true, "Drawing to render previews"],
      [false, "Photo to 3D CAD processing"],
      [false, "Team review tools"],
    ] as const,
  },
  {
    name: "Studio",
    price: "€89",
    description: "For studios running multiple technical workflows.",
    popular: true,
    features: [
      [true, "250 creation credits / month"],
      [true, "PDF, SVG and DXF workflows"],
      [true, "Drawing to render previews"],
      [true, "Photo to 3D CAD processing"],
      [true, "Priority review and support"],
    ] as const,
  },
];

export function PlansPanel() {
  const [notice, setNotice] = useState("");

  return (
    <>
      <div className="plans-grid">
        {plans.map((plan) => (
          <article
            className={`plan-card${plan.popular ? " is-popular" : ""}`}
            key={plan.name}
          >
            {plan.popular ? <span className="popular-label">Most popular</span> : null}
            <h2>{plan.name}</h2>
            <p>{plan.description}</p>
            <div className="plan-price">
              <strong>{plan.price}</strong>
              <span>/ month</span>
            </div>
            <ul>
              {plan.features.map(([included, feature]) => (
                <li className={included ? "" : "is-muted"} key={feature}>
                  {included ? (
                    <Check size={17} aria-hidden="true" />
                  ) : (
                    <X size={17} aria-hidden="true" />
                  )}
                  {feature}
                </li>
              ))}
            </ul>
            <button
              className={plan.popular ? "primary-button" : "secondary-button"}
              type="button"
              onClick={() =>
                setNotice(
                  `${plan.name} selected. Connect Stripe or another payment service to complete checkout.`,
                )
              }
            >
              Choose {plan.name}
            </button>
          </article>
        ))}
      </div>
      {notice ? (
        <p className="plan-notice" role="status">
          {notice}
        </p>
      ) : null}
    </>
  );
}
