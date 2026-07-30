import type { Metadata } from "next";
import { headers } from "next/headers";
import { Geist, Geist_Mono } from "next/font/google";
import "./globals.css";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
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
      default: "CarpDraw AI — Product Images to Technical Drawings",
      template: "%s | CarpDraw AI",
    },
    description:
      "Turn product photos and sketches into clear technical drawings, product renders and editable 3D concepts with an assisted AI workflow.",
    keywords: [
      "AI technical drawing",
      "photo to CAD",
      "product drawing",
      "3D CAD concept",
      "CarpDraw AI",
    ],
    icons: {
      icon: "/brand/carpdraw-logo.png",
      shortcut: "/brand/carpdraw-logo.png",
      apple: "/brand/carpdraw-logo.png",
    },
    openGraph: {
      type: "website",
      locale: "en_US",
      siteName: "CarpDraw AI",
      title: "CarpDraw AI — From photo to production-ready drawing",
      description:
        "A focused AI workspace for technical drawings, product renders and 3D CAD concepts.",
      images: [
        {
          url: new URL("/og.png", baseUrl).toString(),
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
      images: [new URL("/og.png", baseUrl).toString()],
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
        className={`${geistSans.variable} ${geistMono.variable} antialiased`}
      >
        <a className="skip-link" href="#main-content">
          Skip to content
        </a>
        {children}
      </body>
    </html>
  );
}
