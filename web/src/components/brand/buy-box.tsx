"use client";

import Link from "next/link";
import { buttonVariants } from "@/components/ui/button";
import { useMe } from "@/hooks/use-me";
import type { Course } from "@/lib/api/types";
import { cn } from "@/lib/utils";

/** Purchase entry point; checkout itself lives in /odeme. */
export function BuyBox({ course }: { course: Pick<Course, "slug" | "price"> }) {
  const { data: me } = useMe();
  const target = `/odeme?kurs=${course.slug}`;
  const href = me ? target : `/giris?next=${encodeURIComponent(target)}`;

  return (
    <Link href={href} className={cn(buttonVariants({ size: "lg" }), "h-11 w-full")}>
      {course.price === 0 ? "Ücretsiz kaydol" : "Satın al"}
    </Link>
  );
}
