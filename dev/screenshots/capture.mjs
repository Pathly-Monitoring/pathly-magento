// Captures d'écran du dossier de publication, sur la boutique du test
// d'intégration (dev/integration/run.sh --keep), API Pathly simulée.
// Lancé dans l'image Playwright, sur le réseau Docker du test :
//   dev/screenshots/run.sh fr|en
import net from "node:net";
import { chromium } from "playwright";

const lang = process.argv[2] ?? "fr";
const out = process.argv[3] ?? "/out";
const BASE = "http://localhost:8617";
const ADMIN = `${BASE}/admin_it`;

// La boutique attend localhost:8617 : on relaie ce port vers le conteneur Magento.
net.createServer((c) => {
  const s = net.connect(80, "magento");
  c.pipe(s).pipe(c);
  s.on("error", () => c.destroy());
  c.on("error", () => s.destroy());
}).listen(8617, "127.0.0.1");

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

// The browser of this container cannot reach the simulator: the screenshot of
// the failed step is the cart page of the test shop, served in its place.
let failureShot = null;
await page.route("https://api.pathlyhq.com/**", (route) =>
  route.fulfill({ status: 200, contentType: "image/png", body: failureShot ?? Buffer.alloc(0) }),
);
const shot = (name) => page.screenshot({ path: `${out}/${name}-${lang}.png` });

await page.goto(`${ADMIN}/`);
await page.fill("#username", "pathly");
await page.fill("#login", "integration-2026");
await page.click(".action-login");
await page.waitForLoadState("networkidle");
// Usage data dialog of Adobe, shown once: declined.
const decline = page.getByRole("button", { name: "Don't Allow" });
if (await decline.isVisible().catch(() => false)) await decline.click();

// 1. Réglages : connexion, puis Lumi.
await page.goto(`${ADMIN}/admin/system_config/edit/section/pathly_monitoring/`);
await page.waitForLoadState("networkidle");
for (const id of ["pathly_monitoring_connection", "pathly_monitoring_flow", "pathly_monitoring_badge"]) {
  const head = page.locator(`#${id}-head`);
  if ((await head.count()) && !(await head.evaluate((e) => e.classList.contains("open")))) await head.click();
}
await page.locator("#pathly_monitoring_connection-head").scrollIntoViewIfNeeded();
await page.evaluate(() => window.scrollBy(0, -120));
await shot("1-settings");

// 2. Lumi : consentement, essai, propositions.
await page.locator(".pathly-lumi-owner").check();
await page.locator(".pathly-lumi-start").click();
await page.locator(".pathly-lumi-proposal").nth(1).waitFor({ timeout: 60000 });
await page.locator("#pathly_monitoring_flow").screenshot({ path: `${out}/2-lumi-${lang}.png` });

// 3. Encart du tableau de bord.
const cart = await browser.newPage({ viewport: { width: 1200, height: 750 } });
await cart.goto(`${BASE}/checkout/cart/`);
await cart.waitForLoadState("networkidle");
failureShot = await cart.screenshot();
await cart.close();
await page.goto(`${ADMIN}/admin/dashboard/`);
await page.waitForLoadState("networkidle");
await page.locator(".pathly-shot").evaluate((img) => img.complete || new Promise((r) => (img.onload = r)));
await page.locator(".pathly-panel").screenshot({ path: `${out}/3-dashboard-${lang}.png` });

// 4. Badge en pied de page.
await page.goto(`${BASE}/`);
await page.waitForLoadState("networkidle");
await page.locator(".pathly-badge").scrollIntoViewIfNeeded();
await shot("4-badge");

await browser.close();
process.exit(0);
