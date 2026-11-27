const BASE_DOMAIN = process.env.TENANT_BASE_DOMAIN ?? "localhost";
const RESERVED = new Set(["www", "admin", "api", "app", "mail"]);

/** "kodakademi.localhost:3300" -> "kodakademi"; platform host -> null. */
export function tenantFromHost(host: string | null): string | null {
  if (!host) return null;
  const hostname = host.split(":")[0].toLowerCase();
  const suffix = `.${BASE_DOMAIN}`;
  if (!hostname.endsWith(suffix)) return null;
  const label = hostname.slice(0, -suffix.length);
  if (!label || label.includes(".") || RESERVED.has(label)) return null;
  return label;
}
