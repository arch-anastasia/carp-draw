import type { MetadataRoute } from "next";

export default function robots(): MetadataRoute.Robots {
  const baseUrl =
    process.env.NEXT_PUBLIC_SITE_URL || "https://carpdraw.ai";
  return {
    rules: {
      userAgent: "*",
      allow: "/",
      disallow: ["/app/settings"],
    },
    sitemap: `${baseUrl}/sitemap.xml`,
  };
}
