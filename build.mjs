// Renders src/template.html once per language from src/i18n.json into dist/.
import { readFileSync, writeFileSync, mkdirSync, cpSync, rmSync } from "node:fs";

const tpl = readFileSync("src/template.html", "utf8");
const i18n = JSON.parse(readFileSync("src/i18n.json", "utf8"));

rmSync("dist", { recursive: true, force: true });
cpSync("public", "dist", { recursive: true });

for (const [lang, t] of Object.entries(i18n)) {
  const vars = { ...t, lang };
  for (const l of Object.keys(i18n)) vars[`active_${l}`] = l === lang ? ' aria-current="page"' : "";
  const html = tpl.replace(/\{\{(\w+)\}\}/g, (_, k) => {
    if (!(k in vars)) throw new Error(`Missing i18n key "${k}" for ${lang}`);
    return vars[k];
  });
  const dir = `dist${t.path === "/" ? "" : t.path}`;
  mkdirSync(dir, { recursive: true });
  writeFileSync(`${dir}/index.html`, html);
  console.log(`built ${dir}/index.html`);
}
