export type Theme = {
  primary?: string;
  accent?: string;
  font?: "sans" | "serif" | "rounded";
  hero?: "gradient" | "split" | "stats";
};

export type Tenant = {
  slug: string;
  name: string;
  tagline: string | null;
  theme: Theme;
};

export type PlatformTenant = Tenant & {
  id: number;
  plan: string;
  commission_rate: number;
  is_active: boolean;
  users_count?: number;
  created_at: string;
};

export type RoleName = "platform-admin" | "brand-admin" | "instructor" | "student";

export type User = {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  roles: RoleName[];
  tenant?: string | null;
};

export type Lesson = {
  id: number;
  section_id: number;
  title: string;
  type: "video" | "text" | "pdf";
  duration_seconds: number;
  position: number;
  is_preview: boolean;
  video_status: "none" | "processing" | "ready" | "failed";
  content?: string | null;
  transcript?: string | null;
  video_url?: string | null;
  thumbnail_url?: string | null;
};

export type Section = {
  id: number;
  title: string;
  position: number;
  lessons?: Lesson[];
};

export type Course = {
  id: number;
  slug: string;
  title: string;
  summary: string | null;
  description?: string | null;
  category: string | null;
  level: "beginner" | "intermediate" | "advanced";
  price: number;
  compare_at_price: number | null;
  included_in_subscription: boolean;
  status: "draft" | "published" | "archived";
  published_at: string | null;
  cover_url?: string | null;
  instructor?: { id: number; name: string };
  lessons_count?: number;
  duration_seconds?: number;
  sections?: Section[];
};

export type Paginated<T> = {
  data: T[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
  links: { next: string | null; prev: string | null };
};

export type ApiErrorBody = { message?: string; errors?: Record<string, string[]> };

export type MyCourse = {
  source: "purchase" | "free" | "subscription";
  course: Course;
  progress?: { completed_lessons: number; percent: number; next_lesson_id: number | null };
};

export type Question = {
  id: number;
  lesson_id: number;
  stem: string;
  options: string[];
  video_timestamp: number | null;
  correct_index?: number;
  explanation?: string | null;
  source?: "manual" | "ai";
  status?: "draft" | "approved" | "rejected";
};
