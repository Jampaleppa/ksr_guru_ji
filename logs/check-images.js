// Checks which fallback image paths exist in the repo (flat images/ only)
const { execFileSync } = require("child_process");
const fs = require("fs");
const path = require("path");
const gitNames = new Set(
  execFileSync("git", ["ls-tree", "-r", "--name-only", "HEAD", "images"], {
    maxBuffer: 1e9,
  })
    .toString("utf8")
    .split("\n")
    .filter(Boolean),
);

const files = [
  "cooking-oil",
  "dairy",
  "grains",
  "hair-care",
  "health-pharmacy",
  "home-care",
  "dishwash",
  "dry-fruits",
  "kitchen",
  "snacks",
  "spices",
  "cold-drinks",
  "cleaning",
];
for (const f of files) {
  try {
    const raw = execFileSync(
      "git",
      ["show", `HEAD:categories/${f}-fallback.json`],
      { maxBuffer: 5e7 },
    )
      .toString("utf8")
      .replace(/^\uFEFF/, "");
    const j = JSON.parse(raw);
    let total = 0,
      flatOK = 0,
      onDiskOK = 0,
      sample = "";
    for (const item of j) {
      const img = item.image || item.imageUrl || "";
      if (!img) continue;
      total++;
      const base = path.basename(img.replace(/\\/g, "/"));
      if (gitNames.has("images/" + base)) {
        flatOK++;
        if (!sample) sample = base;
      }
      if (fs.existsSync(path.join("images", base))) onDiskOK++;
    }
    console.log(
      `${f}: total=${total} flatInGit=${flatOK} onDisk=${onDiskOK} e.g. ${sample}`,
    );
  } catch (e) {
    console.log(`${f}: (no json) ${e.message.slice(0, 60)}`);
  }
}
