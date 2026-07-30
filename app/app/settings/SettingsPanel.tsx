"use client";

import { FormEvent, useState } from "react";
import Link from "next/link";
import {
  Code2,
  Coins,
  Globe2,
  LogOut,
  Pencil,
  UserRound,
} from "lucide-react";

export function SettingsPanel() {
  const [editing, setEditing] = useState(false);
  const [name, setName] = useState("Anastasia Ivanova");
  const [language, setLanguage] = useState("English");
  const [status, setStatus] = useState("");

  const saveProfile = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!name.trim()) {
      setStatus("Name is required.");
      return;
    }
    setEditing(false);
    setStatus("Profile saved in this browser preview.");
  };

  return (
    <div className="settings-stack">
      <section className="settings-card profile-settings-card">
        <div className="settings-profile">
          <span className="settings-avatar">AI</span>
          <div>
            <strong>{name}</strong>
            <small>arch.anastasia.ivanova@gmail.com</small>
          </div>
        </div>
        {editing ? (
          <form className="settings-edit-form" onSubmit={saveProfile}>
            <div className="form-field">
              <label htmlFor="profile-name">Display name</label>
              <input
                id="profile-name"
                value={name}
                onChange={(event) => setName(event.target.value)}
                required
              />
            </div>
            <div className="settings-edit-actions">
              <button
                className="secondary-button"
                type="button"
                onClick={() => setEditing(false)}
              >
                Cancel
              </button>
              <button className="primary-button" type="submit">
                Save profile
              </button>
            </div>
          </form>
        ) : (
          <div className="settings-card-row">
            <div>
              <UserRound size={19} aria-hidden="true" />
              <span>
                <strong>Account</strong>
                <small>Your profile and preview sign-in</small>
              </span>
            </div>
            <div className="settings-row-actions">
              <button
                className="secondary-button"
                type="button"
                onClick={() => setEditing(true)}
              >
                <Pencil size={16} aria-hidden="true" />
                Edit profile
              </button>
              <Link className="secondary-button" href="/">
                <LogOut size={16} aria-hidden="true" />
                Sign out
              </Link>
            </div>
          </div>
        )}
      </section>

      <section className="settings-card settings-card-row">
        <div>
          <Coins size={19} aria-hidden="true" />
          <span>
            <strong>Credits</strong>
            <small>12 preview credits available</small>
          </span>
        </div>
        <Link className="primary-button" href="/app/plans">
          Get more credits
        </Link>
      </section>

      <section className="settings-card settings-api-card">
        <div className="settings-card-row">
          <div>
            <Code2 size={19} aria-hidden="true" />
            <span>
              <strong>API keys</strong>
              <small>For a future public API and automation workflow</small>
            </span>
          </div>
          <Link className="secondary-button" href="/contact">
            Contact us
          </Link>
        </div>
        <p>
          API credentials are not generated in this front-end preview. Connect
          an authentication service and secure backend before enabling them.
        </p>
      </section>

      <section className="settings-card language-card">
        <div>
          <Globe2 size={19} aria-hidden="true" />
          <span>
            <strong>Language</strong>
            <small>Choose the interface language</small>
          </span>
        </div>
        <select
          aria-label="Interface language"
          value={language}
          onChange={(event) => {
            setLanguage(event.target.value);
            setStatus(
              event.target.value === "English"
                ? "English is active."
                : "Russian translation is prepared for a future release.",
            );
          }}
        >
          <option>English</option>
          <option>Русский</option>
        </select>
      </section>
      {status ? (
        <p className="settings-status" role="status">
          {status}
        </p>
      ) : null}
    </div>
  );
}
