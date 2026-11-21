// The designs an author can pick for their space in the admin (Profile →
// Site design). Each sets its own colour tokens (globals.css, by the
// data-template attribute on <html>) and lists posts in one of three ways.
// Midnight and Gazette come with Premium; the blog sends Classic instead
// while an author has no Premium.

export type Template = "classic" | "magazine" | "notebook" | "midnight" | "gazette";

/** How a design lists posts: rows, a grid of cards, or notes with dates in the margin. */
export type Layout = "classic" | "magazine" | "notebook";

export const TEMPLATES: readonly Template[] = ["classic", "magazine", "notebook", "midnight", "gazette"];

const LAYOUTS: Record<Template, Layout> = {
  classic: "classic",
  magazine: "magazine",
  notebook: "notebook",
  midnight: "classic",
  gazette: "magazine",
};

export function templateOf(value: unknown): Template {
  return typeof value === "string" && (TEMPLATES as readonly string[]).includes(value) ? (value as Template) : "classic";
}

export function layoutOf(template: Template): Layout {
  return LAYOUTS[template];
}
