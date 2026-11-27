export const PORT = process.env.E2E_PORT ?? "3300";

export const brandUrl = (slug: string, path = "/") => `http://${slug}.localhost:${PORT}${path}`;
export const platformUrl = (path = "/") => `http://localhost:${PORT}${path}`;
