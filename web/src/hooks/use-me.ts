"use client";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiFetch } from "@/lib/api/client";
import type { RoleName, User } from "@/lib/api/types";

/** The signed-in user of the current host, or null for guests. */
export function useMe() {
  return useQuery({
    queryKey: ["me"],
    queryFn: async () => {
      try {
        return (await apiFetch<{ data: User }>("/auth/me")).data;
      } catch (error) {
        if (error instanceof ApiError && error.status === 401) return null;
        throw error;
      }
    },
    staleTime: 60_000,
  });
}

export function useLogout() {
  const client = useQueryClient();
  return async () => {
    await apiFetch("/auth/logout", { method: "POST" });
    client.setQueryData(["me"], null);
    client.removeQueries({ predicate: (query) => query.queryKey[0] !== "me" });
  };
}

export function hasRole(user: User | null | undefined, ...roles: RoleName[]) {
  return !!user && user.roles.some((role) => roles.includes(role));
}

/** Where a user lands after signing in. */
export function homeFor(user: User): string {
  if (hasRole(user, "platform-admin")) return "/admin";
  if (hasRole(user, "brand-admin")) return "/yonetim";
  if (hasRole(user, "instructor")) return "/egitmen";
  return "/panel";
}
