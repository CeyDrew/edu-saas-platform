import type { Metadata } from "next";
import { Inter, Lora, Nunito } from "next/font/google";
import { AppProviders } from "@/components/providers/app-providers";
import "./globals.css";

const inter = Inter({ variable: "--font-inter", subsets: ["latin", "latin-ext"] });
const lora = Lora({ variable: "--font-lora", subsets: ["latin", "latin-ext"] });
const nunito = Nunito({ variable: "--font-nunito", subsets: ["latin", "latin-ext"] });

export const metadata: Metadata = {
  title: { default: "Edu SaaS", template: "%s | Edu SaaS" },
  description: "Eğitim markaları için çok kiracılı, yapay zekâ destekli öğrenme platformu.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="tr" className={`${inter.variable} ${lora.variable} ${nunito.variable} h-full antialiased`}>
      <body className="flex min-h-full flex-col">
        <AppProviders>{children}</AppProviders>
      </body>
    </html>
  );
}
