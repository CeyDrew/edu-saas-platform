import Link from "next/link";
import { buttonVariants } from "@/components/ui/button";
import type { Course } from "@/lib/api/types";
import { cn } from "@/lib/utils";

/** Purchase entry point; /odeme sends guests to the login page first. */
export function BuyBox({ course }: { course: Pick<Course, "slug" | "price"> }) {
  return (
    <Link href={`/odeme?kurs=${course.slug}`} className={cn(buttonVariants({ size: "lg" }), "h-11 w-full")}>
      {course.price === 0 ? "Ücretsiz kaydol" : "Satın al"}
    </Link>
  );
}
