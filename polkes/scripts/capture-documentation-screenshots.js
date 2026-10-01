/**
 * Capture screenshot halaman publik SIM-BMN untuk panduan PDF.
 *
 * Usage:
 *   set BASE_URL=http://127.0.0.1:8000
 *   node scripts/capture-documentation-screenshots.js
 *
 * Requires:
 *   npm install playwright
 *   npx playwright install chromium
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8000';
const OUT_DIR = path.join(__dirname, '..', 'documentation', 'screenshots');

const PAGES = [
  { file: '01_homepage.png', url: '/' },
  { file: '02_login.png', url: '/login' },
  { file: '05_peminjaman.png', url: '/public/peminjaman-internal' },
  { file: '06_sewa.png', url: '/public/sewa' },
  { file: '07_pemeliharaan.png', url: '/public/pemeliharaan' },
  { file: '08_persediaan.png', url: '/public/persediaan' },
  { file: '09_bhp_kebersihan.png', url: '/public/bhp-kebersihan' },
  { file: '10_sewa_admin_login.png', url: '/sewa-admin/login' },
  // Halaman auth-only membutuhkan kredensial. Lewati di sini;
  // DocumentationGeneratorService akan membuat placeholder berlabel.
];

async function main() {
  fs.mkdirSync(OUT_DIR, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1366, height: 768 },
    deviceScaleFactor: 1,
  });
  const page = await context.newPage();

  for (const item of PAGES) {
    const target = BASE_URL.replace(/\/$/, '') + item.url;
    process.stdout.write(`Capturing ${item.file} <= ${target} ... `);
    try {
      await page.goto(target, { waitUntil: 'networkidle', timeout: 45000 });
      await page.waitForTimeout(800);
      const out = path.join(OUT_DIR, item.file);
      await page.screenshot({ path: out, fullPage: false });
      console.log('OK');
    } catch (err) {
      console.log('FAIL: ' + err.message);
    }
  }

  await browser.close();
  console.log('Done. Output:', OUT_DIR);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
