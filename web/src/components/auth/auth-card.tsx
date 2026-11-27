import Link from "next/link";

export function AuthCard({
  title,
  subtitle,
  footer,
  children,
}: {
  title: string;
  subtitle?: string;
  footer?: { text: string; href: string; label: string };
  children: React.ReactNode;
}) {
  return (
    <div className="mx-auto w-full max-w-md px-4 py-16">
      <div className="space-y-6 rounded-2xl border bg-card p-8 shadow-sm">
        <div className="space-y-1">
          <h1 className="font-heading text-2xl font-bold">{title}</h1>
          {subtitle && <p className="text-sm text-muted-foreground">{subtitle}</p>}
        </div>
        {children}
      </div>
      {footer && (
        <p className="mt-6 text-center text-sm text-muted-foreground">
          {footer.text}{" "}
          <Link href={footer.href} className="font-medium text-primary hover:underline">
            {footer.label}
          </Link>
        </p>
      )}
    </div>
  );
}
