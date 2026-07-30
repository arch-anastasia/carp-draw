"use client";

import { FormEvent, useEffect, useState } from "react";
import { ArrowRight, LockKeyhole, Mail, X } from "lucide-react";
import { TechnicalVisual } from "./TechnicalVisual";

type AuthModalProps = {
  open: boolean;
  onClose: () => void;
};

export function AuthModal({ open, onClose }: AuthModalProps) {
  const [email, setEmail] = useState("");
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (!open) return;
    document.body.classList.add("modal-open");
    const handleKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") onClose();
    };
    window.addEventListener("keydown", handleKey);
    return () => {
      document.body.classList.remove("modal-open");
      window.removeEventListener("keydown", handleKey);
    };
  }, [open, onClose]);

  if (!open) return null;

  const handleProvider = (provider: "Google" | "Apple") => {
    setError("");
    setMessage(
      `${provider} sign-in is ready for an OAuth connection. Use email to preview the current flow.`,
    );
  };

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (submitting) return;
    const cleanEmail = email.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(cleanEmail)) {
      setError("Enter a valid email address.");
      setMessage("");
      return;
    }
    setSubmitting(true);
    setError("");
    window.setTimeout(() => {
      setSubmitting(false);
      setMessage(`A secure preview link has been prepared for ${cleanEmail}.`);
    }, 650);
  };

  return (
    <div
      className="auth-backdrop"
      role="presentation"
      onMouseDown={(event) => {
        if (event.currentTarget === event.target) onClose();
      }}
    >
      <section
        className="auth-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="auth-title"
      >
        <button
          className="auth-close"
          type="button"
          onClick={onClose}
          aria-label="Close sign-in dialog"
        >
          <X size={19} aria-hidden="true" />
        </button>
        <div className="auth-visual">
          <TechnicalVisual label="CARPDRAW / A3" />
          <div className="auth-visual-copy">
            <span>Photo input</span>
            <ArrowRight size={17} aria-hidden="true" />
            <span>Technical output</span>
          </div>
        </div>
        <div className="auth-form-panel">
          <div className="auth-mark" aria-hidden="true">
            CD
          </div>
          <h2 id="auth-title">Welcome to CarpDraw AI</h2>
          <p>Sign in to create your first technical concept.</p>
          <div className="auth-providers">
            <button
              className="secondary-button"
              type="button"
              onClick={() => handleProvider("Google")}
            >
              <span className="provider-g" aria-hidden="true">
                G
              </span>
              Continue with Google
            </button>
            <button
              className="secondary-button"
              type="button"
              onClick={() => handleProvider("Apple")}
            >
              <span aria-hidden="true">●</span>
              Continue with Apple
            </button>
          </div>
          <div className="auth-divider">
            <span />
            <small>or</small>
            <span />
          </div>
          <form onSubmit={handleSubmit} noValidate>
            <div className="form-field">
              <label htmlFor="auth-email">Email</label>
              <div className="input-with-icon">
                <Mail size={18} aria-hidden="true" />
                <input
                  id="auth-email"
                  type="email"
                  autoComplete="email"
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                  placeholder="you@example.com"
                  aria-describedby={error ? "auth-error" : undefined}
                  aria-invalid={Boolean(error)}
                  required
                  autoFocus
                />
              </div>
            </div>
            {error ? (
              <p id="auth-error" className="error-text" role="alert">
                {error}
              </p>
            ) : null}
            <button
              className="primary-button auth-submit"
              type="submit"
              disabled={submitting}
            >
              {submitting ? "Preparing..." : "Continue with email"}
            </button>
          </form>
          {message ? (
            <p className="auth-message" role="status">
              {message}
            </p>
          ) : null}
          <div className="auth-secure">
            <LockKeyhole size={14} aria-hidden="true" />
            Your data stays private in this interface preview.
          </div>
        </div>
      </section>
    </div>
  );
}
