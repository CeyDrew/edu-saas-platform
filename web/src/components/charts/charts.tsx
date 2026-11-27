"use client";

import { Area, AreaChart, Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { formatMoneyAxis, formatPrice } from "@/lib/format";

/*
 * Single-series charts only: identity comes from axis labels, never from color, so the
 * brand's primary color is the one hue used. Thin marks, recessive grid, hover tooltip.
 */

const axis = { stroke: "var(--muted-foreground)", fontSize: 12, tickLine: false, axisLine: false } as const;
const grid = { stroke: "var(--border)", strokeDasharray: "0", vertical: false } as const;

function ChartTooltip({ active, payload, label, format }: {
  active?: boolean;
  payload?: { value: number }[];
  label?: string;
  format: (value: number) => string;
}) {
  if (!active || !payload?.length) return null;
  return (
    <div className="rounded-lg border bg-popover px-3 py-2 text-xs shadow-md">
      <p className="text-muted-foreground">{label}</p>
      <p className="font-semibold text-foreground">{format(payload[0].value)}</p>
    </div>
  );
}

const shortDate = (iso: string) => new Date(iso).toLocaleDateString("tr-TR", { day: "numeric", month: "short" });

export function RevenueChart({ data }: { data: { date: string; revenue: number }[] }) {
  const rows = data.map((row) => ({ ...row, label: shortDate(row.date) }));

  return (
    <ResponsiveContainer width="100%" height={260}>
      <AreaChart data={rows} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
        <defs>
          <linearGradient id="revenue-fill" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="var(--primary)" stopOpacity={0.25} />
            <stop offset="100%" stopColor="var(--primary)" stopOpacity={0} />
          </linearGradient>
        </defs>
        <CartesianGrid {...grid} />
        <XAxis dataKey="label" {...axis} minTickGap={24} />
        <YAxis {...axis} width={64} tickFormatter={formatMoneyAxis} />
        <Tooltip
          cursor={{ stroke: "var(--muted-foreground)", strokeWidth: 1 }}
          content={({ active, payload, label }) => (
            <ChartTooltip active={active} payload={payload as unknown as { value: number }[]} label={String(label)} format={formatPrice} />
          )}
        />
        <Area type="monotone" dataKey="revenue" stroke="var(--primary)" strokeWidth={2} fill="url(#revenue-fill)" activeDot={{ r: 4 }} isAnimationActive={false} />
      </AreaChart>
    </ResponsiveContainer>
  );
}

/** Horizontal bars for ranked magnitudes (top courses, brands, lessons). */
export function RankBars({
  data,
  format,
  axisFormat = format,
  height,
}: {
  data: { name: string; value: number }[];
  format: (value: number) => string;
  axisFormat?: (value: number) => string;
  height?: number;
}) {
  return (
    <ResponsiveContainer width="100%" height={height ?? Math.max(120, data.length * 44)}>
      <BarChart data={data} layout="vertical" margin={{ top: 0, right: 16, left: 0, bottom: 0 }} barCategoryGap={8}>
        <CartesianGrid {...grid} horizontal={false} vertical />
        <XAxis type="number" {...axis} tickFormatter={axisFormat} />
        <YAxis type="category" dataKey="name" {...axis} width={180} tick={{ fontSize: 12, fill: "var(--foreground)" }} />
        <Tooltip
          cursor={{ fill: "var(--muted)" }}
          content={({ active, payload, label }) => (
            <ChartTooltip active={active} payload={payload as unknown as { value: number }[]} label={String(label)} format={format} />
          )}
        />
        <Bar dataKey="value" fill="var(--primary)" radius={[0, 4, 4, 0]} maxBarSize={22} isAnimationActive={false} />
      </BarChart>
    </ResponsiveContainer>
  );
}

export function PeriodFilter({ value, onChange }: { value: number; onChange: (days: number) => void }) {
  return (
    <div className="inline-flex rounded-lg border p-1 text-sm" role="group" aria-label="Dönem">
      {[7, 30, 90].map((days) => (
        <button
          key={days}
          onClick={() => onChange(days)}
          aria-pressed={value === days}
          className={value === days ? "rounded-md bg-primary px-3 py-1 text-primary-foreground" : "rounded-md px-3 py-1 text-muted-foreground"}
        >
          {days} gün
        </button>
      ))}
    </div>
  );
}
