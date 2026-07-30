"use client";

import { FormEvent, useState } from "react";
import { ArrowRight, CheckCircle2 } from "lucide-react";

type ContactErrors = Partial<
  Record<"name" | "email" | "subject" | "message", string>
>;

export function ContactForm() {
  const [errors, setErrors] = useState<ContactErrors>({});
  const [submitting, setSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState(false);

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (submitting) return;
    const form = new FormData(event.currentTarget);
    const values = {
      name: String(form.get("name") || "").trim(),
      email: String(form.get("email") || "").trim(),
      subject: String(form.get("subject") || "").trim(),
      message: String(form.get("message") || "").trim(),
    };
    const nextErrors: ContactErrors = {};
    if (values.name.length < 2) nextErrors.name = "Enter your name.";
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.email)) {
      nextErrors.email = "Enter a valid email address.";
    }
    if (!values.subject) nextErrors.subject = "Choose a topic.";
    if (values.message.length < 20) {
      nextErrors.message = "Tell us a little more (at least 20 characters).";
    }
    setErrors(nextErrors);
    if (Object.keys(nextErrors).length) return;

    setSubmitting(true);
    window.setTimeout(() => {
      setSubmitting(false);
      setSubmitted(true);
    }, 750);
  };

  if (submitted) {
    return (
      <div className="contact-success" role="status">
        <CheckCircle2 size={42} aria-hidden="true" />
        <h2>Message prepared</h2>
        <p>
          The form flow is working. Connect an email service to deliver
          submissions to arch.anastasia.ivanova@gmail.com.
        </p>
        <button
          className="secondary-button"
          type="button"
          onClick={() => {
            setSubmitted(false);
            setErrors({});
          }}
        >
          Send another message
        </button>
      </div>
    );
  }

  return (
    <form className="contact-form" onSubmit={handleSubmit} noValidate>
      <div className="contact-form-row">
        <div className="form-field">
          <label htmlFor="contact-name">Name</label>
          <input
            id="contact-name"
            name="name"
            autoComplete="name"
            aria-invalid={Boolean(errors.name)}
            aria-describedby={errors.name ? "contact-name-error" : undefined}
            required
          />
          {errors.name ? (
            <p id="contact-name-error" className="error-text" role="alert">
              {errors.name}
            </p>
          ) : null}
        </div>
        <div className="form-field">
          <label htmlFor="contact-email">Email</label>
          <input
            id="contact-email"
            name="email"
            type="email"
            autoComplete="email"
            aria-invalid={Boolean(errors.email)}
            aria-describedby={errors.email ? "contact-email-error" : undefined}
            required
          />
          {errors.email ? (
            <p id="contact-email-error" className="error-text" role="alert">
              {errors.email}
            </p>
          ) : null}
        </div>
      </div>
      <div className="form-field">
        <label htmlFor="contact-subject">Topic</label>
        <select
          id="contact-subject"
          name="subject"
          defaultValue=""
          aria-invalid={Boolean(errors.subject)}
          required
        >
          <option value="" disabled>
            Select a topic
          </option>
          <option value="product">Product question</option>
          <option value="studio">Studio plan</option>
          <option value="integration">API or integration</option>
          <option value="other">Other</option>
        </select>
        {errors.subject ? (
          <p className="error-text" role="alert">
            {errors.subject}
          </p>
        ) : null}
      </div>
      <div className="form-field">
        <label htmlFor="contact-message">Message</label>
        <textarea
          id="contact-message"
          name="message"
          rows={7}
          placeholder="Tell us about your workflow..."
          aria-invalid={Boolean(errors.message)}
          aria-describedby={
            errors.message ? "contact-message-error" : undefined
          }
          required
        />
        {errors.message ? (
          <p id="contact-message-error" className="error-text" role="alert">
            {errors.message}
          </p>
        ) : null}
      </div>
      <button className="primary-button" type="submit" disabled={submitting}>
        {submitting ? "Preparing message..." : "Send message"}
        {!submitting ? <ArrowRight size={16} aria-hidden="true" /> : null}
      </button>
      <p className="form-disclaimer">
        Demo submission only. No data is transmitted until an email provider is
        connected.
      </p>
    </form>
  );
}
