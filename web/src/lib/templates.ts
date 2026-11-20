// The designs an author can pick for their space in the admin (Profile →
// Site design). Each sets its own colour tokens (globals.css, by the
// data-template attribute on <html>) and its own way of listing posts.

export type Template = "classic" | "magazine" | "notebook";

export const TEMPLATES: readonly Template[] = ["classic", "magazine", "notebook"];

export function templateOf(value: unknown): Template {
  return typeof value === "string" && (TEMPLATES as readonly string[]).includes(value) ? (value as Template) : "classic";
}
