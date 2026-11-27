"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useParams } from "next/navigation";
import { useState } from "react";
import { toast } from "sonner";
import { BarChart3, ExternalLink, FileText, ImageUp, Plus, Trash2, Upload, Video } from "lucide-react";
import { PageHeader } from "@/components/panel/panel-shell";
import { QuestionsDialog } from "@/components/panel/questions-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { ApiError, apiFetch, json } from "@/lib/api/client";
import type { Course, Lesson, Section } from "@/lib/api/types";
import { formatDuration, statusLabel } from "@/lib/format";

const videoStatusLabel = { none: "Video yok", processing: "İşleniyor", ready: "Hazır", failed: "Hata" } as const;

function errorMessage(error: unknown) {
  return error instanceof ApiError ? (Object.values(error.fieldErrors())[0] ?? error.message) : "Beklenmeyen hata";
}

export default function EditCourse() {
  const { id } = useParams<{ id: string }>();
  const client = useQueryClient();
  const key = ["manage", "course", id];
  const { data: course } = useQuery({
    queryKey: key,
    queryFn: async () => (await apiFetch<{ data: Course }>(`/manage/courses/${id}`)).data,
    // Poll while a video is being processed in the queue.
    refetchInterval: (query) =>
      query.state.data?.sections?.some((s) => s.lessons?.some((l) => l.video_status === "processing")) ? 3000 : false,
  });

  const refresh = () => client.invalidateQueries({ queryKey: key });

  const mutation = useMutation({
    mutationFn: ({ path, method = "POST", body }: { path: string; method?: string; body?: BodyInit }) =>
      apiFetch(path, { method, body }),
    onSuccess: () => refresh(),
    onError: (error) => toast.error(errorMessage(error)),
  });

  if (!course) return <p className="text-muted-foreground">Yükleniyor…</p>;

  const published = course.status === "published";

  return (
    <>
      <PageHeader
        title={course.title}
        description={`Durum: ${statusLabel[course.status]}`}
        action={
          <div className="flex gap-2">
            <Link href={`/egitmen/kurslar/${course.id}/rapor`}>
              <Button variant="outline">
                <BarChart3 /> Rapor
              </Button>
            </Link>
            {published && (
              <Link href={`/kurslar/${course.slug}`} target="_blank">
                <Button variant="outline">
                  <ExternalLink /> Vitrinde gör
                </Button>
              </Link>
            )}
            <Button
              variant={published ? "outline" : "default"}
              onClick={() =>
                mutation.mutate(
                  { path: `/manage/courses/${course.id}/${published ? "unpublish" : "publish"}` },
                  { onSuccess: () => toast.success(published ? "Yayından kaldırıldı" : "Kurs yayında") },
                )
              }
            >
              {published ? "Yayından kaldır" : "Yayınla"}
            </Button>
          </div>
        }
      />

      <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
        <div className="space-y-4">
          {course.sections?.map((section) => (
            <SectionCard key={section.id} section={section} mutate={mutation.mutate} />
          ))}
          <AddSection onAdd={(title) => mutation.mutate({ path: `/manage/courses/${course.id}/sections`, body: json({ title }) })} />
        </div>
        <CourseDetails course={course} onSaved={refresh} />
      </div>
    </>
  );
}

type Mutate = (variables: { path: string; method?: string; body?: BodyInit }) => void;

function SectionCard({ section, mutate }: { section: Section; mutate: Mutate }) {
  return (
    <div className="rounded-xl border bg-card">
      <div className="flex items-center justify-between border-b px-4 py-3">
        <h3 className="font-semibold">{section.title}</h3>
        <div className="flex gap-1">
          <LessonDialog sectionId={section.id} mutate={mutate} />
          <Button
            variant="ghost"
            size="icon-sm"
            aria-label="Bölümü sil"
            onClick={() => confirm("Bölüm ve dersleri silinsin mi?") && mutate({ path: `/manage/sections/${section.id}`, method: "DELETE" })}
          >
            <Trash2 />
          </Button>
        </div>
      </div>
      <ul className="divide-y">
        {section.lessons?.map((lesson) => (
          <LessonRow key={lesson.id} lesson={lesson} mutate={mutate} />
        ))}
        {section.lessons?.length === 0 && <li className="px-4 py-3 text-sm text-muted-foreground">Bu bölümde ders yok.</li>}
      </ul>
    </div>
  );
}

function LessonRow({ lesson, mutate }: { lesson: Lesson; mutate: Mutate }) {
  const Icon = lesson.type === "video" ? Video : FileText;

  return (
    <li className="flex flex-wrap items-center gap-3 px-4 py-3 text-sm">
      <Icon className="size-4 text-muted-foreground" />
      <span className="flex-1">{lesson.title}</span>
      {lesson.is_preview && <Badge variant="outline">Önizleme</Badge>}
      {lesson.type === "video" && (
        <Badge variant={lesson.video_status === "ready" ? "secondary" : "outline"}>
          {videoStatusLabel[lesson.video_status]}
          {lesson.video_status === "ready" && ` · ${formatDuration(lesson.duration_seconds)}`}
        </Badge>
      )}
      {lesson.type === "video" && (
        <label className="cursor-pointer text-primary hover:underline">
          <Upload className="mr-1 inline size-4" />
          Video yükle
          <input
            type="file"
            accept="video/mp4,video/webm,video/quicktime"
            className="sr-only"
            onChange={(event) => {
              const file = event.target.files?.[0];
              if (!file) return;
              const body = new FormData();
              body.append("video", file);
              mutate({ path: `/manage/lessons/${lesson.id}/video`, body });
              toast.info("Video yüklendi, kuyrukta işleniyor.");
            }}
          />
        </label>
      )}
      <QuestionsDialog lesson={lesson} />
      <Button
        variant="ghost"
        size="icon-xs"
        aria-label="Dersi sil"
        onClick={() => confirm("Ders silinsin mi?") && mutate({ path: `/manage/lessons/${lesson.id}`, method: "DELETE" })}
      >
        <Trash2 />
      </Button>
    </li>
  );
}

function LessonDialog({ sectionId, mutate }: { sectionId: number; mutate: Mutate }) {
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ title: "", type: "video", content: "", transcript: "", is_preview: false });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger render={<Button variant="outline" size="sm" />}>
        <Plus /> Ders
      </DialogTrigger>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Yeni ders</DialogTitle>
        </DialogHeader>
        <form
          className="space-y-4"
          onSubmit={(event) => {
            event.preventDefault();
            mutate({ path: `/manage/sections/${sectionId}/lessons`, body: json(form) });
            setOpen(false);
            setForm({ title: "", type: "video", content: "", transcript: "", is_preview: false });
          }}
        >
          <div className="space-y-1.5">
            <Label htmlFor="lesson-title">Başlık</Label>
            <Input id="lesson-title" required value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="lesson-type">Tür</Label>
            <select
              id="lesson-type"
              className="h-8 w-full rounded-lg border bg-background px-2 text-sm"
              value={form.type}
              onChange={(e) => setForm({ ...form, type: e.target.value })}
            >
              <option value="video">Video</option>
              <option value="text">Metin</option>
              <option value="pdf">PDF</option>
            </select>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="lesson-content">Ders metni</Label>
            <Textarea id="lesson-content" rows={4} value={form.content} onChange={(e) => setForm({ ...form, content: e.target.value })} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="lesson-transcript">Transkript (AI soru üretimi için)</Label>
            <Textarea
              id="lesson-transcript"
              rows={3}
              value={form.transcript}
              onChange={(e) => setForm({ ...form, transcript: e.target.value })}
            />
          </div>
          <label className="flex items-center gap-2 text-sm">
            <Switch checked={form.is_preview} onCheckedChange={(checked) => setForm({ ...form, is_preview: checked })} />
            Ücretsiz önizleme
          </label>
          <Button type="submit" className="w-full">
            Dersi ekle
          </Button>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function AddSection({ onAdd }: { onAdd: (title: string) => void }) {
  const [title, setTitle] = useState("");

  return (
    <form
      className="flex gap-2"
      onSubmit={(event) => {
        event.preventDefault();
        if (!title.trim()) return;
        onAdd(title.trim());
        setTitle("");
      }}
    >
      <Input placeholder="Yeni bölüm adı" value={title} onChange={(e) => setTitle(e.target.value)} />
      <Button type="submit" variant="outline">
        <Plus /> Bölüm ekle
      </Button>
    </form>
  );
}

function CourseDetails({ course, onSaved }: { course: Course; onSaved: () => void }) {
  const [form, setForm] = useState({
    title: course.title,
    summary: course.summary ?? "",
    description: course.description ?? "",
    category: course.category ?? "",
    price: String(course.price / 100),
  });

  const save = useMutation({
    mutationFn: () =>
      apiFetch(`/manage/courses/${course.id}`, {
        method: "PATCH",
        body: json({ ...form, price: Math.round(Number(form.price) * 100) }),
      }),
    onSuccess: () => {
      toast.success("Kaydedildi");
      onSaved();
    },
    onError: (error) => toast.error(errorMessage(error)),
  });

  const cover = useMutation({
    mutationFn: (file: File) => {
      const body = new FormData();
      body.append("cover", file);
      return apiFetch(`/manage/courses/${course.id}/cover`, { method: "POST", body });
    },
    onSuccess: () => {
      toast.success("Kapak güncellendi");
      onSaved();
    },
    onError: (error) => toast.error(errorMessage(error)),
  });

  return (
    <form
      className="space-y-4 rounded-xl border bg-card p-4"
      onSubmit={(event) => {
        event.preventDefault();
        save.mutate();
      }}
    >
      <h3 className="font-semibold">Kurs bilgileri</h3>
      {course.cover_url && (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={course.cover_url} alt="" className="aspect-video w-full rounded-lg object-cover" />
      )}
      <label className="flex cursor-pointer items-center gap-2 text-sm text-primary">
        <ImageUp className="size-4" /> Kapak görseli yükle
        <input
          type="file"
          accept="image/png,image/jpeg,image/webp"
          className="sr-only"
          onChange={(e) => e.target.files?.[0] && cover.mutate(e.target.files[0])}
        />
      </label>
      {(
        [
          ["title", "Başlık"],
          ["category", "Kategori"],
          ["price", "Fiyat (TL)"],
        ] as const
      ).map(([field, label]) => (
        <div key={field} className="space-y-1.5">
          <Label htmlFor={field}>{label}</Label>
          <Input id={field} value={form[field]} onChange={(e) => setForm({ ...form, [field]: e.target.value })} />
        </div>
      ))}
      <div className="space-y-1.5">
        <Label htmlFor="summary">Kısa açıklama</Label>
        <Textarea id="summary" rows={2} value={form.summary} onChange={(e) => setForm({ ...form, summary: e.target.value })} />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="description">Açıklama</Label>
        <Textarea
          id="description"
          rows={5}
          value={form.description}
          onChange={(e) => setForm({ ...form, description: e.target.value })}
        />
      </div>
      <Button type="submit" className="w-full" disabled={save.isPending}>
        Kaydet
      </Button>
    </form>
  );
}
