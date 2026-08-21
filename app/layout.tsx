import type { Metadata } from "next";
import { headers } from "next/headers";
import { IBM_Plex_Mono, Inter } from "next/font/google";
import "./globals.css";

const inter = Inter({
  variable: "--font-interface-sans",
  subsets: ["latin"],
});

const plexMono = IBM_Plex_Mono({
  variable: "--font-interface-mono",
  subsets: ["latin"],
  weight: ["400", "500"],
});

export async function generateMetadata(): Promise<Metadata> {
  let baseUrl = new URL(
    process.env.NEXT_PUBLIC_SITE_URL || "https://carpdraw.ai",
  );

  try {
    const requestHeaders = await headers();
    const host =
      requestHeaders.get("x-forwarded-host") || requestHeaders.get("host");
    const protocol = requestHeaders.get("x-forwarded-proto") || "https";
    if (host) baseUrl = new URL(`${protocol}://${host}`);
  } catch {
    // Static builds use the configured production URL.
  }

  return {
    metadataBase: baseUrl,
    title: {
      default: "CarpDraw AI — References to Technical Drawings",
      template: "%s | CarpDraw AI",
    },
    description:
      "Create technical drawings from photos and 3D models, then prepare structured product specifications in one guided workspace.",
    keywords: [
      "AI technical drawing",
      "photo to CAD",
      "product drawing",
      "3D model to drawing",
      "product specification",
      "CarpDraw AI",
    ],
    icons: {
      icon: "/favicon.png",
      shortcut: "/favicon.png",
      apple: "/brand/carpdraw-mark-refined.png",
    },
    openGraph: {
      type: "website",
      locale: "en_US",
      siteName: "CarpDraw AI",
      title: "CarpDraw AI — From reference to production-ready drawing",
      description:
        "A focused workspace for photo-to-drawing, 3D-to-drawing and product specification workflows.",
      images: [
        {
          url: new URL("/og-v2.png", baseUrl).toString(),
          width: 1536,
          height: 1024,
          alt: "CarpDraw AI technical drawing workspace",
        },
      ],
    },
    twitter: {
      card: "summary_large_image",
      title: "CarpDraw AI",
      description:
        "Turn a product photo into a clear, editable technical workflow.",
      images: [new URL("/og-v2.png", baseUrl).toString()],
    },
  };
}

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body
        className={`${inter.variable} ${plexMono.variable} antialiased`}
      >
        <a className="skip-link" href="#main-content">
          Skip to content
        </a>
        {children}
      </body>
    </html>
  );
}
