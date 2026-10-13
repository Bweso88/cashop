import type { Metadata, Viewport } from "next";
import { Inter } from "next/font/google";
import "./globals.css";

const inter = Inter({ subsets: ["latin"], variable: "--font-inter" });

export const metadata: Metadata = {
  title: { default: "Cashop", template: "%s · Cashop" },
  description: "Envoyez, recevez et gérez votre argent, partout.",
  robots: { index: false, follow: false },
};

export const viewport: Viewport = {
  themeColor: "#0a0d14",
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="fr" className={inter.variable}>
      <body className="cashop-glow min-h-dvh">{children}</body>
    </html>
  );
}
