const currency = new Intl.NumberFormat("tr-TR", { style: "currency", currency: "TRY", maximumFractionDigits: 0 });

/** Prices are stored in kuruş. */
export function formatPrice(kurus: number): string {
  return kurus === 0 ? "Ücretsiz" : currency.format(kurus / 100);
}

export function formatDuration(seconds: number): string {
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.round((seconds % 3600) / 60);
  return hours > 0 ? `${hours} sa ${minutes} dk` : `${minutes} dk`;
}

export const levelLabel = {
  beginner: "Başlangıç",
  intermediate: "Orta",
  advanced: "İleri",
} as const;

export function formatDate(iso: string | null | undefined): string {
  return iso ? new Date(iso).toLocaleDateString("tr-TR", { day: "numeric", month: "long", year: "numeric" }) : "-";
}

export const statusLabel = { draft: "Taslak", published: "Yayında", archived: "Arşiv" } as const;
