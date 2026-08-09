import Link from "next/link";

type BrandLogoProps = {
  href?: string;
  compact?: boolean;
};

export function BrandLogo({ href = "/", compact = false }: BrandLogoProps) {
  return (
    <Link
      className={`brand-logo${compact ? " brand-logo--compact" : ""}`}
      href={href}
      aria-label="CarpDraw AI home"
    >
      <img
        src="/brand/carpdraw-logo-orange.png"
        alt="CarpDraw AI"
        width={2000}
        height={2000}
      />
    </Link>
  );
}
