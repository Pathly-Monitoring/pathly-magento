// Icône, bannières et guide PDF du dossier de publication, depuis publication/.
//   docker run --rm -v "$(pwd)/publication:/pub" -w /tmp mcr.microsoft.com/playwright:v1.51.0-jammy \
//     sh -c "npm i -s playwright@1.51.0 && node /pub/../dev/screenshots/assets.mjs"
import { readFileSync } from "node:fs";
import { chromium } from "playwright";

const pub = process.argv[2] ?? "/pub";
const icon = readFileSync(`${pub}/assets/icon.svg`, "utf8");
const browser = await chromium.launch();

for (const size of [512, 240, 128]) {
  const page = await browser.newPage({ viewport: { width: size, height: size } });
  await page.setContent(`<style>html,body{margin:0}svg{width:${size}px;height:${size}px;display:block}</style>${icon}`);
  await page.screenshot({ path: `${pub}/assets/icon-${size}.png`, omitBackground: true });
  await page.close();
}

const banners = {
  fr: ["Pathly Checkout Monitoring", "Sachez avant vos clients quand votre tunnel casse.", "Ping · Flow préparé par Lumi · rejeu après chaque mise à jour"],
  en: ["Pathly Checkout Monitoring", "Know before your customers when your checkout breaks.", "Ping · Flow prepared by Lumi · replay after every update"],
};
for (const [lang, [title, line, sub]] of Object.entries(banners)) {
  const page = await browser.newPage({ viewport: { width: 1280, height: 640 } });
  await page.setContent(`<style>
    body{margin:0;width:1280px;height:640px;background:linear-gradient(135deg,#0b1220 0%,#12303a 100%);
      font-family:"Segoe UI",Arial,sans-serif;color:#fff;display:flex;align-items:center}
    .wrap{display:flex;gap:56px;align-items:center;padding:0 96px}
    svg{width:200px;height:200px;flex:none}
    h1{font-size:60px;margin:0 0 20px;font-weight:700}
    p{font-size:34px;margin:0 0 18px;color:#d7f5f0}
    small{font-size:24px;color:#3ddec9}
  </style><div class="wrap">${icon}<div><h1>${title}</h1><p>${line}</p><small>${sub}</small></div></div>`);
  await page.screenshot({ path: `${pub}/assets/banner-${lang}-1280x640.png` });
  await page.close();
}

const guide = await browser.newPage();
await guide.goto(`file://${pub}/user-guide-en.html`);
await guide.waitForLoadState("networkidle");
await guide.pdf({ path: `${pub}/user-guide-en.pdf`, format: "A4", margin: { top: "18mm", bottom: "18mm", left: "16mm", right: "16mm" } });
await browser.close();
