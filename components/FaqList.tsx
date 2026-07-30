"use client";

import { useState } from "react";
import { Plus } from "lucide-react";

const faqItems = [
  {
    question: "How does CarpDraw AI turn a photo into a drawing?",
    answer:
      "Upload a clear photo, set at least one known measurement and choose the output views. The interface then prepares a structured drawing concept with dimensions and export options.",
  },
  {
    question: "Which image formats can I upload?",
    answer:
      "The current interface accepts JPG, PNG, HEIC and WebP files up to 15 MB. The demo keeps files in your browser and does not send them to a server.",
  },
  {
    question: "Can I use sketches as an input?",
    answer:
      "Yes. High-contrast hand sketches and scanned line drawings work well as a starting point, especially when one reliable dimension is provided.",
  },
  {
    question: "Which outputs are planned?",
    answer:
      "The product is designed around PDF, SVG, DXF and DWG drawing workflows, plus an editable 3D concept route. Production export requires a CAD processing backend.",
  },
  {
    question: "Do I need previous CAD experience?",
    answer:
      "No. The guided steps are designed to capture the decisions that matter without exposing a full CAD interface.",
  },
  {
    question: "How can I contact the CarpDraw AI team?",
    answer:
      "Use the contact page, email arch.anastasia.ivanova@gmail.com or call +421 918 135 587.",
  },
];

export function FaqList() {
  const [openIndex, setOpenIndex] = useState<number | null>(0);

  return (
    <div className="faq-list">
      {faqItems.map((item, index) => {
        const open = openIndex === index;
        return (
          <div className={`faq-item${open ? " is-open" : ""}`} key={item.question}>
            <button
              type="button"
              aria-expanded={open}
              aria-controls={`faq-answer-${index}`}
              onClick={() => setOpenIndex(open ? null : index)}
            >
              <span>{item.question}</span>
              <Plus size={20} aria-hidden="true" />
            </button>
            <div id={`faq-answer-${index}`} hidden={!open}>
              <p>{item.answer}</p>
            </div>
          </div>
        );
      })}
    </div>
  );
}
